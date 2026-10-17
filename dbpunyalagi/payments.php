<?php
// Unified workspace redirect to Transactions & Payments Hub (Tab: Riwayat Pembayaran)
$_GET['tab'] = $_GET['tab'] ?? 'payments';
require_once 'transactions.php';
