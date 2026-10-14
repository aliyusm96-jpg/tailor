<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

$error = '';
$success = '';

// Fetch current user profile data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $business_name = trim($_POST['business_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    $logo_path = $user['logo'] ?? '';

    // Handle Brand Logo Upload
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['logo']['tmp_name'];
        $file_name = $_FILES['logo']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed_extensions)) {
            $new_file_name = 'logo_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
            $upload_dir = '../assets/uploads/';
            
            // Create uploads directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $destination = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                // Delete old logo if it exists on the server
                if (!empty($user['logo']) && file_exists('../' . $user['logo'])) {
                    @unlink('../' . $user['logo']);
                }
                $logo_path = 'assets/uploads/' . $new_file_name;
            } else {
                $error = "Failed to upload logo.";
            }
        } else {
            $error = "Invalid image format. Allowed formats: JPG, JPEG, PNG, WEBP.";
        }
    }

    if (empty($error)) {
        // Save text fields and database path for the logo using 'business_name'
        $stmt_update = $pdo->prepare("
            UPDATE users 
            SET business_name = ?, phone = ?, address = ?, logo = ? 
            WHERE id = ?
        ");
        $stmt_update->execute([$business_name, $phone, $address, $logo_path, $_SESSION['user_id']]);
        
        $success = "Profile updated successfully!";
        
        // Refresh user data to show updated details instantly
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
}

// Include centralized layout (which opens <html>, <head>, header, and <main class="app-container">)
include('../includes/app_layout.php');
?>

<style>
    .profile-card {
        background: var(--surface-color);
        padding: 24px;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--border-color);
        max-width: 900px;
        margin: 0 auto;
    }

    h2 { 
        margin-top: 0; 
        color: var(--primary-color); 
        margin-bottom: 20px; 
        font-size: 1.25rem;
    }
    
    .info-banner {
        background: #f1f5f9;
        border: 1px solid var(--border-color);
        padding: 16px;
        border-radius: var(--radius-md);
        margin-bottom: 25px;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    @media (min-width: 540px) {
        .info-banner {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
        }
    }

    .brand-display-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .banner-logo {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--border-color);
        background: #ffffff;
        flex-shrink: 0;
    }
    .banner-info-text span {
        display: block;
        font-size: 0.75rem;
        color: var(--text-muted);
        font-weight: 500;
    }
    .banner-info-text strong {
        font-size: 0.9rem;
        color: var(--text-main);
        word-break: break-all;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 16px;
    }
    @media (min-width: 768px) {
        .form-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
    }

    .form-group { display: flex; flex-direction: column; }
    .form-group.full-width { grid-column: span 1; }
    @media (min-width: 768px) {
        .form-group.full-width { grid-column: span 2; }
    }

    label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.875rem; color: var(--text-muted); }
    
    .brand-preview-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 5px;
    }
    @media (min-width: 480px) {
        .brand-preview-container {
            flex-direction: row;
            align-items: center;
            gap: 15px;
        }
    }

    .current-brand-img {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid var(--border-color);
        background: #f1f5f9;
        flex-shrink: 0;
    }
    .file-input-wrapper { flex: 1; width: 100%; }
    .file-input-wrapper input[type="file"] {
        height: auto;
        padding: 8px;
        font-size: 0.9rem;
        background: #f8fafc;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
    }

    .btn-submit {
        background: var(--primary-color);
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: var(--radius-sm);
        font-weight: 600;
        cursor: pointer;
        margin-top: 15px;
        font-size: 0.95rem;
        transition: background 0.2s;
        width: 100%;
    }
    .btn-submit:hover { background: var(--primary-hover); }
    
    .error-msg { background: #fee2e2; color: #dc2626; padding: 12px 14px; border-radius: var(--radius-sm); font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; border: 1px solid #fecaca; }
    .success-msg { background: #dcfce7; color: #16a34a; padding: 12px 14px; border-radius: var(--radius-sm); font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; border: 1px solid #bbf7d0; }
</style>

<div class="profile-card">
    
	
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2 style="margin: 0; color: var(--primary-color); font-size: 1.25rem;">Edit Profile</h2>
    <a href="/tailorpro/auth/logout.php" class="btn" style="padding: 6px 12px; font-size: 0.80rem; background: #fee2e2; color: #ef4444;" title="Logout">Logout</a>
 </div>

    <!-- Info Banner showing brand picture displayed BEFORE the business name -->
    <div class="info-banner">
        <div class="brand-display-wrapper">
            <?php if (!empty($user['logo'])): ?>
                <img src="../<?= htmlspecialchars($user['logo']) ?>" alt="Logo" class="banner-logo">
            <?php else: ?>
                <div class="banner-logo" style="display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:10px;">Logo</div>
            <?php endif; ?>
            <div class="banner-info-text">
                <span>Existing Business Name:</span>
                <strong><?= htmlspecialchars($user['business_name'] ?? 'Not set') ?></strong>
            </div>
        </div>
        <div class="banner-info-text">
            <span>Email Address:</span>
            <strong><?= htmlspecialchars($user['email'] ?? 'Not set') ?></strong>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?= $error ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="success-msg"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-grid">
            
            <div class="form-group full-width">
                <label>Brand Logo / Picture:</label>
                <div class="brand-preview-container">
                    <?php if (!empty($user['logo'])): ?>
                        <img src="../<?= htmlspecialchars($user['logo']) ?>" alt="Brand Logo" class="current-brand-img">
                    <?php else: ?>
                        <div class="current-brand-img" style="display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:11px;">No Logo</div>
                    <?php endif; ?>
                    <div class="file-input-wrapper">
                        <input type="file" name="logo" accept="image/*" class="form-control">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Business Name:</label>
                <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($user['business_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Phone Number:</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
            </div>

            <div class="form-group full-width">
                <label>Shop Address:</label>
                <textarea name="address" class="form-control"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
            </div>

            <div class="form-group full-width">
                <button type="submit" class="btn-submit">Save Changes</button>
            </div>

        </div>
    </form>
</div>

<?php 
// Include centralized footer (which closes <main>, app navigation bar, <body>, and <html>)
include('../includes/app_footer.php'); 
?>