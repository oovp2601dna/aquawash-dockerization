<?php
require_once 'database.php';

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM customers WHERE customer_id = ?");
    $stmt->execute([$id]);
    header("Location: customers.php?msg=deleted");
    exit;
}

include 'includes/header.php';
?>

<div class="header">
    <div>
        <h1 class="page-title">Customer Data</h1>
        <p class="text-muted" style="font-size: 0.9rem; margin-top: 0.2rem;">Manage customer records, linked transaction histories, and contact information</p>
    </div>
    <a href="customer_form.php" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Add Customer</a>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
<div class="alert-toast">
    <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
    <span>Customer record successfully deleted.</span>
</div>
<?php endif; ?>

<div class="card">
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Phone Number</th>
                    <th>Address</th>
                    <th>Linked Transactions (TRX IDs)</th>
                    <th>Registered Date</th>
                    <th style="width: 140px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("
                    SELECT
                        c.*,
                        COUNT(t.transaction_id) AS total_trx,
                        COALESCE(SUM(t.total_price), 0) AS total_spent,
                        GROUP_CONCAT(
                            CONCAT(FormatTransactionID(t.transaction_id), ':', t.transaction_id)
                            ORDER BY t.transaction_id DESC
                            SEPARATOR ','
                        ) AS trx_data
                    FROM customers c
                    LEFT JOIN transactions t ON c.customer_id = t.customer_id
                    GROUP BY c.customer_id
                    ORDER BY c.customer_id DESC
                ");
                
                while ($row = $stmt->fetch()) {
                    echo "<tr>";
                    echo "<td><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
                    echo "<td><i class='fa-solid fa-phone' style='color:var(--gray-400); font-size:0.8rem;'></i> " . htmlspecialchars($row['phone']) . "</td>";
                    echo "<td style='max-width:250px; font-size:0.85rem;'>" . htmlspecialchars($row['address']) . "</td>";
                    
                    echo "<td>";
                    if ($row['total_trx'] > 0 && !empty($row['trx_data'])) {
                        echo "<div style='margin-bottom:0.25rem; font-size:0.78rem; font-weight:700; color:var(--primary-600);'>";
                        echo "<i class='fa-solid fa-receipt'></i> {$row['total_trx']} Orders (Total Rp " . number_format($row['total_spent'], 0, ',', '.') . ")";
                        echo "</div>";
                        
                        echo "<div style='display:flex; flex-wrap:wrap; gap:0.25rem;'>";
                        $trx_items = explode(',', $row['trx_data']);
                        foreach ($trx_items as $titem) {
                            $parts = explode(':', $titem);
                            if (count($parts) === 2) {
                                $tlabel = $parts[0];
                                $tid    = $parts[1];
                                echo "<a href='transaction_detail.php?id={$tid}' class='badge badge-washing' style='text-decoration:none;' title='View details for {$tlabel}'>{$tlabel}</a>";
                            }
                        }
                        echo "</div>";
                    } else {
                        echo "<span class='text-muted' style='font-size:0.8rem;'><i class='fa-solid fa-minus'></i> No transactions yet</span>";
                    }
                    echo "</td>";

                    echo "<td style='font-size:0.85rem; color:var(--gray-600);'>" . date('d M Y', strtotime($row['created_at'])) . "</td>";
                    
                    echo "<td style='text-align: right;'>
                            <a href='customer_form.php?id={$row['customer_id']}' class='btn btn-secondary btn-sm' title='Edit'><i class='fa-solid fa-pen'></i> Edit</a>
                            <a href='customers.php?delete={$row['customer_id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure you want to delete this customer?\");' title='Delete'><i class='fa-solid fa-trash'></i></a>
                          </td>";
                    echo "</tr>";
                }
                if ($stmt->rowCount() == 0) {
                    echo "<tr><td colspan='6' style='text-align:center; padding: 2rem; color: var(--gray-500);'>No customer records found</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
