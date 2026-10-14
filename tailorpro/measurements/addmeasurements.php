<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$error = '';

// Get customer ID from GET request or session fallback[cite: 8]
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : (isset($_SESSION['created_customer_id']) ?$_SESSION['created_customer_id'] : 0);

if ($customer_id <= 0) {
    header("Location: /tailorpro/customers/addingcustomer.php");
    exit;
}

// Fetch customer details to display their name[cite: 8]
$stmt_c =$pdo->prepare("SELECT * FROM customers WHERE id = ? AND tailor_id = ?");
$stmt_c->execute([$customer_id,$_SESSION['user_id']]);
$customer =$stmt_c->fetch();

if (!$customer) {
    die("Customer not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {$action = isset($_POST['action']) ?$_POST['action'] : 'next';

    // If user clicks skip, bypass saving measurements and go straight to order page[cite: 8]
    if ($action === 'skip') {
        header("Location: /tailorpro/orders/addorder.php");
        exit;
    }

    $names = isset($_POST['measurement_name']) ? $_POST['measurement_name'] : [];$values = isset($_POST['measurement_value']) ?$_POST['measurement_value'] : [];

    // Strict validation using safe separate conditions[cite: 8]
    if (empty($names)) {$error = "Please add at least one measurement field, or click 'Skip This Step'.";
    } elseif (empty($values)) {$error = "Please add at least one measurement field, or click 'Skip This Step'.";
    } else {
        $has_empty_fields = false;
        for ($i = 0; $i < count($names);$i++) {
            $n_trim = isset($names[$i]) ? trim($names[$i]) : '';$v_trim = isset($values[$i]) ? trim($values[$i]) : '';
            
            if ($n_trim === '' || $v_trim === '') {$has_empty_fields = true;
                break;
            }
        }

        if ($has_empty_fields) {$error = "All measurement name and value fields must be filled out before proceeding!";
        } else {
            // All fields are valid, save to database[cite: 8]
            for ($i = 0; $i < count($names);$i++) {
                $name = trim($names[$i]);$val = trim($values[$i]);
                
                $stmt =$pdo->prepare("INSERT INTO customer_measurements (customer_id, measurement_name, measurement_value) VALUES (?, ?, ?)");
                $stmt->execute([$customer_id, $name,$val]);
            }

            header("Location: /tailorpro/orders/addorder.php");
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

<div style="max-width: 700px; margin: 0 auto;">
    <div style="background: var(--surface-color); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <h2 style="color: var(--primary-color); margin-top: 0; margin-bottom: 4px; font-size: 1.1rem;"><?= htmlspecialchars($customer['name']) ?></h2>
        <div style="font-size: 0.80rem; color: var(--text-muted); margin-bottom: 16px; font-weight: 500;">Step 2 of 3: Customer Measurements (Optional)</div>
        <p style="color: var(--text-muted); font-size: 0.80rem; margin-bottom: 20px;">Type custom measurement names and values, or skip this step to proceed.</p>

        <?php if (!empty($error)): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 12px 14px; border-radius: var(--radius-sm); font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; border: 1px solid #fecaca;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="measurementForm" style="background: transparent; padding: 0; border: none; box-shadow: none;">
            <input type="hidden" name="action" id="formAction" value="next">

            <div id="fieldsContainer">
                <div style="display: flex; gap: 8px; margin-bottom: 12px; align-items: center; width: 100%;">
                    <input type="text" name="measurement_name[]" class="form-control" placeholder="Measurement Name (e.g. Shoulder)" required>
                    <input type="text" name="measurement_value[]" class="form-control" placeholder="Value (e.g. 16)" required>
                    <button type="button" class="btn" style="background: #fee2e2; color: #dc2626; padding: 0; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;" onclick="removeRow(this)">✕</button>
                </div>
                <div style="display: flex; gap: 8px; margin-bottom: 12px; align-items: center; width: 100%;">
                    <input type="text" name="measurement_name[]" class="form-control" placeholder="Measurement Name (e.g. Chest)" required>
                    <input type="text" name="measurement_value[]" class="form-control" placeholder="Value (e.g. 40)" required>
                    <button type="button" class="btn" style="background: #fee2e2; color: #dc2626; padding: 0; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;" onclick="removeRow(this)">✕</button>
                </div>
            </div>

            <button type="button" class="btn" style="background: #e0e7ff; color: var(--primary-color); width: 100%; margin-top: 5px; margin-bottom: 20px;" onclick="addRow()">+ Add Another Measurement</button>

            <div style="display: flex; flex-direction: column-reverse; gap: 10px; @media(min-width: 480px){flex-direction:row;}">
                <button type="button" class="btn" style="background: #e2e8f0; color: var(--text-main); flex: 1;" onclick="submitSkip()">Skip This Step</button>
                <button type="submit" class="btn btn-primary" style="flex: 1;" onclick="return validateForm(event)">Next: Add Order →</button>
            </div>
        </form>
    </div>

    
</div>

<script>
    function addRow() {
        const container = document.getElementById('fieldsContainer');
        const row = document.createElement('div');
        row.style.cssText = "display: flex; gap: 8px; margin-bottom: 12px; align-items: center; width: 100%;";
        row.innerHTML = `
            <input type="text" name="measurement_name[]" class="form-control" placeholder="Measurement Name" required>
            <input type="text" name="measurement_value[]" class="form-control" placeholder="Value" required>
            <button type="button" class="btn" style="background: #fee2e2; color: #dc2626; padding: 0; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;" onclick="removeRow(this)">✕</button>
        `;
        container.appendChild(row);
    }

    function removeRow(btn) {
        const row = btn.parentElement;
        if (document.querySelectorAll('#fieldsContainer > div').length > 1) {
            row.remove();
        } else {
            alert("You must have at least one measurement field, or click Skip.");
        }
    }

    function submitSkip() {
        document.getElementById('formAction').value = 'skip';
        document.querySelectorAll('#fieldsContainer input').forEach(input => input.removeAttribute('required'));
        document.getElementById('measurementForm').submit();
    }

    function validateForm(event) {
        if (document.getElementById('formAction').value === 'skip') {
            return true;
        }
        
        const inputs = document.querySelectorAll('#fieldsContainer input');
        for (let input of inputs) {
            if (!input.value.trim()) {
                alert("Please fill in all measurement fields before proceeding to the next step.");
                input.focus();
                event.preventDefault();
                return false;
            }
        }
        return true;
    }
</script>

<?php include('../includes/app_footer.php'); ?>