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
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT o.*, c.name as customer_name, c.phone as customer_phone, c.gender as customer_gender, c.address as customer_address 
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    WHERE o.id = ? AND o.tailor_id = ?
");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    die("Order not found or access denied.");
}

$balance = $order['amount_charged'] - $order['amount_paid'];

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

<div style="max-width: 800px; margin: 0 auto;">
    <div style="background: var(--surface-color); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <div style="display: flex; flex-direction: column; gap: 10px; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px; @media(min-width: 480px){flex-direction:row;align-items:center;}">
            <h2 style="margin: 0; color: var(--primary-color); font-size: 1.3rem;">Order #<?= $order['id'] ?> Details</h2>
            <span style="display: inline-block; padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.75rem; background: #e0e7ff; color: var(--primary-color);"><?= htmlspecialchars($order['status']) ?></span>
        </div>

        <div style="display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 25px; @media(min-width: 640px){grid-template-columns: 1fr 1fr; gap: 20px;}">
            <div style="background: var(--bg-color); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <h4 style="margin-top: 0; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Customer Profile</h4>
                <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Name:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
                <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Gender:</strong> <?= htmlspecialchars($order['customer_gender']) ?></p>
                <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Address:</strong> <?= htmlspecialchars($order['customer_address']) ?: 'N/A' ?></p>
            </div>

            <div style="background: var(--bg-color); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <h4 style="margin-top: 0; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Clothing & Fabric Specs</h4>
                <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Quantity:</strong> <?= htmlspecialchars($order['fabric_quantity']) ?> Yards </p>
                <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Design/Style:</strong> <?= htmlspecialchars($order['design']) ?: 'Not specified.' ?></p>
                <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Fabric Type:</strong> <?= htmlspecialchars($order['fabric_type']) ?></p>
                <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Color:</strong> <?= htmlspecialchars($order['color']) ?: 'N/A' ?></p>
            </div>
        </div>

        <div style="background: var(--bg-color); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 25px;">
            <h4 style="margin-top: 0; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Schedule & Instructions</h4>
            <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Date Submitted:</strong> <?= htmlspecialchars($order['date_submitted']) ?></p>
            <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Expected Collection Date:</strong> <?= htmlspecialchars($order['collection_date']) ?></p>
            <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Special Instructions:</strong> <?= htmlspecialchars($order['special_instructions']) ?: 'Not specified.' ?></p>
        </div>

        <div style="background: #fdf4f4; padding: 16px; border-radius: var(--radius-md); border: 1px solid #fecaca; margin-bottom: 25px;">
            <h4 style="margin-top: 0; color: #dc2626; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">Financial Summary</h4>
            <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Amount Charged:</strong> ₦<?= number_format($order['amount_charged'], 0) ?></p>
            <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Amount Paid:</strong> ₦<?= number_format($order['amount_paid'], 0) ?></p>
            <p style="margin: 8px 0; font-size: 0.9rem;"><strong>Remaining Balance:</strong> <span style="color: #dc2626; font-weight: bold;">₦<?= number_format($balance, 0) ?></span></p>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 25px; @media(min-width: 540px){flex-direction:row;flex-wrap:wrap;}">
            <a href="receipt.php?id=<?= $order['id'] ?>" class="btn btn-primary">🖨️ Print Receipt</a>
            <a href="edit.php?id=<?= $order['id'] ?>" class="btn" style="background: #e2e8f0; color: var(--text-main);">✏️ Edit Order</a>
            
        </div>
    </div>
</div>

<?php include('../includes/app_footer.php'); ?>