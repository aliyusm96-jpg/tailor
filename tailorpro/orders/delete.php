<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

// Support both POST (form) and GET (link click) requests
$order_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'] ?? null;
} elseif (isset($_GET['id'])) {
    $order_id = $_GET['id'];
}

if ($order_id) {
    // Securely delete the order ensuring it belongs to the logged-in tailor
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ? AND tailor_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
}

// Redirect back to the orders management page
header("Location: index.php");
exit;