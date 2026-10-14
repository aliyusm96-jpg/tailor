<?php
// Determine active script for active state highlighting
$current_page = basename($_SERVER['PHP_SELF']);



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tailor Modern App Interface</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="/tailorpro/assets/css/style.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <!-- Top App Header -->
    <header class="app-header">
        <div style="display: flex; align-items: center; gap: 8px;">
            <i class="fa-solid fa-scissors" style="color: var(--primary-color); font-size: 1rem;"></i>
            <span style="font-size: 0.95rem; font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($user['business_name'] ?? 'Tailor')?></span>
        </div>
        
        <div class="user-profile-actions" style="display: flex; align-items: center; gap: 8px;">
            <a href="/tailorpro/profile/edit.php" class="btn" style="padding: 6px 12px; font-size: 0.85rem; background: #e2e8f0; color: var(--text-main);" title="Profile Settings"><i class="fa-solid fa-gear"></i></a>
           <a href="/tailorpro/reminders/index.php" class="btn" style="padding: 6px 12px; font-size: 0.85rem; background: #e2e8f0; color: var(--text-main); position: relative;" title="Reminders & Notifications">
    <i class="fa-solid fa-bell"></i>
    
    <?php 
    $notification_count = count($notifications ?? []); 
    if ($notification_count > 0): 
    ?>
        <span style="position: absolute; top: -4px; right: -4px; background: #ef4444; color: white; font-size: 0.6rem; font-weight: 700; padding: 2px 5px; border-radius: 50%; line-height: 1;"><?= $notification_count ?></span>
    <?php endif; ?>
</a>
        </div>
    </header>

    <main class="app-container">