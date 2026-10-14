<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$customer_id = $_GET['id'] ?? null;
if (!$customer_id) {
    header("Location: index.php");
    exit;
}

// Fetch customer details securely[cite: 3]
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND tailor_id = ?");
$stmt->execute([$customer_id, $_SESSION['user_id']]);
$customer = $stmt->fetch();

if (!$customer) {
    die("Customer not found or access denied.");
}

// Fetch dynamic customer measurements[cite: 3]
$stmt_m = $pdo->prepare("SELECT * FROM customer_measurements WHERE customer_id = ? ORDER BY id ASC");
$stmt_m->execute([$customer_id]);
$measurements = $stmt_m->fetchAll();

// Fetch customer order history[cite: 3]
$stmt_o = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? AND tailor_id = ? ORDER BY collection_date DESC");
$stmt_o->execute([$customer_id, $_SESSION['user_id']]);
$orders = $stmt_o->fetchAll();

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

<div style="max-width: 900px; margin: 0 auto;">
    <!-- Customer Header Card -->
    <div style="background: var(--surface-color); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 24px;">
        <h2 style="color: var(--primary-color); margin-top: 0; font-size: 1.5rem; margin-bottom: 12px;"><?= htmlspecialchars($customer['name']) ?></h2>
        <div style="color: var(--text-main); font-size: 0.95rem; line-height: 1.6;">
            <p><strong>Phone:</strong> <?= htmlspecialchars($customer['phone']) ?> &nbsp;|&nbsp; <strong>Gender:</strong> <?= htmlspecialchars($customer['gender']) ?></p>
            <p><strong>Address:</strong> <?= htmlspecialchars($customer['address']) ?: 'N/A' ?></p>
            <p><strong>Notes:</strong> <?= htmlspecialchars($customer['notes']) ?: 'None' ?></p>
        </div>
        <div style="margin-top: 16px;">
            <a href="edit.php?id=<?= $customer['id'] ?>" class="btn" style="background: #e2e8f0; color: var(--text-main); font-size: 0.85rem; padding: 6px 14px;"><i class="fa-solid fa-pen" style="margin-right: 6px;"></i> Edit Profile</a>
        </div>
    </div>

    <!-- Measurements Section -->
    <div style="background: var(--surface-color); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 24px;">
        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px; justify-content: space-between; align-items: flex-start; @media(min-width: 600px){flex-direction:row;align-items:center;}">
            <h3 style="font-size: 1.1rem; margin: 0; color: var(--text-main);">Customer Measurements</h3>
            <a href="../measurements/manage.php?customer_id=<?= $customer['id'] ?>" class="btn btn-primary" style="font-size: 0.85rem; padding: 8px 14px;">Update Measurements</a>
        </div>

        <?php if (count($measurements) > 0): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; background: var(--bg-color); padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <?php foreach ($measurements as $m): ?>
                    <div style="font-size: 0.9rem; background: var(--surface-color); padding: 10px 14px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: var(--text-muted); font-weight: 500;"><?= htmlspecialchars($m['measurement_name']) ?></span>
                        <span style="color: var(--text-main); font-weight: 700;"><?= htmlspecialchars($m['measurement_value']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted); font-size: 0.9rem;">No measurements recorded for this customer yet.</p>
        <?php endif; ?>
    </div>

    <!-- Order History Section -->
    <div style="background: var(--surface-color); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 24px;">
        <h3 style="font-size: 1.1rem; margin-bottom: 16px; color: var(--text-main);">Order History</h3>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Quantity</th>
                        <th>Fabric</th>
                        <th>Collection Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td data-label="Quantity"><?= htmlspecialchars($order['fabric_quantity']) ?> Yards</td>
                            <td data-label="Fabric"><?= htmlspecialchars($order['fabric_type']) ?></td>
                            <td data-label="Collection Date"><?= htmlspecialchars($order['collection_date']) ?></td>
                            <td data-label="Status"><strong><?= htmlspecialchars($order['status']) ?></strong></td>
                            <td data-label="Action"><a href="../orders/view.php?id=<?= $order['id'] ?>" class="btn" style="padding: 4px 10px; font-size: 0.85rem; background: #e0e7ff; color: var(--primary-color);">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="color: var(--text-muted); text-align: center; padding: 20px;">No orders found for this customer.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p><a href="index.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem; font-weight: 500;"><i class="fa-solid fa-arrow-left" style="margin-right: 6px;"></i> Back to Customers List</a></p>
</div>

<?php include('../includes/app_footer.php'); ?>