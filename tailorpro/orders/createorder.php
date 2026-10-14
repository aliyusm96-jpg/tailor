<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch customers belonging to the logged-in tailor so the dropdown displays names
$stmt_cust = $pdo->prepare("SELECT id, name FROM customers WHERE tailor_id = ? ORDER BY name ASC");
$stmt_cust->execute([$_SESSION['user_id']]);
$customers = $stmt_cust->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $design = isset($_POST['design']) ? trim($_POST['design']) : '';
    $fabric_type = isset($_POST['fabric_type']) ? trim($_POST['fabric_type']) : '';
    $fabric_quantity = isset($_POST['fabric_quantity']) ? $_POST['fabric_quantity'] : 0;
    $color = isset($_POST['color']) ? trim($_POST['color']) : '';
    $special_instructions = isset($_POST['special_instructions']) ? trim($_POST['special_instructions']) : '';
    $order_date = isset($_POST['order_date']) ? $_POST['order_date'] : date('Y-m-d');
    $collection_date = isset($_POST['collection_date']) ? $_POST['collection_date'] : '';
    $amount_charged = isset($_POST['amount_charged']) ? $_POST['amount_charged'] : 0;
    $amount_paid = isset($_POST['amount_paid']) ? $_POST['amount_paid'] : 0;
    $status = 'In progress';

    if ($customer_id <= 0) {
        $error = "Please select a customer.";
    } elseif (empty($collection_date) || empty($fabric_type)) {
        $error = "Please fill in all required order fields (Customer, Fabric Type, Collection Date).";
    } else {
        $stmt_check = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND tailor_id = ?");
        $stmt_check->execute([$customer_id, $_SESSION['user_id']]);
        if (!$stmt_check->fetch()) {
            $error = "Invalid customer selected.";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO orders (tailor_id, customer_id, design, fabric_type, fabric_quantity, color, special_instructions, date_submitted, collection_date, amount_charged, amount_paid, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'], $customer_id, $design, 
                $fabric_type, $fabric_quantity, $color, $special_instructions, 
                $order_date, $collection_date, $amount_charged, $amount_paid, $status
            ]);

            header("Location: /tailorpro/orders/index.php");
            exit;
        }
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

<div style="max-width: 900px; margin: 0 auto;">
    <div style="background: var(--surface-color); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <h2 style="margin-top: 0; color: var(--primary-color); margin-bottom: 4px; font-size: 1.3rem;">Create Order for Existing Customer</h2>
        <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 20px; font-weight: 500;">Select a customer and fill out order details</div>

        <?php if (!empty($error)): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 12px 14px; border-radius: var(--radius-sm); font-weight: 600; margin-bottom: 20px; font-size: 0.9rem;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" style="background: transparent; padding: 0; border: none; box-shadow: none;">
            <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                <div style="display: flex; flex-direction: column; grid-column: span 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Select Customer: *</label>
                    <select name="customer_id" id="customer_id" class="form-control" required>
                        <option value="">-- Choose Customer --</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['name']) ?> 
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; flex-direction: column;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Design / Style Notes:</label>
                    <input type="text" name="design" class="form-control" value="<?= htmlspecialchars(isset($_POST['design']) ? $_POST['design'] : '') ?>">
                </div>

                <div style="display: flex; flex-direction: column;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Fabric Type: *</label>
                    <input type="text" name="fabric_type" id="fabric_type" class="form-control" required placeholder="Shadda, Ankara, etc.">
                </div>

                <div style="display: flex; flex-direction: column;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Fabric Quantity (Yards): *</label>
                    <input type="text" name="fabric_quantity" class="form-control" required placeholder="e.g., 5">
                </div>

                <div style="display: flex; flex-direction: column;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Color: *</label>
                    <input type="text" name="color" class="form-control" required placeholder="e.g., Navy Blue">
                </div>

                <div style="display: flex; flex-direction: column;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Expected Collection Date: *</label>
                    <input type="date" name="collection_date" id="collection_date" class="form-control" required>
                </div>

                <div style="display: flex; flex-direction: column;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Amount Charged (₦):</label>
                    <input type="number" step="0.01" name="amount_charged" class="form-control" placeholder="0.00">
                </div>

                <div style="display: flex; flex-direction: column;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Amount Paid (₦):</label>
                    <input type="number" step="0.01" name="amount_paid" class="form-control" placeholder="0.00">
                </div>

                <div style="display: flex; flex-direction: column; grid-column: span 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">Special Instructions:</label>
                    <textarea name="special_instructions" class="form-control" placeholder="Extra notes..." style="height: 90px; resize: vertical;"></textarea>
                </div>

                <div style="display: flex; flex-direction: column; grid-column: span 1;">
                    <button type="submit" class="btn" style="background: #10b981; color: white; width: 100%; margin-top: 10px; font-size: 1rem; padding: 12px;" onclick="return validateOrder(event)">Save Order</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function validateOrder(event) {
        const customerId = document.getElementById('customer_id').value;
        const fabric = document.getElementById('fabric_type').value.trim();
        const collectionDate = document.getElementById('collection_date').value;

        if (!customerId || !fabric || !collectionDate) {
            alert("Please select a customer and fill in all required fields (Fabric Type, and Collection Date).");
            event.preventDefault();
            return false;
        }
        return true;
    }
</script>

<?php include('../includes/app_footer.php'); ?>