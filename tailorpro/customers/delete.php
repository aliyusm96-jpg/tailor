<?php
session_start();
require_once '../config/config.php';
require_once '../includes/auth.php';

$customer_id = $_GET['id'] ?? null;

if ($customer_id) {
    // Verify ownership before deleting so tailors can only delete their own customers
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND tailor_id = ?");
    $stmt->execute([$customer_id, $_SESSION['user_id']]);
    $customer = $stmt->fetch();

    if ($customer) {
        // Delete the customer (associated measurements will automatically cascade if the foreign key is set up with ON DELETE CASCADE, or you can delete them explicitly)
        $stmt_del_m = $pdo->prepare("DELETE FROM customer_measurements WHERE customer_id = ?");
        $stmt_del_m->execute([$customer_id]);

        $stmt_del = $pdo->prepare("DELETE FROM customers WHERE id = ? AND tailor_id = ?");
        $stmt_del->execute([$customer_id, $_SESSION['user_id']]);
    }
}

// Redirect back to the customers index page
header("Location: index.php");
exit;