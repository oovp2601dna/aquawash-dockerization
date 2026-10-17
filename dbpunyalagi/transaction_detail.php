<?php
require_once 'database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    die("Transaction ID missing.");
}

// POST handler untuk 1-click update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $new_status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE transactions SET status = ? WHERE transaction_id = ?");
    $stmt->execute([$new_status, $id]);
    header("Location: transaction_detail.php?id={$id}&msg=status_updated");
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        t.*,
        c.name, c.phone, c.address,
        FormatTransactionID(t.transaction_id) AS trx_label,
        IsTransactionPaid(t.transaction_id)   AS is_paid,
        CalculateTransactionTotal(t.transaction_id) AS calculated_total
    FROM transactions t
    JOIN customers c ON t.customer_id = c.customer_id
    WHERE t.transaction_id = ?
");
$stmt->execute([$id]);
$trx = $stmt->fetch();

if (!$trx) {
    die("Transaction not found.");
}

$stmtItems = $pdo->prepare("
    SELECT ti.*, s.name, s.price_per_unit, s.unit_type
    FROM transaction_items ti
    JOIN services s ON ti.service_id = s.service_id
    WHERE ti.transaction_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll();

$stmtPay = $pdo->prepare("
    SELECT p.*, pm.name AS method_name
    FROM payments p
    JOIN payment_methods pm ON p.payment_method_id = pm.payment_method_id
    WHERE p.transaction_id = ?
    ORDER BY p.payment_date DESC
");
$stmtPay->execute([$id]);
$payments = $stmtPay->fetchAll();

// Mapping urutan status untuk progress stepper
$statusSteps = ['Pending', 'Washing', 'Ironing', 'Ready for Pickup', 'Completed'];
$currentStatus = $trx['status'];
$currentIndex = array_search($currentStatus, $statusSteps);
if ($currentIndex === false) $currentIndex = 0;

// Menentukan status berikutnya untuk tombol 1-click
$nextStatus = ($currentIndex < count($statusSteps) - 1) ? $statusSteps[$currentIndex + 1] : null;

include 'includes/header.php';
?>

<div class="header">
    <div>
        <h1 class="page-title">Detail Transaksi <?= htmlspecialchars($trx['trx_label']) ?></h1>
        <p class="text-muted" style="font-size: 0.9rem; margin-top: 0.2rem;">Lacak status pesanan, rincian item, dan pencatatan pembayaran</p>
    </div>
    <div style="display: flex; gap: 0.5rem;" class="no-print">
        <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> Cetak Nota / Receipt</button>
        <a href="transactions.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'status_updated'): ?>
<div class="alert-toast no-print">
    <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
    <span>Status pesanan berhasil diperbarui ke <strong><?= htmlspecialchars($currentStatus) ?></strong></span>
</div>
<?php endif; ?>

<!-- ================= VISUAL ORDER STATUS STEPPER ================= -->
<div class="card no-print">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
        <h3 style="font-size: 1rem; font-weight: 700; color: var(--gray-800);">
            <i class="fa-solid fa-bars-progress" style="color: var(--primary-500);"></i> Progres Pesanan Laundry
        </h3>
        
        <?php if ($nextStatus): ?>
        <form method="POST" style="margin: 0;">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="status" value="<?= htmlspecialchars($nextStatus) ?>">
            <button type="submit" class="btn btn-primary btn-sm">
                Lanjut ke: <strong><?= htmlspecialchars($nextStatus) ?></strong> <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>
        <?php else: ?>
        <span class="badge badge-completed" style="font-size: 0.85rem; padding: 0.4rem 0.9rem;"><i class="fa-solid fa-check-double"></i> Pesanan Selesai</span>
        <?php endif; ?>
    </div>

    <div class="order-stepper">
        <?php 
        $stepLabels = [
            'Pending' => 'Pending (Diterima)',
            'Washing' => 'Cuci (Washing)',
            'Ironing' => 'Setrika (Ironing)',
            'Ready for Pickup' => 'Siap Diambil',
            'Completed' => 'Selesai (Completed)'
        ];
        foreach ($statusSteps as $idx => $step): 
            $class = '';
            if ($idx < $currentIndex) {
                $class = 'completed';
            } elseif ($idx === $currentIndex) {
                $class = 'active';
            }
        ?>
        <div class="step-item <?= $class ?>">
            <div class="step-circle">
                <?php if ($idx < $currentIndex): ?>
                    <i class="fa-solid fa-check"></i>
                <?php else: ?>
                    <?= $idx + 1 ?>
                <?php endif; ?>
            </div>
            <div class="step-label"><?= htmlspecialchars($stepLabels[$step]) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; align-items: start;">

    <!-- Kolom Kiri: Customer Info + Payment Info -->
    <div>
        <div class="card">
            <h3 style="margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; font-size: 1.05rem;">
                <i class="fa-solid fa-user" style="color: var(--accent-500);"></i> Informasi Customer
            </h3>
            <p><strong>Nama:</strong> <?= htmlspecialchars($trx['name']) ?></p>
            <p style="margin-top:0.4rem;"><strong>Telepon / WA:</strong> <?= htmlspecialchars($trx['phone']) ?></p>
            <p style="margin-top:0.4rem;"><strong>Alamat:</strong> <?= nl2br(htmlspecialchars($trx['address'])) ?></p>
            <br>
            <h3 style="margin-bottom: 0.75rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; font-size: 1.05rem;">
                <i class="fa-solid fa-clock" style="color: var(--primary-500);"></i> Waktu & Status
            </h3>
            <p style="font-size: 0.9rem;"><strong>Tgl Masuk:</strong> <?= date('d M Y H:i', strtotime($trx['transaction_date'])) ?></p>
            <p style="margin-top:0.3rem; font-size: 0.9rem;"><strong>Est. Selesai:</strong> <?= date('d M Y', strtotime($trx['estimated_completion_date'])) ?></p>
        </div>

        <!-- Payment Info -->
        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                <h3 style="font-size: 1.05rem;"><i class="fa-solid fa-credit-card" style="color: var(--success-text);"></i> Status Pembayaran</h3>
                <?php if (!$trx['is_paid']): ?>
                    <a href="transactions.php?pay=<?= $trx['transaction_id'] ?>" class="btn btn-primary btn-sm no-print"><i class="fa-solid fa-plus"></i> Catat Bayar</a>
                <?php endif; ?>
            </div>

            <?php if ($trx['is_paid']): ?>
                <div style="background: var(--success-bg); border-radius: var(--radius-md); padding: 0.8rem 1rem; margin-bottom: 1rem; color: var(--success-text); font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i> LUNAS (PAID)
                </div>
            <?php else: ?>
                <div style="background: var(--warning-bg); border-radius: var(--radius-md); padding: 0.8rem 1rem; margin-bottom: 1rem; color: var(--warning-text); font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.2rem;"></i> BELUM LUNAS (UNPAID)
                </div>
            <?php endif; ?>

            <?php if (empty($payments)): ?>
                <p style="color: var(--gray-500); font-size:0.88rem;">Belum ada riwayat pembayaran yang dicatat.</p>
            <?php else: ?>
                <?php foreach ($payments as $pay):
                    $payClass = strtolower($pay['status']);
                ?>
                <div style="border: 1px solid var(--border); border-radius: var(--radius-md); padding: 0.8rem 1rem; margin-bottom: 0.8rem; background: var(--surface-muted);">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-weight:700; color: var(--gray-800);"><?= htmlspecialchars($pay['method_name']) ?></span>
                        <span class="badge badge-pay-<?= $payClass ?>"><?= $pay['status'] ?></span>
                    </div>
                    <p style="margin-top:0.3rem; font-size:1.15rem; color:var(--primary-600); font-weight:800;">
                        Rp <?= number_format($pay['amount'], 0, ',', '.') ?>
                    </p>
                    <p style="font-size:0.78rem; color:var(--gray-500);">
                        <?= date('d M Y H:i', strtotime($pay['payment_date'])) ?>
                    </p>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Kolom Kanan: Rincian Items -->
    <div class="card">
        <h3 style="margin-bottom: 1rem; font-size: 1.05rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-list-check" style="color: var(--primary-500);"></i> Rincian Layanan Laundry
        </h3>
        <table>
            <thead>
                <tr>
                    <th>Layanan</th>
                    <th style="text-align: right;">Harga Satuan</th>
                    <th style="text-align: center;">Jumlah / Qty</th>
                    <th style="text-align: right;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                    <td style="text-align: right;">Rp <?= number_format($item['price_per_unit'], 0, ',', '.') ?> / <?= $item['unit_type'] ?></td>
                    <td style="text-align: center; font-weight: 600;"><?= floatval($item['quantity']) ?> <?= $item['unit_type'] ?></td>
                    <td style="text-align: right; font-weight: 700; color: var(--gray-900);">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" style="text-align: right; font-size: 1.1rem; color: var(--gray-900); padding-top: 1.25rem;">Grand Total Transaksi:</th>
                    <th style="text-align: right; font-size: 1.35rem; color: var(--primary-600); font-weight: 800; padding-top: 1.25rem;">
                        Rp <?= number_format($trx['calculated_total'], 0, ',', '.') ?>
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
