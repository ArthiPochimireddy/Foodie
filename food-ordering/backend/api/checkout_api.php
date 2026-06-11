<?php
// backend/api/checkout_api.php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/db.php';
include_once '../config/security.php';

initSecureSession();
requireMethod('POST');
requireUserAuth();

$user_id          = (int)$_SESSION['user_id'];
$delivery_address = sanitizeString(getInput('delivery_address'));

// Fallback: use user's saved address if none provided
if (empty($delivery_address)) {
    try {
        $s = $pdo->prepare("SELECT address FROM users WHERE user_id = ? LIMIT 1");
        $s->execute([$user_id]);
        $row = $s->fetch();
        $delivery_address = sanitizeString($row['address'] ?? '');
    } catch (PDOException $e) {
        sendError(500, "Could not retrieve user address.", $e->getMessage());
    }
}

if (empty($delivery_address)) {
    sendError(400, "Delivery address is required.");
}

try {
    // Fetch cart items (prices from DB — never trust frontend totals)
    $stmt = $pdo->prepare("
        SELECT c.cart_id, c.food_id, c.quantity, f.price
        FROM cart c JOIN foods f ON c.food_id = f.food_id
        WHERE c.user_id = :uid
    ");
    $stmt->execute([':uid' => $user_id]);
    $cart_items = $stmt->fetchAll();

    if (count($cart_items) === 0) {
        sendError(400, "Your cart is empty. Add items before checking out.");
    }

    // Server-side total calculation
    $subtotal    = 0;
    foreach ($cart_items as $item) {
        $subtotal += floatval($item['price']) * intval($item['quantity']);
    }
    $delivery_fee  = $subtotal > 0 ? 5.00 : 0.00;
    $tax           = $subtotal * 0.10;
    $total_amount  = round($subtotal + $delivery_fee + $tax, 2);

    // Begin transaction for data integrity
    $pdo->beginTransaction();

    // Insert order record
    $pdo->prepare("
        INSERT INTO orders (user_id, total_amount, order_status, delivery_address)
        VALUES (?, ?, 'pending', ?)
    ")->execute([$user_id, $total_amount, $delivery_address]);

    $order_id = (int)$pdo->lastInsertId();

    // Insert order items (snapshot prices at time of purchase)
    $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, food_id, quantity, price) VALUES (?,?,?,?)");
    foreach ($cart_items as $item) {
        $item_stmt->execute([$order_id, $item['food_id'], $item['quantity'], $item['price']]);
    }

    // Clear user's cart
    $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);

    $pdo->commit();

    sendSuccess("Order placed successfully! Your food is being prepared.", [
        "order_id"    => $order_id,
        "total_paid"  => number_format($total_amount, 2, '.', ''),
        "status"      => "pending"
    ], 201);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendError(500, "An error occurred while processing your order.", $e->getMessage());
}
?>
