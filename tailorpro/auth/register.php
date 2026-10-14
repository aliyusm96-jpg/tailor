<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    redirect('../dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $businessName = trim($_POST['business_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $businessName === '' || $email === '' || $password === '') {
        $errors[] = 'Please fill all required fields.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);

        if ($check->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, business_name, email, phone, password, address)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$fullName, $businessName, $email, $phone, $hash, $address]);

            redirect('login.php?registered=1');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Create Account - Tailor</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
    :root {
        --primary: #4f46e5;
        --primary-hover: #4338ca;
        --bg-color: #f8fafc;
        --card-bg: #ffffff;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
    }
    body.auth-page { 
        font-family: 'Plus Jakarta Sans', sans-serif; 
        background-color: var(--bg-color); 
        color: var(--text-main); 
        margin: 0; 
        padding: 15px; 
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        box-sizing: border-box;
    }
    .auth-card { 
        width: 100%;
        max-width: 480px; 
        background: var(--card-bg); 
        padding: 25px; 
        border-radius: 16px; 
        border: 1px solid var(--border-color); 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); 
        box-sizing: border-box;
        margin: 20px 0;
    }
    @media (min-width: 768px) {
        .auth-card { padding: 35px; }
    }
    .auth-card h1 { 
        margin: 0; 
        color: var(--primary); 
        font-size: 24px; 
        font-weight: 700;
    }
    .brand-header {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-bottom: 5px;
    }
    .auth-card > p {
        font-size: 14px;
        color: var(--text-muted);
        text-align: center;
        margin-top: 0;
        margin-bottom: 25px;
    }
    label { 
        display: block; 
        margin-bottom: 6px; 
        font-weight: 600; 
        font-size: 13px; 
        color: var(--text-muted); 
    }
    input { 
        width: 100%; 
        height: 46px;
        padding: 10px 14px; 
        margin-bottom: 15px;
        border: 1px solid var(--border-color); 
        border-radius: 8px; 
        font-family: inherit; 
        font-size: 16px; /* Prevents mobile Safari auto-zoom */
        box-sizing: border-box; 
        background: #fff; 
        color: var(--text-main); 
    }
    @media (min-width: 768px) {
        input { font-size: 14px; }
    }
    input:focus { 
        outline: none; 
        border-color: var(--primary); 
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); 
    }
    button[type="submit"] { 
        width: 100%; 
        background: var(--primary); 
        color: white; 
        border: none; 
        padding: 14px; 
        border-radius: 8px; 
        font-weight: 600; 
        cursor: pointer; 
        font-size: 15px; 
        box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2); 
        transition: background 0.2s;
        box-sizing: border-box;
        margin-top: 5px;
    }
    button[type="submit"]:hover { background: var(--primary-hover); }
    .auth-link { 
        text-align: center;
        margin-top: 20px; 
        font-size: 14px;
        color: var(--text-muted);
    }
    .auth-link a { 
        color: var(--primary); 
        text-decoration: none; 
        font-weight: 600;
    }
    .auth-link a:hover { text-decoration: underline; }
    
    .alert { padding: 12px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; margin-bottom: 20px; box-sizing: border-box; }
    .alert.error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    .alert.error div { margin-bottom: 3px; }
    .alert.error div:last-child { margin-bottom: 0; }
</style>
</head>
<body class="auth-page">
<div class="auth-card">
    <div class="brand-header">
        <i class="fa-solid fa-scissors" style="color: red; font-size: 1.25rem;"></i>
        <h1>Tailor</h1>
    </div>
    <p>Create your tailor account</p>

    <?php if ($errors): ?>
        <div class="alert error">
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <label>Full Name *</label>
        <input type="text" name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>" autocomplete="name">

        <label>Business / Brand Name *</label>
        <input type="text" name="business_name" required value="<?= e($_POST['business_name'] ?? '') ?>" autocomplete="organization">

        <label>Email *</label>
        <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email">

        <label>Phone *</label>
        <input type="text" name="phone" required value="<?= e($_POST['phone'] ?? '') ?>" autocomplete="tel">

        <label>Shop Address *</label>
        <input type="text" name="address" required value="<?= e($_POST['address'] ?? '') ?>" autocomplete="street-address">

        <label>Password *</label>
        <input type="password" name="password" required autocomplete="new-password">

        <label>Confirm Password *</label>
        <input type="password" name="confirm_password" required autocomplete="new-password">

        <button type="submit">Create Account</button>
    </form>

    <p class="auth-link">Already registered? <a href="login.php">Login</a></p>
</div>
</body>
</html>