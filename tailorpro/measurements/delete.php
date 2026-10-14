<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

$id = $_GET['id'] ?? null;
$customer_id = $_GET['customer_id'] ?? null;

if ($id && $customer_id) {
    // Verify ownership via customer and tailor session
    $stmt = $pdo->prepare("
        SELECT m.id FROM customer_measurements m
        JOIN customers c ON m.customer_id = c.id
        WHERE m.id = ? AND c.tailor_id = ?
    ");
    $stmt->execute([$id, $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        $del = $pdo->prepare("DELETE FROM customer_measurements WHERE id = ?");
        $del->execute([$id]);
    }
}

header("Location: ../customers/view.php?id=" . $customer_id);
exit;