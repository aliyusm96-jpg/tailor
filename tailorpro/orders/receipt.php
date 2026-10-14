<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    header("Location: /tailorpro/orders/index.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT o.*, c.name as customer_name, c.phone as customer_phone, c.gender as customer_gender, 
           u.business_name, u.phone as tailor_phone, u.address as tailor_address
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    JOIN users u ON o.tailor_id = u.id
    WHERE o.id = ? AND o.tailor_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$receipt = $stmt->fetch();

if (!$receipt) {
    die("Receipt not found or access denied.");
}

$balance = $receipt['amount_charged'] - $receipt['amount_paid'];

// Fetch pending orders and build notifications array (matching dashboard.php logic)
$today = date('Y-m-d');
$stmt_orders = $pdo->prepare("
    SELECT o.*, c.name as customer_name, c.phone as customer_phone 
    FROM orders o 
    JOIN customers c ON o.customer_id = c.id 
    WHERE o.tailor_id = ? AND o.status NOT IN ('Collected', 'Cancelled')
    ORDER BY o.collection_date ASC
");
$stmt_orders->execute([$_SESSION['user_id']]);
$all_pending_orders = $stmt_orders->fetchAll();

$notifications = [];
foreach ($all_pending_orders as $row) {
    $diff = strtotime($row['collection_date']) - strtotime($today);
    $days_remaining = floor($diff / (60 * 60 * 24));
    
    if ($row['collection_date'] == $today) {
        $notifications[] = ['text' => "🔴 <strong>{$row['customer_name']}</strong> ({$row['clothing_type']}) is due <strong>Today</strong>!", 'type' => 'due-today'];
    } elseif ($days_remaining == 1) {
        $notifications[] = ['text' => "🟡 <strong>{$row['customer_name']}</strong> collection is <strong>Tomorrow</strong>.", 'type' => 'tomorrow'];
    } elseif ($days_remaining < 0) {
        $days_ago = abs($days_remaining);
        $notifications[] = ['text' => "🔴 <strong>{$row['customer_name']}</strong> order is <strong>Overdue by $days_ago day(s)</strong>!", 'type' => 'overdue'];
    }
}

// This variable will be picked up by app_layout.php to render the badge count
$notification_count = count($notifications);

include('../includes/app_layout.php');
?>

<style>
    .receipt-container {
        width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .receipt-box {
        width: 100%;
        max-width: 420px;
        margin: 10px auto;
        padding: 20px;
        border: 2px dashed var(--border-color);
        background: var(--surface-color);
        border-radius: var(--radius-md);
        box-sizing: border-box;
    }
    @media (min-width: 480px) {
        .receipt-box { padding: 24px; margin: 20px auto; }
    }
    .receipt-header, .receipt-footer {
        text-align: center;
    }
    .print-btn {
        display: block;
        width: 100%;
        padding: 12px;
        background: var(--primary-color);
        color: white;
        text-align: center;
        border: none;
        cursor: pointer;
        margin-top: 20px;
        font-weight: 600;
        border-radius: var(--radius-sm);
    }
    .print-btn:hover { background: var(--primary-hover); }
    .back-link {
        margin-top: 15px;
        text-align: center;
    }
    .back-link a {
        color: var(--text-muted);
        text-decoration: none;
        font-size: 0.9rem;
    }
    @media print {
        body { background: #fff; padding: 0; }
        .print-btn, .back-link, .app-header, .app-footer, nav { display: none !important; }
        .receipt-box { border: none; margin: 0; width: 100%; max-width: 100%; padding: 0; box-shadow: none; }
    }
</style>

<div class="receipt-container">
    <div class="receipt-box">
        <div class="receipt-header">
            <h3 style="color: var(--text-main); margin-bottom: 5px;"><?= strtoupper(htmlspecialchars($receipt['business_name'])) ?></h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0;"><?= htmlspecialchars($receipt['tailor_address']) ?><br>Phone: <?= htmlspecialchars($receipt['tailor_phone']) ?></p>
            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 15px 0;">
        </div>

        <p style="font-size: 0.9rem; margin: 6px 0;"><strong>Customer:</strong> <?= htmlspecialchars($receipt['customer_name']) ?></p>
        <p style="font-size: 0.9rem; margin: 6px 0;"><strong>Phone:</strong> <?= htmlspecialchars($receipt['customer_phone']) ?></p>
        <p style="font-size: 0.9rem; margin: 6px 0;"><strong>Gender:</strong> <?= htmlspecialchars($receipt['customer_gender'] ?? 'N/A') ?></p>
        <p style="font-size: 0.9rem; margin: 6px 0;"><strong>Fabric:</strong> <?= htmlspecialchars($receipt['fabric_type']) ?> - <?= htmlspecialchars($receipt['fabric_quantity']) ?> yds</p>
        <p style="font-size: 0.9rem; margin: 6px 0;"><strong>Date Submitted:</strong> <?= htmlspecialchars($receipt['date_submitted']) ?></p>
        <p style="font-size: 0.9rem; margin: 6px 0;"><strong>Collection Date:</strong> <?= htmlspecialchars($receipt['collection_date']) ?></p>
        
        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 15px 0;">
        <p style="font-size: 0.9rem; margin: 6px 0;"><strong>Amount Charged:</strong> ₦<?= number_format($receipt['amount_charged'], 2) ?></p>
        <p style="font-size: 0.9rem; margin: 6px 0;"><strong>Amount Paid:</strong> ₦<?= number_format($receipt['amount_paid'], 2) ?></p>
        <p style="font-size: 0.9rem; margin: 6px 0;"><strong>Balance:</strong> <span style="color: #dc2626; font-weight: bold;">₦<?= number_format($balance, 2) ?></span></p>
        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 15px 0;">

        <div class="receipt-footer">
            <p style="font-size: 0.85rem; color: var(--text-muted);"><em>Thank you for your patronage!</em></p>
        </div>

        <button class="print-btn" onclick="window.print()">Print Receipt</button>
        
       
    </div>
</div>

