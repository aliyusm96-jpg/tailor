<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle initial request or standard PHP fallback search if JS is disabled[cite: 8]
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch customers and their measurements linked to the logged-in tailor[cite: 8]
if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM customer_measurements WHERE customer_id = c.id) as measurement_count 
        FROM customers c 
        WHERE c.tailor_id = ? AND (c.name LIKE ? OR c.phone LIKE ?) 
        ORDER BY c.name ASC
    ");
    $stmt->execute([$_SESSION['user_id'], "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT COUNT(*) FROM customer_measurements WHERE customer_id = c.id) as measurement_count 
        FROM customers c 
        WHERE c.tailor_id = ? 
        ORDER BY c.name ASC
    ");
    $stmt->execute([$_SESSION['user_id']]);
}
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

<div style="max-width: 800px; margin: 0 auto;">
    <div style="background: var(--surface-color); padding: 16px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); @media(min-width: 768px){padding: 24px;}">
        <div class="header-flex" style="margin-bottom: 16px;">
            <h2 style="margin: 0; color: var(--primary-color); font-size: 1.25rem; @media(min-width: 768px){font-size: 1.5rem;}">Customers Measurements</h2>
        </div>

        <form method="GET" action="" class="search-bar" id="searchForm" style="display: flex; gap: 10px; margin-bottom: 20px; width: 100%; align-items: center; background: transparent; padding: 0; border: none; box-shadow: none;">
            <div class="search-input-wrap" style="width: 100%; position: relative;">
                <input type="text" id="searchInput" name="search" class="form-control" placeholder="Search customer name or phone..." value="<?= htmlspecialchars($search) ?>" autocomplete="off" style="height: 44px; font-size: 0.9rem; @media(min-width: 768px){height: 48px; font-size: 1rem;}">
            </div>
            <?php if ($search !== ''): ?>
                <a href="index.php" class="btn-reset" style="display: flex; align-items: center; justify-content: center; padding: 0 16px; background: #e2e8f0; color: #1e293b; border-radius: 12px; text-decoration: none; font-weight: 600; font-size: 0.85rem; height: 44px; flex-shrink: 0; @media(min-width: 768px){height: 48px; padding: 0 20px; font-size: 0.95rem;}">Reset</a>
            <?php endif; ?>
        </form>

        <div class="table-responsive" style="width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                    <tr>
                        <th style="padding: 12px 16px; background: #f8fafc; color: var(--text-muted); font-weight: 600; border-bottom: 1px solid var(--border-color); text-align: left;">Customer Name</th>
                        <th style="padding: 12px 16px; background: #f8fafc; color: var(--text-muted); font-weight: 600; border-bottom: 1px solid var(--border-color); text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody id="customerTableBody">
                    <?php if (count($customers) > 0): ?>
                        <?php foreach ($customers as $c): ?>
                        <tr class="clickable-row" data-href="../customers/view.php?id=<?= $c['id'] ?>" style="cursor: pointer; transition: background 0.15s ease;">
                            <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-color);">
                                <span class="customer-name" style="color: var(--text-main); font-weight: 600;"><?= htmlspecialchars($c['name']) ?></span>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                                    <?= $c['measurement_count'] > 0 ? '<span style="color: #059669; font-weight: 500;">Recorded (' . $c['measurement_count'] . ' fields)</span>' : '<span style="color: var(--text-muted);">No measurements</span>' ?>
                                </div>
                            </td>
                            <td style="padding: 12px 16px; border-bottom: 1px solid var(--border-color); text-align: right;" onclick="event.stopPropagation();">
                                <a href="../measurements/manage.php?customer_id=<?= $c['id'] ?>" class="btn-action" style="display: inline-block; padding: 6px 12px; background: #e0e7ff; color: #3730a3; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.8rem;">
                                    <?= $c['measurement_count'] > 0 ? 'Update' : 'Add' ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="noResultsRow"><td colspan="2" style="color: var(--text-muted); text-align: center; padding: 30px; font-size: 0.9rem; border-bottom: 1px solid var(--border-color);">No customers found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    
</div>

<script>
    document.addEventListener('click', function(e) {
        const row = e.target.closest('tr.clickable-row');
        if (row && !e.target.closest('.btn-action')) {
            window.location.href = row.dataset.href;
        }
    });

    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('customerTableBody');

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();

        fetch('index.php?search=' + encodeURIComponent(query))
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTbody = doc.getElementById('customerTableBody');
                if (newTbody) {
                    tableBody.innerHTML = newTbody.innerHTML;
                }
            })
            .catch(err => console.error('Live search error:', err));
    });
</script>

<?php include('../includes/app_footer.php'); ?>