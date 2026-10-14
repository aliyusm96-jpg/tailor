<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$order_id = $_GET['id'] ?? null;
if (!$order_id) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND tailor_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();
if (!$order) { die("Order not found or access denied."); }

$stmt_cust = $pdo->prepare("SELECT id, name FROM customers WHERE id = ? AND tailor_id = ?");
$stmt_cust->execute([$order['customer_id'], $_SESSION['user_id']]);
$customer = $stmt_cust->fetch();
if (!$customer) { die("Customer not found."); }

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $design = trim($_POST['design']);
    $gender = $_POST['gender'];
    $fabric_type = trim($_POST['fabric_type']);
    $fabric_quantity = $_POST['fabric_quantity'] ?? 0;
    $color = trim($_POST['color']);
    $special_instructions = trim($_POST['special_instructions']);
    $collection_date = $_POST['collection_date'];
    $amount_charged = $_POST['amount_charged'] ?? 0;
    $amount_paid = $_POST['amount_paid'] ?? 0;
    $status = $_POST['status'];

    if (!empty($fabric_type) && !empty($collection_date)) {
        $stmt_update = $pdo->prepare("UPDATE orders SET design = ?, gender = ?, fabric_type = ?, fabric_quantity = ?, color = ?, special_instructions = ?, collection_date = ?, amount_charged = ?, amount_paid = ?, status = ? WHERE id = ? AND tailor_id = ?");
        $stmt_update->execute([$design, $gender, $fabric_type, $fabric_quantity, $color, $special_instructions, $collection_date, $amount_charged, $amount_paid, $status, $order_id, $_SESSION['user_id']]);
        $success = "Order updated successfully!";
        $stmt->execute([$order_id, $_SESSION['user_id']]);
        $order = $stmt->fetch();
    } else {
        $error = "Required fields missing (Fabric Type and Collection Date are required).";
    }
}

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

<div style="max-width: 700px; margin: 0 auto;">
    <div style="background: var(--surface-color); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <h2 style="color: var(--primary-color); margin-top: 0; font-size: 1.3rem; margin-bottom: 20px;">Edit Order #<?= $order['id'] ?></h2>
        
        <?php if ($success): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 12px 14px; border-radius: var(--radius-sm); font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; border: 1px solid #a7f3d0;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 12px 14px; border-radius: var(--radius-sm); font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; border: 1px solid #fecaca;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" style="background: transparent; padding: 0; border: none; box-shadow: none;">
            <div class="form-group">
                <label class="form-group label">Customer Name:</label>
                <div style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); padding: 12px 14px; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: var(--radius-sm); margin-top: 5px; margin-bottom: 15px;"><?= htmlspecialchars($customer['name']) ?></div>
                <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
            </div>

            <div class="form-group">
                <label class="form-group label">Design/Style Notes:</label>
                <input type="text" name="design" class="form-control" value="<?= htmlspecialchars($order['design']) ?>">
            </div>

            <div class="form-group">
                <label class="form-group label">Fabric Type: *</label>
                <input type="text" name="fabric_type" class="form-control" value="<?= htmlspecialchars($order['fabric_type']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-group label">Gender:</label>
                <select name="gender" class="form-control">
                    <option value="Female" <?= $order['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Male" <?= $order['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Other" <?= $order['gender'] == 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-group label">Fabric Quantity (Yards):</label>
                <input type="number" step="1" min="0" name="fabric_quantity" class="form-control" value="<?= htmlspecialchars($order['fabric_quantity']) ?>">
            </div>
            
            <div class="form-group">
                <label class="form-group label">Color:</label>
                <input type="text" name="color" class="form-control" value="<?= htmlspecialchars($order['color']) ?>">
            </div>
            
            <div class="form-group">
                <label class="form-group label">Special Instructions:</label>
                <textarea name="special_instructions" class="form-control" style="height: 90px; resize: vertical;"><?= htmlspecialchars($order['special_instructions']) ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-group label">Collection Date: *</label>
                <input type="date" name="collection_date" class="form-control" value="<?= htmlspecialchars($order['collection_date']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-group label">Amount Charged (₦):</label>
                <input type="number" step="0.01" name="amount_charged" class="form-control" value="<?= htmlspecialchars($order['amount_charged']) ?>">
            </div>
            
            <div class="form-group">
                <label class="form-group label">Amount Paid (₦):</label>
                <input type="number" step="0.01" name="amount_paid" class="form-control" value="<?= htmlspecialchars($order['amount_paid']) ?>">
            </div>
            
            <div class="form-group">
                <label class="form-group label">Status:</label>
                <select name="status" class="form-control">
                    <?php foreach (['In progress', 'Pending', 'Ready', 'Collected', 'Cancelled'] as $st): ?>
                        <option value="<?= $st ?>" <?= $order['status'] == $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Update Order</button>
        </form>
    </div>

   
</div>

<?php include('../includes/app_footer.php'); ?>