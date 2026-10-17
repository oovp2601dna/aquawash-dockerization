<?php
require_once 'database.php';

// Revenue period filter
$period = $_GET['period'] ?? 'today';
$today = date('Y-m-d');

switch ($period) {
    case 'yesterday':
        $start_date = date('Y-m-d', strtotime('-1 day'));
        $end_date   = date('Y-m-d', strtotime('-1 day'));
        $period_label = 'Yesterday (' . date('d M Y', strtotime($start_date)) . ')';
        break;
    case '2days':
        $start_date = date('Y-m-d', strtotime('-1 day'));
        $end_date   = $today;
        $period_label = 'Last 2 Days (' . date('d M', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)) . ')';
        break;
    case '3days':
        $start_date = date('Y-m-d', strtotime('-2 days'));
        $end_date   = $today;
        $period_label = 'Last 3 Days (' . date('d M', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)) . ')';
        break;
    case '7days':
        $start_date = date('Y-m-d', strtotime('-6 days'));
        $end_date   = $today;
        $period_label = 'Last 7 Days (7 Days)';
        break;
    case '1month':
        $start_date = date('Y-m-d', strtotime('-29 days'));
        $end_date   = $today;
        $period_label = 'Last 1 Month (1 Month)';
        break;
    case '1year':
        $start_date = date('Y-m-d', strtotime('-364 days'));
        $end_date   = $today;
        $period_label = 'Last 1 Year (1 Year)';
        break;
    case 'custom':
        $start_date = !empty($_GET['start_date']) ? $_GET['start_date'] : $today;
        $end_date   = !empty($_GET['end_date']) ? $_GET['end_date'] : $today;
        $period_label = date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date));
        break;
    case 'all':
        $start_date = '2000-01-01';
        $end_date   = '2099-12-31';
        $period_label = 'All Time';
        break;
    case 'today':
    default:
        $period = 'today';
        $start_date = $today;
        $end_date   = $today;
        $period_label = 'Today (' . date('d M Y') . ')';
        break;
}

// 1. Total Transactions in period
$stmtTrx = $pdo->prepare("SELECT COUNT(*) AS total FROM transactions WHERE DATE(transaction_date) BETWEEN ? AND ?");
$stmtTrx->execute([$start_date, $end_date]);
$total_transactions = $stmtTrx->fetch()['total'];

// 2. Total Revenue in period
$stmtRev = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) AS total FROM transactions WHERE DATE(transaction_date) BETWEEN ? AND ?");
$stmtRev->execute([$start_date, $end_date]);
$total_revenue = floatval($stmtRev->fetch()['total']);

// 3. Paid Revenue in period
$stmtPaid = $pdo->prepare("
    SELECT COALESCE(SUM(p.amount), 0) AS total
    FROM payments p
    JOIN transactions t ON p.transaction_id = t.transaction_id
    WHERE p.status = 'Paid' AND DATE(t.transaction_date) BETWEEN ? AND ?
");
$stmtPaid->execute([$start_date, $end_date]);
$paid_revenue = floatval($stmtPaid->fetch()['total']);

$unpaid_revenue = max(0, $total_revenue - $paid_revenue);
$avg_revenue = $total_transactions > 0 ? ($total_revenue / $total_transactions) : 0;

// 4. Daily Data for chart/breakdown
$stmtDaily = $pdo->prepare("
    SELECT
        DATE(transaction_date) AS tgl,
        COUNT(*) AS count_trx,
        COALESCE(SUM(total_price), 0) AS daily_rev
    FROM transactions
    WHERE DATE(transaction_date) BETWEEN ? AND ?
    GROUP BY DATE(transaction_date)
    ORDER BY tgl ASC
");
$stmtDaily->execute([$start_date, $end_date]);
$daily_data = $stmtDaily->fetchAll();

$max_daily = 0;
foreach ($daily_data as $d) {
    if ($d['daily_rev'] > $max_daily) $max_daily = $d['daily_rev'];
}

// 5. Recent Transactions (top 5)
$stmtRecent = $pdo->query("
    SELECT
        t.*,
        c.name AS customer_name,
        FormatTransactionID(t.transaction_id) AS trx_label,
        IsTransactionPaid(t.transaction_id) AS is_paid
    FROM transactions t
    JOIN customers c ON t.customer_id = c.customer_id
    ORDER BY t.transaction_date DESC
    LIMIT 5
");
$recent_transactions = $stmtRecent->fetchAll();

include 'includes/header.php';
?>

<div class="header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="text-muted" style="font-size: 0.9rem; margin-top: 0.2rem;">Operational overview & revenue report for AquaWash Laundry</p>
    </div>
    <a href="transactions.php?new=1" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create New Transaction</a>
</div>

<!-- ================= REVENUE REPORT FILTER ================= -->
<div class="card" style="padding: 1.25rem 1.5rem; margin-bottom: 1.75rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        
        <!-- Dropdown Selector -->
        <form method="GET" action="index.php" id="periodForm" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin: 0;">
            <label for="periodSelect" style="font-weight: 700; font-size: 0.95rem; color: var(--gray-800); display: flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                <i class="fa-solid fa-chart-line" style="color: var(--primary-500); font-size: 1.1rem;"></i>
                Revenue Report Filter:
            </label>
            <select name="period" id="periodSelect" class="form-control" style="width: auto; min-width: 220px; padding: 0.5rem 2.5rem 0.5rem 1rem; font-weight: 700; font-size: 0.9rem; border-color: var(--primary-400); background-color: var(--surface);" onchange="handlePeriodChange(this.value)">
                <option value="today" <?= $period == 'today' ? 'selected' : '' ?>>Today</option>
                <option value="yesterday" <?= $period == 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                <option value="2days" <?= $period == '2days' ? 'selected' : '' ?>>2 Days Ago</option>
                <option value="3days" <?= $period == '3days' ? 'selected' : '' ?>>3 Days Ago</option>
                <option value="7days" <?= $period == '7days' ? 'selected' : '' ?>>Last 7 Days (7 Days)</option>
                <option value="1month" <?= $period == '1month' ? 'selected' : '' ?>>Last 1 Month (1 Month)</option>
                <option value="1year" <?= $period == '1year' ? 'selected' : '' ?>>Last 1 Year (1 Year)</option>
                <option value="all" <?= $period == 'all' ? 'selected' : '' ?>>All Time</option>
                <option value="custom" <?= $period == 'custom' ? 'selected' : '' ?>>📅 Custom Date Range...</option>
            </select>
        </form>

        <!-- Quick Access Pills -->
        <div class="period-pills">
            <a href="index.php?period=today" class="pill-item <?= $period == 'today' ? 'active' : '' ?>">Today</a>
            <a href="index.php?period=yesterday" class="pill-item <?= $period == 'yesterday' ? 'active' : '' ?>">Yesterday</a>
            <a href="index.php?period=7days" class="pill-item <?= $period == '7days' ? 'active' : '' ?>">7 Days</a>
            <a href="index.php?period=1month" class="pill-item <?= $period == '1month' ? 'active' : '' ?>">1 Month</a>
            <a href="index.php?period=1year" class="pill-item <?= $period == '1year' ? 'active' : '' ?>">1 Year</a>
        </div>
    </div>

    <!-- Custom Date Filter Form -->
    <div id="customFilterBox" style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px dashed var(--border); display: <?= $period == 'custom' ? 'flex' : 'none' ?>; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <form method="GET" action="index.php" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; margin:0;">
            <input type="hidden" name="period" value="custom">
            <div>
                <label class="form-label" style="font-size: 0.78rem;">From Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>" required style="padding: 0.4rem 0.8rem;">
            </div>
            <div>
                <label class="form-label" style="font-size: 0.78rem;">To Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>" required style="padding: 0.4rem 0.8rem;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-magnifying-glass"></i> Display Report</button>
        </form>
    </div>
</div>

<!-- ================= STAT CARDS ================= -->
<div class="dashboard-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-rupiah-sign"></i></div>
        <div class="stat-content">
            <h3>Total Revenue</h3>
            <p style="color: var(--primary-600);">Rp <?= number_format($total_revenue, 0, ',', '.') ?></p>
            <div style="font-size: 0.78rem; margin-top: 0.2rem; color: var(--gray-500);">
                Period: <strong><?= htmlspecialchars($period_label) ?></strong>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
        <div class="stat-content">
            <h3>Total Transactions</h3>
            <p><?= number_format($total_transactions) ?></p>
            <div style="font-size: 0.78rem; margin-top: 0.2rem; color: var(--gray-500);">
                Average: Rp <?= number_format($avg_revenue, 0, ',', '.') ?>/trx
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-content">
            <h3>Payment Status</h3>
            <p style="font-size: 1.1rem; font-weight: 700;">
                <span style="color: var(--success-text);">Paid: Rp <?= number_format($paid_revenue, 0, ',', '.') ?></span>
            </p>
            <div style="font-size: 0.78rem; margin-top: 0.2rem; color: var(--warning-text); font-weight: 600;">
                Unpaid: Rp <?= number_format($unpaid_revenue, 0, ',', '.') ?>
            </div>
        </div>
    </div>
</div>

<!-- ================= DAILY REVENUE BREAKDOWN ================= -->
<?php if (!empty($daily_data)): ?>
<div class="card" style="margin-bottom: 1.75rem;">
    <div class="header" style="margin-bottom: 1.2rem;">
        <h2 style="font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-chart-column" style="color: var(--primary-500);"></i>
            Daily Revenue Breakdown (<?= htmlspecialchars($period_label) ?>)
        </h2>
    </div>

    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <?php foreach ($daily_data as $row):
            $pct = $max_daily > 0 ? round(($row['daily_rev'] / $max_daily) * 100) : 0;
        ?>
        <div style="display: grid; grid-template-columns: 110px 1fr 140px; align-items: center; gap: 1rem;">
            <div style="font-size: 0.85rem; font-weight: 600; color: var(--gray-700);">
                <?= date('d M Y', strtotime($row['tgl'])) ?>
            </div>
            <div style="background: var(--gray-100); border-radius: var(--radius-pill); height: 18px; overflow: hidden; position: relative;">
                <div style="background: linear-gradient(90deg, var(--primary-400), var(--primary-600)); height: 100%; width: <?= max(5, $pct) ?>%; border-radius: var(--radius-pill); transition: width 0.3s ease;"></div>
            </div>
            <div style="text-align: right; font-size: 0.88rem; font-weight: 700; color: var(--gray-900);">
                Rp <?= number_format($row['daily_rev'], 0, ',', '.') ?>
                <span style="font-size: 0.75rem; font-weight: 400; color: var(--gray-500); display: block;"><?= $row['count_trx'] ?> transactions</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ================= RECENT TRANSACTIONS ================= -->
<div class="card">
    <div class="header" style="margin-bottom: 1rem;">
        <h2 style="font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-clock-rotate-left" style="color: var(--accent-500);"></i>
            Recent Transactions
        </h2>
        <a href="transactions.php" class="btn btn-secondary btn-sm">View All Transactions</a>
    </div>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>TRX ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Order Status</th>
                    <th>Payment</th>
                    <th style="text-align: right;">Total Price</th>
                    <th style="text-align: center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_transactions)): ?>
                    <tr><td colspan="7" style="text-align:center; padding: 2rem; color:var(--gray-500);">No transactions found</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_transactions as $row):
                        $statusClass = strtolower(str_replace(' ', '-', $row['status']));
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['trx_label']) ?></strong></td>
                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td><?= date('d M Y H:i', strtotime($row['transaction_date'])) ?></td>
                        <td><span class="badge badge-<?= $statusClass ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                        <td>
                            <?php if ($row['is_paid']): ?>
                                <span class="badge badge-pay-paid">Paid</span>
                            <?php else: ?>
                                <span class="badge badge-pay-pending">Unpaid</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; font-weight: 700;">Rp <?= number_format($row['total_price'], 0, ',', '.') ?></td>
                        <td style="text-align: center;">
                            <a href="transaction_detail.php?id=<?= $row['transaction_id'] ?>" class="btn btn-secondary btn-sm" title="View Details"><i class="fa-solid fa-eye"></i> Details</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function handlePeriodChange(val) {
    if (val === 'custom') {
        document.getElementById('customFilterBox').style.display = 'flex';
    } else {
        document.getElementById('customFilterBox').style.display = 'none';
        document.getElementById('periodForm').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
