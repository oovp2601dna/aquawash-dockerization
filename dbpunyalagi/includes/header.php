<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AquaWash Management</title>
    <link rel="stylesheet" href="index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<aside class="sidebar">
    <a href="index.php" class="sidebar-logo">
        <span class="logo-icon"><i class="fa-solid fa-soap"></i></span>
        <span>AquaWash</span>
    </a>

    <ul class="nav-links">
        <li><a href="index.php" class="nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-house"></i> <span>Dashboard</span>
        </a></li>
        <li><a href="customers.php" class="nav-item <?= in_array($current_page, ['customers.php','customer_form.php']) ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i> <span>Customers</span>
        </a></li>
        <li><a href="services.php" class="nav-item <?= in_array($current_page, ['services.php','service_form.php']) ? 'active' : '' ?>">
            <i class="fa-solid fa-list-check"></i> <span>Services</span>
        </a></li>
        <li><a href="transactions.php" class="nav-item <?= in_array($current_page, ['transactions.php','transaction_form.php','transaction_detail.php']) ? 'active' : '' ?>">
            <i class="fa-solid fa-receipt"></i> <span>Transactions</span>
        </a></li>
        <li><a href="payments.php" class="nav-item <?= in_array($current_page, ['payments.php','payment_form.php']) ? 'active' : '' ?>">
            <i class="fa-solid fa-credit-card"></i> <span>Payments</span>
        </a></li>
    </ul>
</aside>

<main class="main-content">