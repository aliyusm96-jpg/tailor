<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch all customers and calculate their actual pending balances[cite: 6]
$stmt = $pdo->prepare("
    SELECT c.*, 
           COALESCE(SUM(o.amount_charged - o.amount_paid), 0) AS pending_balance
    FROM customers c
    LEFT JOIN orders o ON c.id = o.customer_id AND o.tailor_id = ?
    WHERE c.tailor_id = ?
    GROUP BY c.id
    ORDER BY c.id DESC
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$customers = $stmt->fetchAll();

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

<div style="max-width: 1100px; margin: 0 auto;">
    <div style="background: var(--surface-color); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <div style="display: flex; flex-direction: column; gap: 15px; margin-bottom: 20px; @media(min-width: 640px){flex-direction:row;justify-content:space-between;align-items:center;}">
            <h2 style="margin: 0; color: var(--primary-color); font-size: 1.5rem;">Customers List</h2>
            <a href="addingcustomer.php" class="btn btn-primary" style="font-size: 0.9rem;"><i class="fa-solid fa-user-plus" style="margin-right: 8px;"></i> Add New Customer</a>
        </div>
        
        <div style="margin-bottom: 20px;">
            <input type="text" id="searchInput" class="form-control" placeholder="Start typing customer name or phone..." autocomplete="off">
        </div>

        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Gender</th>
                        <th>Address</th>
                        <th>Pending Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody">
                    <?php if (count($customers) > 0): ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr onclick="window.location='view.php?id=<?= $customer['id'] ?>'" style="cursor: pointer;">
                            <td data-label="Name"><strong><?= htmlspecialchars($customer['name']) ?></strong></td>
                            <td data-label="Phone"><?= htmlspecialchars($customer['phone']) ?></td>
                            <td data-label="Gender"><?= htmlspecialchars($customer['gender']) ?></td>
                            <td data-label="Address"><?= htmlspecialchars($customer['address']) ?: 'N/A' ?></td>
                            <td data-label="Pending Balance">
                                <?php if ($customer['pending_balance'] > 0): ?>
                                    <span style="color: #dc2626; font-weight: 600;">₦<?= number_format($customer['pending_balance'], 2) ?></span>
                                <?php else: ?>
                                    <span style="color: #059669; font-weight: 600;">₦0.00</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions" onclick="event.stopPropagation();">
				<div style="display: flex; gap: 10px; align-items: center;">
                                <a href="edit.php?id=<?= $customer['id'] ?>" style="color: #d97706; text-decoration: none; font-weight: 600;">Edit</a>
                                <a href="delete.php?id=<?= $customer['id'] ?>" style="color: #ef4444; text-decoration: none; font-weight: 600;" onclick="return confirm('Are you sure you want to delete this customer?');">Delete</a>
				</div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="noCustomersRow"><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">No customers found. Click "+ Add New Customer" to get started.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
   
</div>

<script>
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('customersTableBody');
    const rows = tableBody.getElementsByTagName('tr');

    searchInput.addEventListener('input', function() {
        const filter = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        for (let i = 0; i < rows.length; i++) {
            if (rows[i].id === 'noCustomersRow') continue;

            const text = rows[i].textContent.toLowerCase();
            if (text.includes(filter)) {
                rows[i].style.display = "";
                visibleCount++;
            } else {
                rows[i].style.display = "none";
            }
        }

        let emptyRow = document.getElementById('dynamicEmptyRow');
        if (visibleCount === 0 && filter !== "") {
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.id = 'dynamicEmptyRow';
                emptyRow.innerHTML = '<td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">No matching customers found.</td>';
                tableBody.appendChild(emptyRow);
            } else {
                emptyRow.style.display = "";
            }
        } else if (emptyRow) {
            emptyRow.style.display = "none";
        }
    });
</script>

<?php include('../includes/app_footer.php'); ?>