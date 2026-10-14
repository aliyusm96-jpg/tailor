<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch all orders for this tailor upfront so JavaScript can filter them instantly[cite: 16]
$stmt = $pdo->prepare("
    SELECT o.*, c.name AS customer_name 
    FROM orders o
    JOIN customers c ON o.customer_id = c.id
    WHERE o.tailor_id = ? 
    ORDER BY id DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

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
        <div style="display: flex; flex-direction: column; gap: 15px; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; @media(min-width: 540px){flex-direction:row;align-items:center;}">
            <h2 style="margin: 0; color: var(--primary-color); font-size: 1.5rem;">Orders Management</h2>
            <a href="createorder.php" class="btn btn-primary" style="font-size: 0.9rem;"><i class="fa-solid fa-bag-shopping" style="margin-right: 8px;"></i> Create New Order</a>
        </div>
        
        <div style="margin-bottom: 20px;">
            <input type="text" id="searchInput" class="form-control" placeholder="Start typing customer name, clothing type, or status..." autocomplete="off">
        </div>

        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Fabric Type</th>
                        <th>Collection Date</th>
                        <th>Amount (₦)</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                        <tr onclick="window.location='view.php?id=<?= $order['id'] ?>'" style="cursor: pointer;">
                            <td data-label="Customer Name"><strong><?= htmlspecialchars($order['customer_name']) ?></strong></td>
                            <td data-label="Fabric Type"><?= htmlspecialchars($order['fabric_type']) ?></td>
                            <td data-label="Collection Date"><?= htmlspecialchars($order['collection_date']) ?></td>
                            <td data-label="Amount">₦<?= number_format($order['amount_charged'], 0) ?></td>
                            <td data-label="Status">
                                <span style="padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; display: inline-block; background: <?= strtolower($order['status']) === 'completed' || strtolower($order['status']) === 'ready' ? '#dcfce7; color: #16a34a;' : '#fef3c7; color: #d97706;' ?>">
                                    <?= htmlspecialchars($order['status']) ?>
                                </span>
                            </td>
                           <td data-label="Actions" onclick="event.stopPropagation();">
    <div style="display: flex; gap: 10px; align-items: center;">
        <a href="edit.php?id=<?= $order['id'] ?>" style="color: #d97706; text-decoration: none; font-weight: 500;">Edit</a>
        <a href="delete.php?id=<?= $order['id'] ?>" onclick="return confirm('Are you sure you want to delete this order?');" style="color: #ef4444; text-decoration: none; font-weight: 500;">Delete</a>
    </div>
</td>
					

                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="noOrdersRow">
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">No orders found. Click "Create New Order" to get started.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    
</div>

<script>
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('ordersTableBody');
    const rows = tableBody.getElementsByTagName('tr');

    searchInput.addEventListener('input', function() {
        const filter = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        for (let i = 0; i < rows.length; i++) {
            if (rows[i].id === 'noOrdersRow') continue;

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
                emptyRow.innerHTML = '<td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">No matching orders found.</td>';
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