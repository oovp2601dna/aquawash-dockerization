<?php
require_once 'database.php';

$customers = $pdo->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();
$services = $pdo->query("SELECT * FROM services ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = null;

    $customer_id            = $_POST['customer_id'];
    $transaction_date       = $_POST['transaction_date'];
    $estimated_completion   = $_POST['estimated_completion_date'];
    $service_ids            = $_POST['service_id'] ?? [];
    $quantities             = $_POST['quantity'] ?? [];

    // Bangun string CSV untuk stored procedure: "service_id:qty,service_id:qty"
    $items_csv_parts = [];
    foreach ($service_ids as $index => $sid) {
        $qty = $quantities[$index] ?? 0;
        if ($qty > 0 && $sid) {
            $items_csv_parts[] = $sid . ':' . $qty;
        }
    }

    if (empty($items_csv_parts)) {
        $error = "You must select at least one service with quantity > 0";
    } else {
        $items_csv = implode(',', $items_csv_parts);

        try {
            // Panggil Stored Procedure CreateTransaction
            $stmt = $pdo->prepare("CALL CreateTransaction(?, ?, ?, ?, @p_transaction_id, @p_total_price)");
            $stmt->execute([$customer_id, $transaction_date, $estimated_completion, $items_csv]);
            $stmt->closeCursor(); // tutup result set dari CALL

            // Ambil OUT parameter
            $out = $pdo->query("SELECT @p_transaction_id AS trx_id, @p_total_price AS total")->fetch();

            if ($out['trx_id'] == -1) {
                $error = "Gagal membuat transaksi. Coba lagi.";
            } else {
                header("Location: transactions.php");
                exit;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>

<div class="header">
    <h1 class="page-title">New Transaction</h1>
    <a href="transactions.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back</a>
</div>

<?php if (isset($error)): ?>
<div class="card" style="background-color: #FEE2E2; color: #991B1B; padding: 1rem; border-left: 4px solid #B91C1C;">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="card" style="max-width: 800px;">
    <form method="POST" id="trxForm">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group" style="grid-column: span 2;">
                <label class="form-label">Customer</label>
                <select name="customer_id" class="form-control" required>
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['customer_id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['phone']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Transaction Date</label>
                <input type="datetime-local" name="transaction_date" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Est. Completion Date</label>
                <input type="datetime-local" name="estimated_completion_date" class="form-control" required value="<?= date('Y-m-d\TH:i', strtotime('+2 days')) ?>">
            </div>
        </div>

        <h3 style="margin: 1.5rem 0 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border);">Services Entry</h3>

        <div id="service-items">
            <div class="flex-row" style="display: flex; gap: 1rem; margin-bottom: 1rem; align-items: flex-end;">
                <div class="form-group" style="flex: 2; margin-bottom: 0;">
                    <label class="form-label">Service</label>
                    <select name="service_id[]" class="form-control" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= $s['service_id'] ?>"><?= htmlspecialchars($s['name']) ?> - Rp <?= number_format($s['price_per_unit'],0,',','.') ?>/<?= htmlspecialchars($s['unit_type']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label class="form-label">Qty</label>
                    <input type="number" step="0.01" name="quantity[]" class="form-control" required min="0.01">
                </div>
                <div style="flex: 0; padding-bottom: 0.5rem;">
                    <button type="button" class="btn btn-secondary" onclick="this.parentElement.parentElement.remove()" style="padding: 0.75rem 1rem;"><i class="fa-solid fa-times"></i></button>
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-secondary btn-sm" id="add-item-btn" style="margin-bottom: 2rem;"><i class="fa-solid fa-plus"></i> Add Another Item</button>

        <div style="text-align: right;">
            <button type="submit" class="btn btn-primary" style="font-size: 1.1rem; padding: 0.75rem 2rem;"><i class="fa-solid fa-check-circle"></i> Create Transaction</button>
        </div>
    </form>
</div>

<script>
document.getElementById('add-item-btn').addEventListener('click', function() {
    var container = document.getElementById('service-items');
    var row = container.children[0].cloneNode(true);
    row.querySelector('select').value = '';
    row.querySelector('input').value = '';
    container.appendChild(row);
});
</script>

<?php include 'includes/footer.php'; ?>
