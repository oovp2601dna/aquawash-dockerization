<?php
require_once 'database.php';

$error = null;
$success = null;
$openModal = null;       // 'new', 'payment', 'quick_customer'
$paymentTrxId = null;

// Tab aktif untuk Unified Transactions & Payments Hub
$activeTab = $_GET['tab'] ?? 'all'; // 'all', 'unpaid', 'payments'
$filterStatus = $_GET['status_filter'] ?? '';

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'trx_created') $success = "Transaksi baru berhasil dibuat!";
    if ($_GET['msg'] === 'pay_success') $success = "Pembayaran berhasil dicatat!";
    if ($_GET['msg'] === 'status_updated') $success = "Status transaksi berhasil diperbarui!";
    if ($_GET['msg'] === 'cust_added') $success = "Customer baru berhasil ditambahkan!";
    if ($_GET['msg'] === 'pay_deleted') $success = "Data pembayaran berhasil dihapus!";
    if ($_GET['msg'] === 'trx_deleted') $success = "Data transaksi berhasil dihapus!";
}

// Hapus transaksi
if (isset($_GET['delete_trx'])) {
    $id = $_GET['delete_trx'];
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE transaction_id = ?");
    $stmt->execute([$id]);
    header("Location: transactions.php?tab={$activeTab}&msg=trx_deleted");
    exit;
}

// Hapus pembayaran
if (isset($_GET['delete_pay'])) {
    $id = $_GET['delete_pay'];
    $stmt = $pdo->prepare("DELETE FROM payments WHERE payment_id = ?");
    $stmt->execute([$id]);
    header("Location: transactions.php?tab=payments&msg=pay_deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Quick Add Customer persis sama dengan customer_form.php
    if ($_POST['action'] === 'quick_add_customer') {
        $q_name        = trim($_POST['q_name'] ?? '');
        $q_phone       = trim($_POST['q_phone'] ?? '');
        $q_street      = trim($_POST['q_street'] ?? '');
        $q_landmark    = trim($_POST['q_landmark'] ?? '');
        $q_postal_code = trim($_POST['q_postal_code'] ?? '');

        $q_errors = [];
        if ($q_name === '') {
            $q_errors[] = "Nama lengkap wajib diisi.";
        } elseif (!preg_match('/^[\p{L}\s\'\-]+$/u', $q_name)) {
            $q_errors[] = "Nama lengkap hanya boleh berisi huruf dan spasi.";
        }

        if ($q_phone === '') {
            $q_errors[] = "Nomor telepon wajib diisi.";
        } elseif (!preg_match('/^\d{10,13}$/', $q_phone)) {
            $q_errors[] = "Nomor telepon harus berupa angka (10-13 digit).";
        }

        if ($q_street === '') {
            $q_errors[] = "Alamat jalan wajib diisi.";
        }

        if ($q_postal_code === '') {
            $q_errors[] = "Kode pos wajib diisi.";
        } elseif (!preg_match('/^\d{5}$/', $q_postal_code)) {
            $q_errors[] = "Kode pos harus persis 5 digit angka.";
        }

        if (!empty($q_errors)) {
            $error = implode(" ", $q_errors);
            $openModal = 'quick_customer';
        } else {
            // Build address format "Street | Patokan: ... | Kode Pos: ..."
            $address = $q_street;
            if ($q_landmark !== '') $address .= " | Patokan: {$q_landmark}";
            if ($q_postal_code !== '') $address .= " | Kode Pos: {$q_postal_code}";

            $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
            $stmt->execute([$q_name, $q_phone, $address]);
            $new_cust_id = $pdo->lastInsertId();
            header("Location: transactions.php?new=1&cust_id={$new_cust_id}&msg=cust_added");
            exit;
        }
    }

    // Update status transaksi (dropdown inline)
    if ($_POST['action'] === 'update_status') {
        $id = $_POST['transaction_id'];
        $status = $_POST['status'];
        $stmt = $pdo->prepare("UPDATE transactions SET status = ? WHERE transaction_id = ?");
        $stmt->execute([$status, $id]);
        header("Location: transactions.php?tab={$activeTab}&msg=status_updated");
        exit;
    }

    // Buat transaksi baru — panggil Stored Procedure CreateTransaction
    if ($_POST['action'] === 'create_transaction') {
        $customer_id           = $_POST['customer_id'];
        $transaction_date      = $_POST['transaction_date'];
        $estimated_completion  = $_POST['estimated_completion_date'];
        $service_ids           = $_POST['service_id'] ?? [];
        $quantities            = $_POST['quantity'] ?? [];

        $items_csv_parts = [];
        foreach ($service_ids as $index => $sid) {
            $qty = $quantities[$index] ?? 0;
            if ($qty > 0 && $sid) {
                $items_csv_parts[] = $sid . ':' . $qty;
            }
        }

        if (empty($items_csv_parts)) {
            $error = "Pilih minimal satu layanan dengan jumlah lebih dari 0.";
            $openModal = 'new';
        } else {
            $items_csv = implode(',', $items_csv_parts);

            try {
                $stmt = $pdo->prepare("CALL CreateTransaction(?, ?, ?, ?, @p_transaction_id, @p_total_price)");
                $stmt->execute([$customer_id, $transaction_date, $estimated_completion, $items_csv]);
                $stmt->closeCursor();

                $out = $pdo->query("SELECT @p_transaction_id AS trx_id, @p_total_price AS total")->fetch();

                if ($out['trx_id'] == -1) {
                    $error = "Gagal membuat transaksi. Coba lagi.";
                    $openModal = 'new';
                } else {
                    header("Location: transactions.php?msg=trx_created");
                    exit;
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
                $openModal = 'new';
            }
        }
    }

    // Catat pembayaran — panggil Stored Procedure AddPayment
    if ($_POST['action'] === 'add_payment') {
        $trx_id       = $_POST['transaction_id'];
        $method_id    = $_POST['payment_method_id'];
        $amount       = $_POST['amount'];
        $payment_date = $_POST['payment_date'];
        $status       = $_POST['status'];

        $stmt = $pdo->prepare("CALL AddPayment(?, ?, ?, ?, ?, @p_result)");
        $stmt->execute([$trx_id, $method_id, $amount, $payment_date, $status]);
        $stmt->closeCursor();

        $out = $pdo->query("SELECT @p_result AS result")->fetch();

        if ($out['result'] === 'SUCCESS') {
            header("Location: transactions.php?tab=payments&msg=pay_success");
            exit;
        } else {
            $error = $out['result'];
            $openModal = 'payment';
            $paymentTrxId = $trx_id;
        }
    }
}

// Buka modal "New Transaction" otomatis
if (isset($_GET['new'])) {
    $openModal = 'new';
}

// Buka modal "Record Payment" otomatis
if (isset($_GET['pay']) && !$paymentTrxId) {
    $paymentTrxId = $_GET['pay'];
    $openModal = 'payment';
}

// Ambil data transaksi terkait untuk mengisi modal pembayaran
$payTrxData = null;
if ($paymentTrxId) {
    $stmtPay = $pdo->prepare("
        SELECT t.transaction_id, t.total_price, c.name AS customer_name,
               FormatTransactionID(t.transaction_id) AS trx_label,
               IsTransactionPaid(t.transaction_id) AS is_paid
        FROM transactions t
        JOIN customers c ON t.customer_id = c.customer_id
        WHERE t.transaction_id = ?
    ");
    $stmtPay->execute([$paymentTrxId]);
    $payTrxData = $stmtPay->fetch();

    if ($openModal === 'payment' && $payTrxData && $payTrxData['is_paid']) {
        $openModal = null;
        $paymentTrxId = null;
    }
}

// Hitung statistik ringkas untuk tab bar
$count_all     = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
$count_unpaid  = $pdo->query("SELECT COUNT(*) FROM transactions WHERE IsTransactionPaid(transaction_id) = 0")->fetchColumn();
$count_pay_log = $pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn();

// Data pendukung form
$customers       = $pdo->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();
$services        = $pdo->query("SELECT * FROM services ORDER BY name ASC")->fetchAll();
$payment_methods = $pdo->query("SELECT * FROM payment_methods ORDER BY payment_method_id ASC")->fetchAll();

// Mapping service untuk kalkulator JS
$serviceMap = [];
foreach ($services as $s) {
    $serviceMap[$s['service_id']] = [
        'name'  => $s['name'],
        'price' => floatval($s['price_per_unit']),
        'unit'  => $s['unit_type']
    ];
}

include 'includes/header.php';
?>

<div class="header">
    <div>
        <h1 class="page-title">Transactions & Payments Hub</h1>
        <p class="text-muted" style="font-size: 0.9rem; margin-top: 0.2rem;">Laundry order management center, work status, and tracking of completed payments</p>
    </div>
    <button type="button" class="btn btn-primary" onclick="openModal('modalNewTransaction')"><i class="fa-solid fa-plus"></i> Make New Transaction </button>
</div>

<?php if ($success): ?>
<div class="alert-toast">
    <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
    <span><?= htmlspecialchars($success) ?></span>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="card" style="background-color: #FEE2E2; color: #991B1B; padding: 1rem; border-left: 4px solid #B91C1C; margin-bottom: 1.5rem;">
    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- ================= UNIFIED TAB BAR ================= -->
<div class="card" style="padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div class="period-pills">
            <a href="transactions.php?tab=all" class="pill-item <?= $activeTab == 'all' ? 'active' : '' ?>">
                <i class="fa-solid fa-list-check"></i> All Transactions (<?= $count_all ?>)
            </a>
            <a href="transactions.php?tab=unpaid" class="pill-item <?= $activeTab == 'unpaid' ? 'active' : '' ?>" style="<?= $count_unpaid > 0 && $activeTab != 'unpaid' ? 'color: var(--amber-600); border-color: var(--amber-500);' : '' ?>">
                <i class="fa-solid fa-triangle-exclamation"></i> Unpaid (<?= $count_unpaid ?>)
            </a>
            <a href="transactions.php?tab=payments" class="pill-item <?= $activeTab == 'payments' ? 'active' : '' ?>">
                <i class="fa-solid fa-history"></i> Payment History (<?= $count_pay_log ?>)
            </a>
        </div>

        <?php if ($activeTab == 'all'): ?>
        <form method="GET" action="transactions.php" style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
            <input type="hidden" name="tab" value="all">
            <label class="form-label" style="margin:0; font-size: 0.8rem; font-weight: 700;">Filter Status:</label>
            <select name="status_filter" class="form-control" style="width: auto; padding: 0.35rem 1.8rem 0.35rem 0.6rem; font-size: 0.8rem; font-weight: 600;" onchange="this.form.submit()">
                <option value="">-- All Status --</option>
                <?php foreach (['Pending', 'Washing', 'Ironing', 'Ready for Pickup', 'Completed'] as $st): ?>
                    <option value="<?= $st ?>" <?= $filterStatus == $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- ================= TAB CONTENT 1 & 2: DAFTAR TRANSAKSI & UNPAID ================= -->
<?php if ($activeTab == 'all' || $activeTab == 'unpaid'): ?>
<div class="card">
    <div class="header" style="margin-bottom: 1rem;">
        <h2 style="font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid <?= $activeTab == 'unpaid' ? 'fa-triangle-exclamation' : 'fa-receipt' ?>" style="color: <?= $activeTab == 'unpaid' ? 'var(--amber-500)' : 'var(--primary-500)' ?>;"></i>
            <?= $activeTab == 'unpaid' ? 'Daftar Transaksi Belum Lunas (Butuh Pembayaran)' : 'Daftar Semua Transaksi' ?>
        </h2>
    </div>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>TRX ID</th>
                    <th>Customer</th>
                    <th>Transaction Date</th>
                    <th>Est. Completin</th>
                    <th>Total Price</th>
                    <th>Order Status </th>
                    <th>Payment</th>
                    <th style="width: 280px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $where = [];
                $params = [];

                if ($activeTab == 'unpaid') {
                    $where[] = "IsTransactionPaid(t.transaction_id) = 0";
                }
                if (!empty($filterStatus)) {
                    $where[] = "t.status = ?";
                    $params[] = $filterStatus;
                }

                $sql = "
                    SELECT
                        t.*,
                        c.name AS customer_name,
                        FormatTransactionID(t.transaction_id) AS trx_label,
                        IsTransactionPaid(t.transaction_id)   AS is_paid
                    FROM transactions t
                    JOIN customers c ON t.customer_id = c.customer_id
                ";

                if (!empty($where)) {
                    $sql .= " WHERE " . implode(" AND ", $where);
                }
                $sql .= " ORDER BY t.transaction_id DESC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                while ($row = $stmt->fetch()) {
                    $statusClass = strtolower(str_replace(' ', '-', $row['status']));
                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($row['trx_label']) . "</strong></td>";
                    echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
                    echo "<td>" . date('d M Y H:i', strtotime($row['transaction_date'])) . "</td>";
                    echo "<td>" . date('d M Y', strtotime($row['estimated_completion_date'])) . "</td>";
                    echo "<td style='font-weight:800; color:var(--primary-600);'>Rp " . number_format($row['total_price'], 0, ',', '.') . "</td>";
                    echo "<td><span class='badge badge-{$statusClass}'>{$row['status']}</span></td>";

                    if ($row['is_paid']) {
                        echo "<td><span class='badge badge-pay-paid'>Paid</span></td>";
                    } else {
                        echo "<td><span class='badge badge-pay-pending'>Unpaid</span></td>";
                    }

                    echo "<td style='text-align: right; display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center; flex-wrap: wrap;'>";

                    // Inline Status Selector
                    echo "<form method='POST' style='margin:0; display:inline-block;'>";
                    echo "<input type='hidden' name='action' value='update_status'>";
                    echo "<input type='hidden' name='transaction_id' value='{$row['transaction_id']}'>";
                    echo "<select name='status' class='form-control' style='width:auto; padding: 0.25rem 1.8rem 0.25rem 0.5rem; font-size:0.78rem; font-weight:600; display:inline-block;' onchange='this.form.submit()'>";
                    $statuses = ['Pending', 'Washing', 'Ironing', 'Ready for Pickup', 'Completed'];
                    foreach ($statuses as $s) {
                        $selected = ($row['status'] == $s) ? 'selected' : '';
                        echo "<option value='{$s}' {$selected}>{$s}</option>";
                    }
                    echo "</select>";
                    echo "</form>";

                    if (!$row['is_paid']) {
                        $btnAttrs = "data-trx-id='{$row['transaction_id']}' "
                                  . "data-trx-label='" . htmlspecialchars($row['trx_label'], ENT_QUOTES) . "' "
                                  . "data-trx-customer='" . htmlspecialchars($row['customer_name'], ENT_QUOTES) . "' "
                                  . "data-trx-amount='{$row['total_price']}'";
                        echo "<button type='button' class='btn btn-primary btn-sm' {$btnAttrs} onclick='openPaymentModal(this)'><i class='fa-solid fa-money-bill-wave'></i> Bayar</button>";
                    }

                    echo "<a href='transaction_detail.php?id={$row['transaction_id']}' class='btn btn-secondary btn-sm' title='Detail Pesanan'><i class='fa-solid fa-eye'></i></a>";
                    echo "<a href='transactions.php?delete_trx={$row['transaction_id']}&tab={$activeTab}' class='btn btn-danger btn-sm' onclick='return confirm(\"Yakin ingin menghapus transaksi ini?\");' title='Hapus'><i class='fa-solid fa-trash'></i></a>";
                    echo "</td>";
                    echo "</tr>";
                }
                if ($stmt->rowCount() == 0) {
                    echo "<tr><td colspan='8' style='text-align:center; padding: 2rem; color: var(--gray-500);'>Belum ada transaksi pada kategori ini</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ================= TAB CONTENT 3: RIWAYAT PEMBAYARAN ================= -->
<?php if ($activeTab == 'payments'): ?>
<div class="card">
    <div class="header" style="margin-bottom: 1rem;">
        <h2 style="font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-history" style="color: var(--primary-500);"></i>
            Paid Payment History & Cash Records
        </h2>
    </div>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>TRX ID</th>
                    <th>Customer</th>
                    <th>Payment Method</th>
                    <th style="text-align: right;">Amount to Pay</th>
                    <th>Payment Date</th>
                    <th>Status</th>
                    <th style="width: 100px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("
                    SELECT p.*, 
                           pm.name AS method_name,
                           t.total_price,
                           c.name AS customer_name,
                           FormatTransactionID(t.transaction_id) AS trx_label
                    FROM payments p
                    JOIN payment_methods pm ON p.payment_method_id = pm.payment_method_id
                    JOIN transactions t ON p.transaction_id = t.transaction_id
                    JOIN customers c ON t.customer_id = c.customer_id
                    ORDER BY p.payment_id DESC
                ");
                while ($row = $stmt->fetch()) {
                    $statusClass = strtolower($row['status']);
                    echo "<tr>";
                    echo "<td><strong>".htmlspecialchars($row['trx_label'])."</strong></td>";
                    echo "<td>".htmlspecialchars($row['customer_name'])."</td>";
                    echo "<td><span style='font-weight:600;'>".htmlspecialchars($row['method_name'])."</span></td>";
                    echo "<td style='text-align: right; font-weight: 800; color: var(--primary-600);'>Rp ".number_format($row['amount'], 0, ',', '.')."</td>";
                    echo "<td>".date('d M Y H:i', strtotime($row['payment_date']))."</td>";
                    echo "<td><span class='badge badge-pay-{$statusClass}'>{$row['status']}</span></td>";
                    echo "<td style='text-align: right;'>
                            <a href='transactions.php?delete_pay={$row['payment_id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Hapus data pembayaran ini?\");' title='Hapus'><i class='fa-solid fa-trash'></i></a>
                          </td>";
                    echo "</tr>";
                }
                if ($stmt->rowCount() == 0) {
                    echo "<tr><td colspan='7' style='text-align:center; padding: 2rem; color: var(--gray-500);'>Belum ada riwayat pembayaran</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- ================= Modal: New Transaction ================= -->
<div class="modal-overlay" id="modalNewTransaction">
    <div class="modal-box">
        <div class="modal-header">
            <h2><i class="fa-solid fa-receipt" style="color: var(--primary-500);"></i> Make New Transaction </h2>
            <button type="button" class="modal-close" onclick="closeModal('modalNewTransaction')"><i class="fa-solid fa-times"></i></button>
        </div>

        <form method="POST" id="trxForm">
            <input type="hidden" name="action" value="create_transaction">
            
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                    <label class="form-label" style="margin-bottom:0;">Choose Customer</label>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modalNewTransaction'); openModal('modalQuickCustomer');" style="padding: 0.2rem 0.6rem; font-size: 0.75rem;">
                        <i class="fa-solid fa-user-plus"></i> + New Customer
                    </button>
                </div>
                <select name="customer_id" class="form-control" required>
                    <option value="">-- Choose Customer --</option>
                    <?php 
                    $preset_cust = $_GET['cust_id'] ?? null;
                    foreach ($customers as $c): 
                        $sel = ($preset_cust == $c['customer_id']) ? 'selected' : '';
                    ?>
                        <option value="<?= $c['customer_id'] ?>" <?= $sel ?>><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['phone']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Tanggal Transaksi</label>
                    <input type="datetime-local" name="transaction_date" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Est. Tanggal Selesai</label>
                    <input type="datetime-local" name="estimated_completion_date" class="form-control" required value="<?= date('Y-m-d\TH:i', strtotime('+2 days')) ?>">
                </div>
            </div>

            <h3 style="margin: 1.25rem 0 0.85rem; padding-bottom: 0.4rem; border-bottom: 1px solid var(--border); font-size: 1rem; color: var(--gray-800);">
                <i class="fa-solid fa-basket-shopping" style="color: var(--accent-500);"></i> Layanan Laundry (Items)
            </h3>

            <div id="service-items">
                <div class="flex-row item-row" style="display: flex; gap: 0.75rem; margin-bottom: 0.85rem; align-items: flex-end;">
                    <div class="form-group" style="flex: 2; margin-bottom: 0;">
                        <label class="form-label">Pilih Layanan</label>
                        <select name="service_id[]" class="form-control service-select" required onchange="calculateLiveTotal()">
                            <option value="">-- Pilih Layanan --</option>
                            <?php foreach ($services as $s): ?>
                                <option value="<?= $s['service_id'] ?>"><?= htmlspecialchars($s['name']) ?> — Rp <?= number_format($s['price_per_unit'],0,',','.') ?>/<?= htmlspecialchars($s['unit_type']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label class="form-label">Jumlah / Qty</label>
                        <input type="number" step="0.01" name="quantity[]" class="form-control qty-input" required min="0.01" placeholder="cth: 2.5" oninput="calculateLiveTotal()">
                    </div>
                    <div style="flex: 0; padding-bottom: 0.1rem;">
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)" style="padding: 0.65rem 0.85rem;"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-secondary btn-sm" id="add-item-btn" style="margin-bottom: 1.5rem;"><i class="fa-solid fa-plus"></i> Tambah Layanan Lain</button>

            <!-- Ringkasan Total Live Calculation -->
            <div style="background: var(--primary-50); border: 1px solid var(--primary-200); border-radius: var(--radius-md); padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 0.85rem; font-weight: 600; color: var(--gray-600);">Estimasi Grand Total:</span>
                    <h3 id="liveGrandTotal" style="font-size: 1.4rem; font-weight: 800; color: var(--primary-600); margin: 0;">Rp 0</h3>
                </div>
                <div style="font-size: 0.8rem; color: var(--gray-500); text-align: right;">
                    <i class="fa-solid fa-calculator"></i> Dihitung otomatis
                </div>
            </div>

            <div style="text-align: right;">
                <button type="submit" class="btn btn-primary" style="font-size: 1rem; padding: 0.75rem 2rem;"><i class="fa-solid fa-check-circle"></i> Simpan & Buat Transaksi</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= Modal: Quick Add Customer (Persis Customer Form) ================= -->
<div class="modal-overlay" id="modalQuickCustomer">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <h2><i class="fa-solid fa-user-plus" style="color: var(--primary-500);"></i> Tambah Customer Cepat</h2>
            <button type="button" class="modal-close" onclick="closeModal('modalQuickCustomer'); openModal('modalNewTransaction');"><i class="fa-solid fa-times"></i></button>
        </div>

        <form method="POST" id="quickCustomerForm">
            <input type="hidden" name="action" value="quick_add_customer">

            <div class="form-group">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="q_name" id="qNameInput" class="form-control" required placeholder="Contoh: Budi Santoso" pattern="[A-Za-zÀ-ÖØ-öø-ÿ\s'\-]+">
            </div>

            <div class="form-group">
                <label class="form-label">Nomor Telepon / WA</label>
                <input type="tel" name="q_phone" id="qPhoneInput" class="form-control" required placeholder="Contoh: 081234567890" maxlength="13" inputmode="numeric" pattern="\d{10,13}">
            </div>

            <div class="form-group">
                <label class="form-label">Alamat Jalan / Lengkap</label>
                <input type="text" name="q_street" class="form-control" required placeholder="Contoh: Jl. Sudirman No. 12, RT 03/RW 05">
            </div>

            <div class="form-group">
                <label class="form-label">Patokan / Landmark (opsional)</label>
                <input type="text" name="q_landmark" class="form-control" placeholder="Contoh: Dekat Indomaret / sebelah masjid">
            </div>

            <div class="form-group">
                <label class="form-label">Kode Pos (5 Digit)</label>
                <input type="text" name="q_postal_code" id="qPostalInput" class="form-control" required placeholder="Contoh: 12345" maxlength="5" inputmode="numeric" pattern="\d{5}" style="max-width: 160px;">
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalQuickCustomer'); openModal('modalNewTransaction');">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan Customer</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= Modal: Record Payment ================= -->
<div class="modal-overlay" id="modalPayment">
    <div class="modal-box modal-sm">
        <div class="modal-header">
            <h2><i class="fa-solid fa-money-bill-wave" style="color: var(--success-text);"></i> Catat Pembayaran</h2>
            <button type="button" class="modal-close" onclick="closeModal('modalPayment')"><i class="fa-solid fa-times"></i></button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="add_payment">
            <input type="hidden" name="transaction_id" id="pay-trx-id" value="<?= htmlspecialchars($paymentTrxId ?? '') ?>">

            <div class="form-group">
                <label class="form-label">Transaksi</label>
                <input type="text" class="form-control" id="pay-trx-display" disabled style="font-weight: 700;">
            </div>

            <div class="form-group">
                <label class="form-label">Metode Pembayaran</label>
                <select name="payment_method_id" class="form-control" required>
                    <option value="">-- Pilih Metode --</option>
                    <?php foreach ($payment_methods as $m): ?>
                        <option value="<?= $m['payment_method_id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Jumlah Pembayaran (Rp)</label>
                <input type="number" step="0.01" name="amount" id="pay-amount" class="form-control" required placeholder="e.g. 50000" style="font-size: 1.1rem; font-weight: 700;">
            </div>

            <div class="form-group">
                <label class="form-label">Tanggal Pembayaran</label>
                <input type="datetime-local" name="payment_date" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Status Pembayaran</label>
                <select name="status" class="form-control" required>
                    <option value="Paid" selected>Paid (Lunas)</option>
                    <option value="Pending">Pending</option>
                    <option value="Failed">Failed</option>
                </select>
            </div>

            <div style="text-align: right; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;"><i class="fa-solid fa-save"></i> Simpan Pembayaran</button>
            </div>
        </form>
    </div>
</div>

<script>
var serviceMap = <?= json_encode($serviceMap) ?>;

function calculateLiveTotal() {
    var rows = document.querySelectorAll('#service-items .item-row');
    var grandTotal = 0;

    rows.forEach(function(row) {
        var select = row.querySelector('.service-select');
        var qtyInput = row.querySelector('.qty-input');

        var sid = select ? select.value : null;
        var qty = qtyInput ? parseFloat(qtyInput.value) || 0 : 0;

        if (sid && serviceMap[sid] && qty > 0) {
            grandTotal += serviceMap[sid].price * qty;
        }
    });

    var formatted = new Intl.NumberFormat('id-ID').format(grandTotal);
    document.getElementById('liveGrandTotal').innerText = 'Rp ' + formatted;
}

function removeRow(btn) {
    var container = document.getElementById('service-items');
    if (container.children.length > 1) {
        btn.closest('.item-row').remove();
        calculateLiveTotal();
    } else {
        alert('Minimal harus ada 1 item layanan.');
    }
}

document.getElementById('add-item-btn').addEventListener('click', function() {
    var container = document.getElementById('service-items');
    var firstRow = container.children[0];
    var newRow = firstRow.cloneNode(true);
    newRow.querySelector('select').value = '';
    newRow.querySelector('input').value = '';
    container.appendChild(newRow);
    calculateLiveTotal();
});

// Validasi input di quick customer modal
if (document.getElementById('qNameInput')) {
    document.getElementById('qNameInput').addEventListener('input', function() {
        const cleaned = this.value.replace(/[^A-Za-zÀ-ÖØ-öø-ÿ\s'\-]/g, '');
        if (cleaned !== this.value) {
            alert('Nama lengkap harus berupa huruf.');
            this.value = cleaned;
        }
    });
}

if (document.getElementById('qPhoneInput')) {
    document.getElementById('qPhoneInput').addEventListener('input', function() {
        const cleaned = this.value.replace(/\D/g, '').slice(0, 13);
        if (cleaned !== this.value) {
            alert('Nomor telepon harus angka saja (10-13 digit).');
            this.value = cleaned;
        }
    });
}

if (document.getElementById('qPostalInput')) {
    document.getElementById('qPostalInput').addEventListener('input', function() {
        const cleaned = this.value.replace(/\D/g, '').slice(0, 5);
        if (cleaned !== this.value) {
            alert('Kode pos harus 5 digit angka.');
            this.value = cleaned;
        }
    });
}

function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.classList.add('modal-open');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.classList.remove('modal-open');
}

function openPaymentModal(btn) {
    var trxId       = btn.getAttribute('data-trx-id');
    var trxLabel    = btn.getAttribute('data-trx-label');
    var trxCustomer = btn.getAttribute('data-trx-customer');
    var trxAmount   = btn.getAttribute('data-trx-amount');

    document.getElementById('pay-trx-id').value = trxId;
    document.getElementById('pay-trx-display').value = trxLabel + ' — ' + trxCustomer;
    document.getElementById('pay-amount').value = parseFloat(trxAmount).toFixed(2);

    openModal('modalPayment');
}

document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            overlay.classList.remove('active');
            document.body.classList.remove('modal-open');
        }
    });
});

<?php if ($openModal === 'new'): ?>
openModal('modalNewTransaction');
<?php elseif ($openModal === 'quick_customer'): ?>
openModal('modalQuickCustomer');
<?php elseif ($openModal === 'payment' && $paymentTrxId): ?>
document.getElementById('pay-trx-id').value = <?= json_encode((string) $paymentTrxId) ?>;
<?php if ($payTrxData): ?>
document.getElementById('pay-trx-display').value = <?= json_encode($payTrxData['trx_label'] . ' — ' . $payTrxData['customer_name']) ?>;
document.getElementById('pay-amount').value = <?= json_encode(number_format((float) $payTrxData['total_price'], 2, '.', '')) ?>;
<?php endif; ?>
openModal('modalPayment');
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
