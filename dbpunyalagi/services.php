<?php
require_once 'database.php';

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM services WHERE service_id = ?");
    $stmt->execute([$id]);
    header("Location: services.php?msg=deleted");
    exit;
}

include 'includes/header.php';
?>

<div class="header">
    <div>
        <h1 class="page-title">Services</h1>
        <p class="text-muted" style="font-size: 0.9rem; margin-top: 0.2rem;">Manage laundry service packages, unit prices, and billing units</p>
    </div>
    <a href="service_form.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Service</a>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
<div class="alert-toast">
    <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
    <span>Laundry service package successfully deleted.</span>
</div>
<?php endif; ?>

<div class="card">
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Service Name</th>
                    <th>Price Per Unit</th>
                    <th>Unit Type</th>
                    <th style="width: 150px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->query("SELECT * FROM services ORDER BY service_id DESC");
                while ($row = $stmt->fetch()) {
                    echo "<tr>";
                    echo "<td><strong>".htmlspecialchars($row['name'])."</strong></td>";
                    echo "<td style='font-weight: 700; color: var(--primary-600);'>Rp ".number_format($row['price_per_unit'], 0, ',', '.')."</td>";
                    echo "<td><span class='badge badge-washing'>".htmlspecialchars($row['unit_type'])."</span></td>";
                    echo "<td style='text-align: right;'>
                            <a href='service_form.php?id={$row['service_id']}' class='btn btn-secondary btn-sm' title='Edit'><i class='fa-solid fa-pen'></i> Edit</a>
                            <a href='services.php?delete={$row['service_id']}' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure you want to delete this service?\");' title='Delete'><i class='fa-solid fa-trash'></i></a>
                          </td>";
                    echo "</tr>";
                }
                if ($stmt->rowCount() == 0) {
                    echo "<tr><td colspan='4' style='text-align:center; padding: 2rem; color: var(--gray-500);'>No services found</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
