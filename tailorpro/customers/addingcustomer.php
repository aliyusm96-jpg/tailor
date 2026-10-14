<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();


$error = '';
$name_val = '';
$phone_val = '';
$gender_val = '';
$address_val = '';
$notes_val = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_val = isset($_POST['name']) ? trim($_POST['name']) : '';
    $phone_val = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $gender_val = isset($_POST['gender']) ? trim($_POST['gender']) : '';
    $address_val = isset($_POST['address']) ? trim($_POST['address']) : '';
    $notes_val = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if (!empty($name_val) && !empty($phone_val) && !empty($gender_val) && !empty($address_val)) {
        $stmt_check = $pdo->prepare("SELECT id FROM customers WHERE tailor_id = ? AND (name = ? OR phone = ?)");
        $stmt_check->execute([$_SESSION['user_id'], $name_val, $phone_val]);
        $existing_customer = $stmt_check->fetch();

        if ($existing_customer) {
            $error = "A customer with this name or phone number already exists!";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO customers (tailor_id, name, phone, gender, address, notes) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $name_val, $phone_val, $gender_val, $address_val, $notes_val]);
            
            $_SESSION['created_customer_id'] = $pdo->lastInsertId();
            
            header("Location: /tailorpro/measurements/addmeasurements.php?customer_id=" . $_SESSION['created_customer_id']);
            exit;
        }
    } else {
        $error = "Please fill in all required fields (Name, Phone, Gender, Address).";
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
        <h2 style="margin-top: 0; color: var(--primary-color); font-size: 1.5rem; margin-bottom: 4px;">Add New Customer</h2>
        <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px; font-weight: 500;">Step 1 of 3: Customer Information</div>
        
        <?php if (!empty($error)): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 12px 14px; border-radius: var(--radius-sm); font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; border: 1px solid #fecaca;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" style="background: transparent; padding: 0; border: none; box-shadow: none;">
            <div class="form-group">
                <label class="form-group label">Customer Full Name: *</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($name_val) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-group label">Phone Number: *</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($phone_val) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-group label">Gender: *</label>
                <select name="gender" class="form-control" required>
                    <option value="">-- Select Gender --</option>
                    <option value="Male" <?= ($gender_val == 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($gender_val == 'Female') ? 'selected' : '' ?>>Female</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-group label">Address: *</label>
                <textarea name="address" class="form-control" placeholder="Customer residential address..." style="height: 90px; resize: vertical;" required><?= htmlspecialchars($address_val) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-group label">Notes / Preferences (optional):</label>
                <textarea name="notes" class="form-control" placeholder="Special body traits or fabric preferences..." style="height: 90px; resize: vertical;"><?= htmlspecialchars($notes_val) ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Next &rarr;</button>
        </form>
    </div>

    <div style="margin-top: 20px;">

    </div>
</div>

<?php include('../includes/app_footer.php'); ?>