<?php
// backend/api/payment_api.php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/db.php';
include_once '../config/security.php';

initSecureSession();
requireMethod('POST');
requireUserAuth();

$user_id        = (int)$_SESSION['user_id'];
$order_id       = validateId(getInput('order_id', 0));
$payment_method = sanitizeString(getInput('payment_method'));

// Validate required fields
if (!$order_id) sendError(400, "Valid Order ID is required.");

$allowed_methods = ['credit_card', 'debit_card', 'paypal', 'cash_on_delivery'];
if (!validateEnum($payment_method, $allowed_methods)) {
    sendError(400, "Invalid payment method. Choose: " . implode(', ', $allowed_methods));
}

try {
    // Verify order belongs to this user and is in 'pending' state
    $order_stmt = $pdo->prepare("SELECT total_amount, order_status FROM orders WHERE order_id=? AND user_id=? LIMIT 1");
    $order_stmt->execute([$order_id, $user_id]);

    if ($order_stmt->rowCount() === 0) {
        sendError(404, "Order not found or unauthorized.");
    }

    $order = $order_stmt->fetch();

    if ($order['order_status'] !== 'pending') {
        sendError(400, "This order has already been processed or cancelled.");
    }

    $amount = floatval($order['total_amount']);

    // Payment gateway simulation with card validation
    $payment_status = 'pending';
    $transaction_id = 'TXN' . time() . rand(1000, 9999);

    if (in_array($payment_method, ['credit_card', 'debit_card'], true)) {
        $card_number = preg_replace('/\s+/', '', getInput('card_number', ''));
        $cvv         = sanitizeString(getInput('cvv', ''));
        $expiry      = sanitizeString(getInput('expiry', ''));

        // Validate card number (Luhn algorithm check would go here in production)
        if (strlen($card_number) < 15 || strlen($card_number) > 19) {
            // Log the failed attempt before exiting
            $pdo->prepare("INSERT INTO payments (order_id, amount, payment_method, payment_status) VALUES (?,?,?,'failed')")
                ->execute([$order_id, $amount, $payment_method]);
            sendError(400, "Invalid card number. Please check your details.");
        }

        if (strlen($cvv) < 3 || !is_numeric($cvv)) {
            $pdo->prepare("INSERT INTO payments (order_id, amount, payment_method, payment_status) VALUES (?,?,?,'failed')")
                ->execute([$order_id, $amount, $payment_method]);
            sendError(400, "Invalid CVV. Payment failed.");
        }

        if (empty($expiry) || !preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry)) {
            sendError(400, "Invalid expiry date format. Use MM/YY.");
        }

        // In production: call Stripe / PayPal SDK here
        $payment_status = 'completed';

    } elseif ($payment_method === 'paypal') {
        // In production: initiate PayPal OAuth redirect
        $payment_status = 'completed';
    } else {
        // Cash on delivery stays pending until driver collects
        $payment_status = 'pending';
    }

    // Begin transaction: insert payment + update order status atomically
    $pdo->beginTransaction();

    $pdo->prepare("
        INSERT INTO payments (order_id, amount, payment_method, payment_status, transaction_id)
        VALUES (?,?,?,?,?)
    ")->execute([$order_id, $amount, $payment_method, $payment_status, $transaction_id]);

    $new_order_status = ($payment_status === 'completed' || $payment_method === 'cash_on_delivery')
        ? 'processing'
        : 'pending';

    $pdo->prepare("UPDATE orders SET order_status=? WHERE order_id=?")->execute([$new_order_status, $order_id]);

    $pdo->commit();

    sendSuccess(
        $payment_method === 'cash_on_delivery'
            ? "Order confirmed! Pay on delivery."
            : "Payment successful! Your order is now processing.",
        [
            "transaction_id" => $transaction_id,
            "amount_paid"    => number_format($amount, 2, '.', ''),
            "status"         => $payment_status,
            "method"         => $payment_method
        ]
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    sendError(500, "An internal server error occurred during payment.", $e->getMessage());
}
?>
