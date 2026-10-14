<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$error = '';$success = '';

// Get customer ID from GET request or session fallback[cite: 7]
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : (isset($_SESSION['created_customer_id']) ?$_SESSION['created_customer_id'] : 0);

if ($customer_id <= 0) {
    header("Location: /tailorpro/customers/index.php");
    exit;
}

// Fetch customer details to display their name[cite: 7]
$stmt_c =$pdo->prepare("SELECT * FROM customers WHERE id = ? AND tailor_id = ?");
$stmt_c->execute([$customer_id,$_SESSION['user_id']]);
$customer =$stmt_c->fetch();

if (!$customer) {
    die("Customer not found.");
}

// Fetch existing measurements for this customer (if any)[cite: 7]
$stmt_exist =$pdo->prepare("SELECT measurement_name, measurement_value FROM customer_measurements WHERE customer_id = ?");
$stmt_exist->execute([$customer_id]);
$existing_measurements =$stmt_exist->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $names = isset($_POST['measurement_name']) ? $_POST['measurement_name'] : [];$values = isset($_POST['measurement_value']) ?$_POST['measurement_value'] : [];

    // Validation[cite: 7]
    if (empty($names) || empty($values)) {$error = "Please add at least one measurement field.";
    } else {
        $has_empty_fields = false;
        for ($i = 0; $i < count($names);$i++) {
            $n_trim = isset($names[$i]) ? trim($names[$i]) : '';$v_trim = isset($values[$i]) ? trim($values[$i]) : '';
            if ($n_trim === '' || $v_trim === '') {$has_empty_fields = true;
                break;
            }
        }

        if ($has_empty_fields) {$error = "All measurement name and value fields must be filled out before saving!";
        } else {
            try {
                $pdo->beginTransaction();

                // Clear out old measurements for this customer so updated/deleted rows sync correctly[cite: 7]
                $stmt_del =$pdo->prepare("DELETE FROM customer_measurements WHERE customer_id = ?");
                $stmt_del->execute([$customer_id]);

                // Insert all current dynamic rows[cite: 7]
                for ($i = 0; $i < count($names);$i++) {
                    $name = trim($names[$i]);$val = trim($values[$i]);
                    if ($name !== '' &&$val !== '') {
                        $stmt_ins =$pdo->prepare("INSERT INTO customer_measurements (customer_id, measurement_name, measurement_value) VALUES (?, ?, ?)");
                        $stmt_ins->execute([$customer_id, $name,$val]);
                    }
                }

                $pdo->commit();

                // If coming from customer creation wizard, proceed to order, otherwise redirect to customer view[cite: 7]
                if (isset($_SESSION['created_customer_id'])) {
                    unset($_SESSION['created_customer_id']);
                    header("Location: /tailorpro/orders/addorder.php");
                    exit;
                } else {
                    header("Location: ../customers/view.php?id=" . $customer_id);
                    exit;
                }

            } catch (Exception $e) {
                $pdo->rollBack();$error = "Failed to save measurements: " . $e->getMessage();
            }
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
        <h2 style="color: var(--text-main); margin-top: 0; margin-bottom: 4px; font-size: 1.1rem;">Measurements for: <span style="color: var(--primary-color);"><?= htmlspecialchars($customer['name']) ?></span></h2>
        <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px; font-weight: 500;"><?= isset($_SESSION['created_customer_id']) ? 'Step 2 of 3: Customer Measurements' : 'Manage Customer Measurements' ?></div>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Add, update, or remove custom measurement names and values.</p>

        <?php if (!empty($error)): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 12px 14px; border-radius: var(--radius-sm); font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; border: 1px solid #fecaca;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 12px 14px; border-radius: var(--radius-sm); font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; border: 1px solid #a7f3d0;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="measurementForm" style="background: transparent; padding: 0; border: none; box-shadow: none;">
            <div id="fieldsContainer">
                <?php if (!empty($existing_measurements)): ?>
                    <?php foreach ($existing_measurements as$m): ?>
                        <div style="display: flex; gap: 8px; margin-bottom: 12px; align-items: center; width: 100%;">
                            <input type="text" name="measurement_name[]" class="form-control" value="<?= htmlspecialchars($m['measurement_name']) ?>" placeholder="(e.g. Shoulder)" required>
                            <input type="text" name="measurement_value[]" class="form-control" value="<?= htmlspecialchars($m['measurement_value']) ?>" placeholder="Value (e.g. 16)" required>
                            <button type="button" class="btn" style="background: #fee2e2; color: #dc2626; padding: 0; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;" onclick="removeRow(this)">✕</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="display: flex; gap: 8px; margin-bottom: 12px; align-items: center; width: 100%;">
                        <input type="text" name="measurement_name[]" class="form-control" placeholder="(e.g. Shoulder)" required>
                        <input type="text" name="measurement_value[]" class="form-control" placeholder="Value (e.g. 16)" required>
                        <button type="button" class="btn" style="background: #fee2e2; color: #dc2626; padding: 0; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;" onclick="removeRow(this)">✕</button>
                    </div>
                    <div style="display: flex; gap: 8px; margin-bottom: 12px; align-items: center; width: 100%;">
                        <input type="text" name="measurement_name[]" class="form-control" placeholder="(e.g. Chest)" required>
                        <input type="text" name="measurement_value[]" class="form-control" placeholder="Value (e.g. 40)" required>
                        <button type="button" class="btn" style="background: #fee2e2; color: #dc2626; padding: 0; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;" onclick="removeRow(this)">✕</button>
                    </div>
                <?php endif; ?>
            </div>

            <button type="button" class="btn" style="background: #e0e7ff; color: var(--primary-color); width: 100%; margin-top: 5px; margin-bottom: 20px;" onclick="addRow()">+ Add Another Measurement</button>

            <button type="submit" class="btn btn-primary" style="width: 100%;" onclick="return validateForm(event)"><?= isset($_SESSION['created_customer_id']) ? 'Next: Add Order →' : 'Save Measurements' ?></button>
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
            alert("You must have at least one measurement field.");
        }
    }

    function validateForm(event) {
        const inputs = document.querySelectorAll('#fieldsContainer input');
        for (let input of inputs) {
            if (!input.value.trim()) {
                alert("Please fill in all measurement fields before saving.");
                input.focus();
                event.preventDefault();
                return false;
            }
        }
        return true;
    }
</script>

<?php include('../includes/app_footer.php'); ?>