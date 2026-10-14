<?php
session_start();
require_once 'config/config.php';
require_once 'includes/auth.php';

// Ensure the user session is active, otherwise redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit;
}

// Fetch logged-in tailor details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// 1. Total Customers Count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE tailor_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_customers = $stmt->fetchColumn();

// 2. Active Orders Count (Pending or In Progress)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE tailor_id = ? AND status IN ('Pending', 'In Progress')");
$stmt->execute([$_SESSION['user_id']]);
$active_orders = $stmt->fetchColumn();

// 3. Ready Orders Count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE tailor_id = ? AND status = 'Ready'");
$stmt->execute([$_SESSION['user_id']]);
$ready_orders = $stmt->fetchColumn();

// 4. Outstanding Balance Sum
$stmt = $pdo->prepare("SELECT SUM(amount_charged - amount_paid) FROM orders WHERE tailor_id = ? AND status != 'Cancelled'");
$stmt->execute([$_SESSION['user_id']]);
$total_balance = $stmt->fetchColumn() ?? 0;

// 5. Today's Collections & Reminders (All pending/due soon)
$today = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT o.*, c.name as customer_name, c.phone as customer_phone 
    FROM orders o 
    JOIN customers c ON o.customer_id = c.id 
    WHERE o.tailor_id = ? AND o.status NOT IN ('Collected', 'Cancelled')
    ORDER BY o.collection_date ASC
");
$stmt->execute([$_SESSION['user_id']]);
$all_pending_orders = $stmt->fetchAll();

// Separate for tables
$todays_collections = [];
$upcoming_collections = [];
$notifications = [];

foreach ($all_pending_orders as $row) {
    $diff = strtotime($row['collection_date']) - strtotime($today);
    $days_remaining = floor($diff / (60 * 60 * 24));
    
    if ($row['collection_date'] == $today) {
        $todays_collections[] = $row;
        $notifications[] = ['text' => "🔴 <strong>{$row['customer_name']}</strong> ({$row['clothing_type']}) is due <strong>Today</strong>!", 'type' => 'due-today'];
    } elseif ($days_remaining == 1) {
        $upcoming_collections[] = $row;
        $notifications[] = ['text' => "🟡 <strong>{$row['customer_name']}</strong> collection is <strong>Tomorrow</strong>.", 'type' => 'tomorrow'];
    } elseif ($days_remaining < 0) {
        $days_ago = abs($days_remaining);
        $notifications[] = ['text' => "🔴 <strong>{$row['customer_name']}</strong> order is <strong>Overdue by $days_ago day(s)</strong>!", 'type' => 'overdue'];
    } else {
        if (count($upcoming_collections) < 5) {
            $upcoming_collections[] = $row;
        }
    }
}

// Compute notification count for the layout badge
$notification_count = count($notifications);

include('includes/app_layout.php');
?>

<!-- Inline CSS override to enforce a 2-column grid layout specifically for mobile metrics -->
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px;
        margin-bottom: 24px;
    }
    @media (min-width: 768px) {
        .stats-grid {
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 16px;
        }
    }
</style>

<!-- Brand Header with Logo placed BEFORE the Business Name -->
<div style="background: var(--surface-color); padding: 20px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
    <div style="display: flex; align-items: center; gap: 15px;">
        <?php if (!empty($user['logo'])): ?>
            <img src="<?= htmlspecialchars($user['logo']) ?>" alt="Logo" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); background: #f1f5f9;">
        <?php else: ?>
            <div style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid var(--border-color); background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 10px;">Logo</div>
        <?php endif; ?>
        <div>
            <h1 style="margin: 0; font-size: 1.10rem; font-weight: 700; color: var(--primary-color);"><?= strtoupper(htmlspecialchars($user['business_name'] ?? 'My Brand')) ?></h1>
            <p style="margin: 2px 0 0 0; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px;">Tailor Workspace</p>
        </div>
    </div>
</div>

<!-- Metrics Cards Grid (2-columns on mobile, 4-columns on tablet/desktop) -->
<div class="stats-grid">
    <div class="stat-card" style="padding: 14px;">
        <h3 style="font-size: 0.75rem;">Total Customers</h3>
        <div class="value" style="font-size: 1.4rem;"><?= $total_customers ?></div>
    </div>
    <div class="stat-card" style="padding: 14px;">
        <h3 style="font-size: 0.75rem;">Active Orders</h3>
        <div class="value" style="font-size: 1.4rem; color: var(--primary-color);"><?= $active_orders ?></div>
    </div>
    <div class="stat-card" style="padding: 14px;">
        <h3 style="font-size: 0.75rem;">Ready for Collection</h3>
        <div class="value" style="font-size: 1.4rem; color: #10b981;"><?= $ready_orders ?></div>
    </div>
    <div class="stat-card" style="padding: 14px;">
        <h3 style="font-size: 0.75rem;">Pending Balance</h3>
        <div class="value" style="font-size: 1.25rem; color: #ef4444;">₦<?= number_format($total_balance, 2) ?></div>
    </div>
</div>

<!-- Main Layout Split -->
<div style="display: grid; grid-template-columns: 1fr; gap: 24px;">
    <style>
        @media (min-width: 968px) {
            .dashboard-grid-split { grid-template-columns: 2fr 1fr !important; }
        }
    </style>
    <div class="dashboard-grid-split" style="display: grid; grid-template-columns: 1fr; gap: 24px;">
        
     <!-- Left Side: Tables (Today & Upcoming) -->
<div>
  <!-- Today's Collections -->
<div style="background: var(--surface-color); border-radius: var(--radius-lg); padding: 20px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); margin-bottom: 24px;">
    <h3 style="font-size: 0.9rem; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
        <span style="display: flex; align-items: center; gap: 6px; white-space: nowrap;">
            Today's Collections 
            <span style="font-size: 0.75rem; font-weight: 600; background: #fee2e2; color: #dc2626; padding: 2px 8px; border-radius: 12px;"><?= count($todays_collections) ?></span>
        </span>
        <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px; white-space: nowrap;">
            <span style="font-size: 0.75rem; font-weight: 400; background: #e0e7ff; color: var(--primary-color); padding: 3px 10px; border-radius: 20px;"><?= $today ?></span>
            <a href="reminders/index.php" style="color: var(--primary-color); font-size: 0.75rem; font-weight: 400; text-decoration: none; margin-top: 2px;">View All</a>
        </div>
    </h3>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Fabric Type</th>
                        <th>Balance Due</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($todays_collections) > 0): 
                        $todays_limit = array_slice($todays_collections, 0, 1);
                        foreach ($todays_limit as $row): 
                            $balance = $row['amount_charged'] - $row['amount_paid'];
                        ?>
                        <tr>
                            <td data-label="Name"><strong><?= htmlspecialchars($row['customer_name']) ?></strong></td>
                            <td data-label="Fabric Type"><?= htmlspecialchars($row['fabric_type'] ) ?></td>
                            <td data-label="Balance Due">₦<?= number_format($balance, 2) ?></td>
                            <td data-label="Action"><a href="orders/view.php?id=<?= $row['id'] ?>" class="btn" style="padding: 4px 10px; font-size: 0.7rem; background: #e0e7ff; color: var(--primary-color);">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="color: var(--text-muted); text-align: center; padding: 20px;">No collections due today. 🎉</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
		 
        </div>
        <?php if (count($todays_collections) > 1): ?>
            <div style="text-align: right; margin-top: 12px;">
                 </div>
        <?php endif; ?>
    </div>

    <!-- Upcoming Collections -->
    <div style="background: var(--surface-color); border-radius: var(--radius-lg); padding: 20px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <h3 style="font-size: 0.9rem; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
            <span>Upcoming Schedule <span style="font-size: 0.75rem; font-weight: 600; background: #eff6ff; color: #1d4ed8; padding: 2px 8px; border-radius: 12px; margin-left: 6px;"><?= count($upcoming_collections) ?></span></span>
            <a href="reminders/index.php" style="color: var(--primary-color); font-size: 0.75rem; font-weight: 400; text-decoration: none;">View All</a>
        </h3>
        <div class="table-responsive">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Fabric Type</th>
                        <th>Collection Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($upcoming_collections) > 0): 
                        $upcoming_limit = array_slice($upcoming_collections, 0, 2);
                        foreach ($upcoming_limit as $row): 
                        ?>
                        <tr>
                            <td data-label="Name"><strong><?= htmlspecialchars($row['customer_name']) ?></strong></td>
                            <td data-label="Fabric Type"><?= htmlspecialchars($row['fabric_type'] ) ?></td>
                            <td data-label="Collection Date"><?= htmlspecialchars($row['collection_date']) ?></td>
                            <td data-label="Action"><a href="orders/view.php?id=<?= $row['id'] ?>" class="btn" style="padding: 4px 10px; font-size: 0.7rem; background: #e0e7ff; color: var(--primary-color);">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="color: var(--text-muted); text-align: center; padding: 20px;">No upcoming collections scheduled.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (count($upcoming_collections) > 2): ?>
            <div style="text-align: right; margin-top: 12px;">
                 </div>
        <?php endif; ?>
    </div>
</div>
	<!-- Right Side: Sticky Notification Box Widget -->
<div>
    <div style="background: var(--surface-color); border-radius: var(--radius-lg); padding: 20px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);">
        <h3 style="font-size: 1.1rem; margin-bottom: 6px;"><i class="fa-solid fa-bell" style="color: var(--primary-color); margin-right: 6px;"></i> Live Reminders</h3>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 16px;">Real-time alerts for collection deadlines.</p>
        
        <?php if (count($notifications) > 0): 
            // Reverse to put latest on top, then slice to limit to 3 items
            $display_notifications = array_slice(array_reverse($notifications), 0, 3);
            foreach ($display_notifications as $note): 
        ?>
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: var(--radius-sm); padding: 12px; margin-bottom: 10px; font-size: 0.85rem; color: #92400e;">
                <span><?= $note['text'] ?></span>
            </div>
        <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 30px; color: var(--text-muted); font-size: 0.9rem;">
                <p>✨ All clear! No urgent collection alerts right now.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include('includes/app_footer.php'); ?>