<?php
require_once 'database.php';

$transaction_id = $_GET['transaction_id'] ?? null;

// Ambil semua transaksi yang belum lunas — gunakan Function IsTransactionPaid
$transactions = $pdo->query("
    SELECT t.transaction_id, t.total_price, c.name AS customer_name
    FROM transactions t
    JOIN customers c ON t.customer_id = c.customer_id
    WHERE IsTransactionPaid(t.transaction_id) = 0
    ORDER BY t.transaction_id DESC
")->fetchAll();

$payment_methods = $pdo->query("SELECT * FROM payment_methods ORDER BY payment_method_id ASC")->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trx_id       = $_POST['transaction_id'];
    $method_id    = $_POST['payment_method_id'];
    $amount       = $_POST['amount'];
    $payment_date = $_POST['payment_date'];
    $status       = $_POST['status'];

    // Panggil Stored Procedure AddPayment
    $stmt = $pdo->prepare("CALL AddPayment(?, ?, ?, ?, ?, @p_result)");
    $stmt->execute([$trx_id, $method_id, $amount, $payment_date, $status]);
    $stmt->closeCursor(); // tutup result set dari CALL

    $out = $pdo->query("SELECT @p_result AS result")->fetch();

    if ($out['result'] === 'SUCCESS') {
        header("Location: payments.php");
        exit;
    } else {
        $error = $out['result'];
    }
}

include 'includes/header.php';
?>

<div class="header">
    <h1 class="page-title">Add Payment</h1>
    <a href="payments.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Back</a>
</div>

<?php if ($error): ?>
<div class="card" style="background-color: #FEE2E2; color: #991B1B; padding: 1rem; border-left: 4px solid #B91C1C; margin-bottom: 1rem;">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="card" style="max-width: 600px;">
    <form method="POST">
        <div class="form-group">
            <label class="form-label">Transaction</label>
            <select name="transaction_id" class="form-control" required id="trx-select" onchange="fillAmount(this)">
                <option value="">-- Select Transaction --</option>
                <?php foreach ($transactions as $t): ?>
                    <option value="<?= $t['transaction_id'] ?>"
                            data-amount="<?= $t['total_price'] ?>"
                            <?= ($transaction_id == $t['transaction_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['customer_name']) ?> — Rp <?= number_format($t['total_price'], 0, ',', '.') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Payment Method</label>
            <select name="payment_method_id" class="form-control" required>
                <option value="">-- Select Method --</option>
                <?php foreach ($payment_methods as $m): ?>
                    <option value="<?= $m['payment_method_id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Amount (Rp)</label>
            <input type="number" step="0.01" name="amount" id="amount-input" class="form-control" required placeholder="e.g. 50000">
        </div>

        <div class="form-group">
            <label class="form-label">Payment Date</label>
            <input type="datetime-local" name="payment_date" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control" required>
                <option value="Pending">Pending</option>
                <option value="Paid" selected>Paid</option>
                <option value="Failed">Failed</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Payment</button>
    </form>
</div>

<script>
function fillAmount(sel) {
    var amount = sel.options[sel.selectedIndex].getAttribute('data-amount');
    if (amount) {
        document.getElementById('amount-input').value = parseFloat(amount).toFixed(2);
    }
}
window.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('trx-select');
    if (sel.value) fillAmount(sel);
});
</script>

<?php include 'includes/footer.php'; ?>
