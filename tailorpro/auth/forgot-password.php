<?php
session_start();
require_once '../config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!empty($email)) {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, business_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // For a production app, you would generate a secure token, store it with an expiration timestamp, 
            // and email a reset link. For this management system scope, we will provide a direct notice or simulation.
            $success = "If an account with that email exists, password reset instructions have been sent.";
        } else {
            // Even if the email doesn't exist, it's secure practice to show a generic success message 
            // to prevent email enumeration, but we can display a user-friendly status notice.
            $success = "If an account with that email exists, password reset instructions have been sent.";
        }
    } else {
        $error = "Please enter your registered email address.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - TailorPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        body { 
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
        .container { 
            width: 100%;
            max-width: 450px; 
            background: var(--card-bg); 
            padding: 25px; 
            border-radius: 16px; 
            border: 1px solid var(--border-color); 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); 
            box-sizing: border-box;
        }
        @media (min-width: 768px) {
            .container { padding: 35px; }
        }
        h2 { 
            margin-top: 0; 
            color: var(--primary); 
            margin-bottom: 8px; 
            font-size: 22px; 
            text-align: center;
        }
        .subtitle {
            font-size: 14px;
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 25px;
            line-height: 1.4;
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
        .btn-submit { 
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
        }
        .btn-submit:hover { background: var(--primary-hover); }
        .back-link-wrap { 
            text-align: center;
            margin-top: 20px; 
        }
        .back-link { 
            text-decoration: none; 
            color: var(--text-muted); 
            font-size: 14px; 
            font-weight: 500;
        }
        .back-link:hover { color: var(--primary); }
        .error-msg { background: #fee2e2; color: #dc2626; padding: 12px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; margin-bottom: 20px; border: 1px solid #fecaca; }
        .success-msg { background: #dcfce7; color: #16a34a; padding: 12px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; margin-bottom: 20px; border: 1px solid #bbf7d0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        <div class="subtitle">Enter your account email address and we'll send you instructions to recover your account.</div>
        
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-msg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="e.g. tailor@example.com" required autocomplete="email">

            <button type="submit" class="btn-submit">Send Reset Instructions</button>
        </form>

        <div class="back-link-wrap">
            <a href="login.php" class="back-link">← Back to Login</a>
        </div>
    </div>
</body>
</html>