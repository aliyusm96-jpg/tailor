<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$customer_id = $_GET['id'] ?? null;
if (!$customer_id) { header("Location: index.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND tailor_id = ?");
$stmt->execute([$customer_id, $_SESSION['user_id']]);
$customer = $stmt->fetch();
if (!$customer) { die("Customer not found or access denied."); }

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $gender = $_POST['gender'];
    $address = trim($_POST['address']);
    $notes = trim($_POST['notes']);

    if (!empty($name) && !empty($phone)) {
        $stmt_update = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, gender = ?, address = ?, notes = ? WHERE id = ? AND tailor_id = ?");
        $stmt_update->execute([$name, $phone, $gender, $address, $notes, $customer_id, $_SESSION['user_id']]);
        $success = "Customer updated successfully!";
        $stmt->execute([$customer_id, $_SESSION['user_id']]);
        $customer = $stmt->fetch();
    } else {
        $error = "Name and Phone are required.";
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

<div style="max-width: 650px; margin: 0 auto;">
    <div style="background: var(--surface-color); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <h2 style="color: var(--primary-color); margin-top: 0; font-size: 1.5rem; margin-bottom: 20px;">Edit Customer Profile</h2>
        
        <?php if ($success): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 12px 14px; border-radius: var(--radius-sm); font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; border: 1px solid #a7f3d0;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 12px 14px; border-radius: var(--radius-sm); font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; border: 1px solid #fecaca;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" style="background: transparent; padding: 0; border: none; box-shadow: none;">
            <div class="form-group">
                <label class="form-group label">Name:</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($customer['name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-group label">Phone:</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($customer['phone']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-group label">Gender:</label>
                <select name="gender" class="form-control">
                    <option value="Male" <?= $customer['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $customer['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= $customer['gender'] == 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-group label">Address:</label>
                <textarea name="address" class="form-control" style="height: 90px; resize: vertical;"><?= htmlspecialchars($customer['address']) ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-group label">Notes:</label>
                <textarea name="notes" class="form-control" style="height: 90px; resize: vertical;"><?= htmlspecialchars($customer['notes']) ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Update Customer</button>
        </form>
    </div>
    
    <div style="margin-top: 20px;">
        <a href="view.php?id=<?= $customer['id'] ?>" style="text-decoration: none; color: var(--text-muted); font-size: 0.9rem; font-weight: 500;"><i class="fa-solid fa-arrow-left" style="margin-right: 6px;"></i> Back to Profile</a>
    </div>
</div>

<?php include('../includes/app_footer.php'); ?>