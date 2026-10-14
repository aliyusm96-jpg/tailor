<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch all active/pending orders with customer details
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT o.*, c.name as customer_name, c.phone as customer_phone 
    FROM orders o 
    JOIN customers c ON o.customer_id = c.id 
    WHERE o.tailor_id = ? AND o.status NOT IN ('Collected', 'Cancelled')
    ORDER BY o.collection_date ASC
");
$stmt->execute([$_SESSION['user_id']]);
$all_orders = $stmt->fetchAll();

// Categorize orders & compute notification count
$overdue_orders = [];
$today_orders = [];
$tomorrow_orders = [];
$upcoming_orders = [];
$notifications = [];

foreach ($all_orders as $row) {
    $diff = strtotime($row['collection_date']) - strtotime($today);
    $days_remaining = floor($diff / (60 * 60 * 24));

    if ($days_remaining < 0) {
        $row['days_ago'] = abs($days_remaining);
        $overdue_orders[] = $row;
        $notifications[] = ['type' => 'overdue'];
    } elseif ($row['collection_date'] == $today) {
        $today_orders[] = $row;
        $notifications[] = ['type' => 'due-today'];
    } elseif ($days_remaining == 1) {
        $tomorrow_orders[] = $row;
        $notifications[] = ['type' => 'tomorrow'];
    } else {
        $row['days_remaining'] = $days_remaining;
        $upcoming_orders[] = $row;
    }
}

// Compute notification count for the app layout header badge
$notification_count = count($notifications);

// Include centralized layout
include('../includes/app_layout.php');
?>

<div style="max-width: 1100px; margin: 0 auto;">

    <!-- Page Title Header -->
    <div style="display: flex; flex-direction: column; gap: 15px; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; @media(min-width: 540px){flex-direction:row;align-items:center;}">
        <h2 style="margin: 0; color: var(--primary-color); font-size: 0.9rem;">Reminders & Collection Schedule</h2>
    </div>

    <!-- Live Search Input -->
    <div style="margin-bottom: 20px;">
        <input type="text" id="searchInput" class="form-control" placeholder="Search customers or clothing type..." autocomplete="off">
    </div>

    <!-- Overdue Orders Section -->
    <div style="background: var(--surface-color); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 24px;">
        <h3 style="margin-top: 0; font-size: 0.9rem; font-weight: 600; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
            <span>🔴 Overdue Collections</span>
            <span style="padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: #fef2f2; color: #991b1b;"><?= count($overdue_orders) ?></span>
        </h3>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Clothing Type</th>
                        <th>Collection Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="overdueTableBody">
                    <?php if (count($overdue_orders) > 0): ?>
                        <?php foreach ($overdue_orders as $index => $row): ?>
                        <tr class="searchable-row" onclick="window.location.href='../orders/view.php?id=<?= $row['id'] ?>'" style="cursor: pointer; <?= $index >= 2 ? 'display:none;' : '' ?>" <?= $index >= 2 ? 'data-hidden="true"' : '' ?>>
                            <td data-label="Name"><strong><?= htmlspecialchars($row['customer_name']) ?></strong></td>
                            <td data-label="Phone" onclick="event.stopPropagation();"><a href="tel:<?= htmlspecialchars($row['customer_phone']) ?>" style="color: var(--primary-color); text-decoration: none;"><?= htmlspecialchars($row['customer_phone']) ?></a></td>
                            <td data-label="Clothing Type"><?= htmlspecialchars($row['fabric_type']) ?></td>
                            <td data-label="Collection Date"><?= htmlspecialchars($row['collection_date']) ?></td>
                            <td data-label="Status">
                                <span style="padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 600; background: #fef2f2; color: #991b1b;">
                                    Overdue by <?= $row['days_ago'] ?> day(s)
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="no-data-row"><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">No overdue orders. Great job! 🎉</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($overdue_orders) > 2): ?>
            <div style="text-align: left; margin-top: 12px;">
                <span id="overdueToggleText" class="view-all-text" onclick="toggleViewAll('overdue')" style="color: var(--primary-color); font-size: 0.85rem; font-weight: 500; cursor: pointer;">View All (<?= count($overdue_orders) ?>)</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Due Today Section -->
    <div style="background: var(--surface-color); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 24px;">
        <h3 style="margin-top: 0; font-size: 0.9rem; font-weight: 600; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
            <span>🔴 Today's Collections</span>
            <span style="padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: #fffbeb; color: #b45309;"><?= count($today_orders) ?></span>
        </h3>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Clothing Type</th>
                        <th>Balance Due</th>
                    </tr>
                </thead>
                <tbody id="todayTableBody">
                    <?php if (count($today_orders) > 0): ?>
                        <?php foreach ($today_orders as $index => $row): 
                            $balance = $row['amount_charged'] - $row['amount_paid'];
                        ?>
                        <tr class="searchable-row" onclick="window.location.href='../orders/view.php?id=<?= $row['id'] ?>'" style="cursor: pointer; <?= $index >= 2 ? 'display:none;' : '' ?>" <?= $index >= 2 ? 'data-hidden="true"' : '' ?>>
                            <td data-label="Name"><strong><?= htmlspecialchars($row['customer_name']) ?></strong></td>
                            <td data-label="Phone" onclick="event.stopPropagation();"><a href="tel:<?= htmlspecialchars($row['customer_phone']) ?>" style="color: var(--primary-color); text-decoration: none;"><?= htmlspecialchars($row['customer_phone']) ?></a></td>
                            <td data-label="Clothing Type"><?= htmlspecialchars($row['fabric_type']) ?></td>
                            <td data-label="Balance Due">₦<?= number_format($balance, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="no-data-row"><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No collections scheduled for today.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($today_orders) > 2): ?>
            <div style="text-align: left; margin-top: 12px;">
                <span id="todayToggleText" class="view-all-text" onclick="toggleViewAll('today')" style="color: var(--primary-color); font-size: 0.85rem; font-weight: 500; cursor: pointer;">View All (<?= count($today_orders) ?>)</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Due Tomorrow Section -->
    <div style="background: var(--surface-color); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 24px;">
        <h3 style="margin-top: 0; font-size: 0.9rem; font-weight: 600; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
            <span>🟡 Tomorrow Collections</span>
            <span style="padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: #fefce8; color: #a16207;"><?= count($tomorrow_orders) ?></span>
        </h3>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Clothing Type</th>
                        <th>Balance Due</th>
                    </tr>
                </thead>
                <tbody id="tomorrowTableBody">
                    <?php if (count($tomorrow_orders) > 0): ?>
                        <?php foreach ($tomorrow_orders as $index => $row): 
                            $balance = $row['amount_charged'] - $row['amount_paid'];
                        ?>
                        <tr class="searchable-row" onclick="window.location.href='../orders/view.php?id=<?= $row['id'] ?>'" style="cursor: pointer; <?= $index >= 2 ? 'display:none;' : '' ?>" <?= $index >= 2 ? 'data-hidden="true"' : '' ?>>
                            <td data-label="Name"><strong><?= htmlspecialchars($row['customer_name']) ?></strong></td>
                            <td data-label="Phone" onclick="event.stopPropagation();"><a href="tel:<?= htmlspecialchars($row['customer_phone']) ?>" style="color: var(--primary-color); text-decoration: none;"><?= htmlspecialchars($row['customer_phone']) ?></a></td>
                            <td data-label="Clothing Type"><?= htmlspecialchars($row['fabric_type']) ?></td>
                            <td data-label="Balance Due">₦<?= number_format($balance, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="no-data-row"><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No collections scheduled for tomorrow.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($tomorrow_orders) > 2): ?>
            <div style="text-align: left; margin-top: 12px;">
                <span id="tomorrowToggleText" class="view-all-text" onclick="toggleViewAll('tomorrow')" style="color: var(--primary-color); font-size: 0.85rem; font-weight: 500; cursor: pointer;">View All (<?= count($tomorrow_orders) ?>)</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upcoming Schedule Section -->
    <div style="background: var(--surface-color); padding: 24px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 24px;">
        <h3 style="margin-top: 0; font-size: 0.9rem; font-weight: 600; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
            <span>🔵 Upcoming Collections</span>
            <span style="padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: #eff6ff; color: #1d4ed8;"><?= count($upcoming_orders) ?></span>
        </h3>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Clothing Type</th>
                        <th>Collection Date</th>
                    </tr>
                </thead>
                <tbody id="upcomingTableBody">
                    <?php if (count($upcoming_orders) > 0): ?>
                        <?php foreach ($upcoming_orders as $index => $row): ?>
                        <tr class="searchable-row" onclick="window.location.href='../orders/view.php?id=<?= $row['id'] ?>'" style="cursor: pointer; <?= $index >= 2 ? 'display:none;' : '' ?>" <?= $index >= 2 ? 'data-hidden="true"' : '' ?>>
                            <td data-label="Name"><strong><?= htmlspecialchars($row['customer_name']) ?></strong></td>
                            <td data-label="Phone" onclick="event.stopPropagation();"><a href="tel:<?= htmlspecialchars($row['customer_phone']) ?>" style="color: var(--primary-color); text-decoration: none;"><?= htmlspecialchars($row['customer_phone']) ?></a></td>
                            <td data-label="Clothing Type"><?= htmlspecialchars($row['fabric_type']) ?></td>
                            <td data-label="Collection Date"><?= htmlspecialchars($row['collection_date']) ?> (In <?= $row['days_remaining'] ?> days)</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="no-data-row"><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 20px;">No upcoming collections scheduled.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($upcoming_orders) > 2): ?>
            <div style="text-align: left; margin-top: 12px;">
                <span id="upcomingToggleText" class="view-all-text" onclick="toggleViewAll('upcoming')" style="color: var(--primary-color); font-size: 0.85rem; font-weight: 500; cursor: pointer;">View All (<?= count($upcoming_orders) ?>)</span>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
    const searchInput = document.getElementById('searchInput');

    searchInput.addEventListener('input', function() {
        const filter = searchInput.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.searchable-row');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(filter)) {
                row.style.display = "";
            } else {
                row.style.display = "none";
            }
        });
    });

    function toggleViewAll(section) {
        const bodyId = section + 'TableBody';
        const toggleTextId = section + 'ToggleText';
        const tbody = document.getElementById(bodyId);
        const hiddenRows = tbody.querySelectorAll('tr[data-hidden="true"]');
        const toggleSpan = document.getElementById(toggleTextId);

        if (toggleSpan.innerText.includes('View All')) {
            hiddenRows.forEach(row => row.style.display = '');
            toggleSpan.innerText = 'Show Less';
        } else {
            hiddenRows.forEach(row => row.style.display = 'none');
            toggleSpan.innerText = 'View All (' + (hiddenRows.length + 2) + ')';
        }
    }
</script>

<?php include('../includes/app_footer.php'); ?>