<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!isset($_SESSION['created_customer_id'])) {
    header("Location: /tailorpro/customers/addingcustomer.php");
    exit;
}

$customer_id = $_SESSION['created_customer_id'];

$stmt_c = $pdo->prepare("SELECT name, phone FROM customers WHERE id = ?");
$stmt_c->execute([$customer_id]);
$current_customer = $stmt_c->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'save';

    if ($action === 'skip') {
        unset($_SESSION['created_customer_id']);
        header("Location: /tailorpro/customers/index.php");
        exit;
    }

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

    if (!empty($collection_date) && !empty($fabric_type)) {
        $stmt = $pdo->prepare("
            INSERT INTO orders (tailor_id, customer_id, design, fabric_type, fabric_quantity, color, special_instructions, date_submitted, collection_date, amount_charged, amount_paid, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'], $customer_id, $design, 
            $fabric_type, $fabric_quantity, $color, $special_instructions, 
            $order_date, $collection_date, $amount_charged, $amount_paid, $status
        ]);
        
        unset($_SESSION['created_customer_id']);

        header("Location: /tailorpro/orders/index.php");
        exit;
    } else {
        $error = "Please fill in all required order fields.";
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

<div style="max-width: 900px; margin: 0 auto; width: 100%; box-sizing: border-box;">
    <div style="background: var(--surface-color); padding: 20px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); box-sizing: border-box;">
        <h2 style="margin-top: 0; color: var(--primary); margin-bottom: 5px; font-size: 20px;">Create New Order</h2>
        <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 20px; font-weight: 500;">Step 3 of 3: Order Specifications</div>

        <?php if ($current_customer): ?>
            <div style="background: #e0e7ff; color: #3730a3; padding: 10px 14px; border-radius: 8px; font-weight: 600; margin-bottom: 20px; font-size: 14px; box-sizing: border-box;">
                <strong><?= htmlspecialchars($current_customer['name']) ?> : </strong> <?= htmlspecialchars($current_customer['phone']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; font-weight: 600; margin-bottom: 20px; font-size: 14px; box-sizing: border-box;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="" method="POST" id="orderForm" style="background: transparent; padding: 0; border: none; box-shadow: none; width: 100%; box-sizing: border-box;">
            <input type="hidden" name="action" id="formAction" value="save">

            <div style="display: grid; grid-template-columns: 1fr; gap: 15px; width: 100%; box-sizing: border-box;">
                <div style="display: flex; flex-direction: column; width: 100%; box-sizing: border-box;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; color: var(--text-muted);">Design / Style Notes:</label>
                    <input type="text" name="design" class="form-control" value="<?= htmlspecialchars(isset($_POST['design']) ? $_POST['design'] : '') ?>" style="width: 100%; height: 46px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; box-sizing: border-box; background: #fff;">
                </div>

                <div style="display: flex; flex-direction: column; width: 100%; box-sizing: border-box;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; color: var(--text-muted);">Fabric Type: *</label>
                    <input type="text" name="fabric_type" id="fabric_type" class="form-control" required placeholder="Shadda, Ankara, etc." style="width: 100%; height: 46px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; box-sizing: border-box; background: #fff;">
                </div>

                <div style="display: flex; flex-direction: column; width: 100%; box-sizing: border-box;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; color: var(--text-muted);">Fabric Quantity (Yards):</label>
                    <input type="number" name="fabric_quantity" class="form-control" placeholder="e.g., 5" style="width: 100%; height: 46px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; box-sizing: border-box; background: #fff;">
                </div>

                <div style="display: flex; flex-direction: column; width: 100%; box-sizing: border-box;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; color: var(--text-muted);">Color:</label>
                    <input type="text" name="color" class="form-control" placeholder="e.g., White, Blue" style="width: 100%; height: 46px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; box-sizing: border-box; background: #fff;">
                </div>

                <div style="display: flex; flex-direction: column; width: 100%; box-sizing: border-box;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; color: var(--text-muted);">Expected Collection Date: *</label>
                    <input type="date" name="collection_date" id="collection_date" class="form-control" required style="width: 100%; height: 46px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; box-sizing: border-box; background: #fff;">
                </div>

                <div style="display: flex; flex-direction: column; width: 100%; box-sizing: border-box;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; color: var(--text-muted);">Amount Charged (₦):</label>
                    <input type="number" step="0.01" name="amount_charged" class="form-control" placeholder="0.00" style="width: 100%; height: 46px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; box-sizing: border-box; background: #fff;">
                </div>

                <div style="display: flex; flex-direction: column; width: 100%; box-sizing: border-box;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; color: var(--text-muted);">Amount Paid (₦):</label>
                    <input type="number" step="0.01" name="amount_paid" class="form-control" placeholder="0.00" style="width: 100%; height: 46px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; box-sizing: border-box; background: #fff;">
                </div>

                <div style="display: flex; flex-direction: column; width: 100%; box-sizing: border-box; grid-column: span 1;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; color: var(--text-muted);">Special Instructions:</label>
                    <textarea name="special_instructions" class="form-control" placeholder="Extra notes..." style="width: 100%; height: 80px; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; box-sizing: border-box; background: #fff; resize: vertical;"></textarea>
                </div>

                <div style="display: flex; flex-direction: column; width: 100%; box-sizing: border-box; grid-column: span 1;">
                    <div style="display: flex; flex-direction: column-reverse; gap: 10px; margin-top: 15px; width: 100%; box-sizing: border-box;">
                        <button type="button" style="background: #e2e8f0; color: var(--text-main); border: none; padding: 14px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; flex: 1; font-size: 16px; box-sizing: border-box;" onclick="submitSkip()">Skip & Save</button>
                        <button type="submit" style="background: #10b981; color: white; border: none; padding: 14px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; flex: 1; font-size: 16px; box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2); box-sizing: border-box;" onclick="return validateOrder(event)">Save Order & Complete</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function submitSkip() {
        document.getElementById('formAction').value = 'skip';
        const fabricEl = document.getElementById('fabric_type');
        const dateEl = document.getElementById('collection_date');
        if (fabricEl) fabricEl.removeAttribute('required');
        if (dateEl) dateEl.removeAttribute('required');
        document.getElementById('orderForm').submit();
    }

    function validateOrder(event) {
        if (document.getElementById('formAction').value === 'skip') {
            return true;
        }

        const fabric = document.getElementById('fabric_type').value.trim();
        const collectionDate = document.getElementById('collection_date').value;

        if (!fabric || !collectionDate) {
            alert("Please fill in all required order fields (Fabric Type, and Collection Date) before saving.");
            event.preventDefault();
            return false;
        }
        return true;
    }
</script>

<?php include('../includes/app_footer.php'); ?>