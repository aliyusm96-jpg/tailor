<?php
declare(strict_types=1);

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /tailorpro/auth/login.php');
        exit;
    }
}

function current_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}
