<?php
// backend/api/cart_api.php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/db.php';
include_once '../config/security.php';

initSecureSession();
requireUserAuth(); // Returns 401 JSON if not logged in

$user_id = (int)$_SESSION['user_id'];
$method  = $_SERVER['REQUEST_METHOD'];

// ── GET: Fetch cart + server-side total calculation ──────────────────────────
if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT c.cart_id, c.quantity, f.food_id, f.title, f.price, f.image_name,
                   (c.quantity * f.price) AS item_total
            FROM cart c
            JOIN foods f ON c.food_id = f.food_id
            WHERE c.user_id = :uid
        ");
        $stmt->execute([':uid' => $user_id]);
        $items = $stmt->fetchAll();

        $subtotal    = array_sum(array_column($items, 'item_total'));
        $delivery    = $subtotal > 0 ? 5.00 : 0.00;
        $tax         = $subtotal * 0.10;
        $total       = $subtotal + $delivery + $tax;

        sendSuccess("Cart fetched.", [
            "items"   => $items,
            "summary" => [
                "subtotal"     => number_format($subtotal, 2, '.', ''),
                "delivery_fee" => number_format($delivery,  2, '.', ''),
                "tax"          => number_format($tax,       2, '.', ''),
                "total"        => number_format($total,     2, '.', '')
            ]
        ]);
    } catch (PDOException $e) {
        sendError(500, "Failed to fetch cart.", $e->getMessage());
    }
}

// ── POST: Add / Update / Remove ───────────────────────────────────────────────
if ($method === 'POST') {
    $action  = sanitizeString(getInput('action'));
    $food_id = validateId(getInput('food_id', 0));
    $cart_id = validateId(getInput('cart_id', 0));
    $qty     = max(1, (int)getInput('quantity', 1));

    // ADD
    if ($action === 'add') {
        if (!$food_id) sendError(400, "Invalid food ID.");

        try {
            $chk = $pdo->prepare("SELECT cart_id, quantity FROM cart WHERE user_id=? AND food_id=? LIMIT 1");
            $chk->execute([$user_id, $food_id]);

            if ($chk->rowCount() > 0) {
                $row    = $chk->fetch();
                $newQty = $row['quantity'] + $qty;
                $pdo->prepare("UPDATE cart SET quantity=? WHERE cart_id=?")->execute([$newQty, $row['cart_id']]);
                sendSuccess("Cart updated! Quantity increased.");
            } else {
                $pdo->prepare("INSERT INTO cart (user_id, food_id, quantity) VALUES (?,?,?)")->execute([$user_id, $food_id, $qty]);
                sendSuccess("Item added to cart!", [], 201);
            }
        } catch (PDOException $e) {
            sendError(500, "Failed to add item to cart.", $e->getMessage());
        }
    }

    // UPDATE QTY
    elseif ($action === 'update_qty') {
        if (!$cart_id || $qty < 1) sendError(400, "Valid cart ID and quantity required.");

        try {
            $stmt = $pdo->prepare("UPDATE cart SET quantity=? WHERE cart_id=? AND user_id=?");
            $stmt->execute([$qty, $cart_id, $user_id]);
            $stmt->rowCount() > 0
                ? sendSuccess("Quantity updated.")
                : sendError(404, "Cart item not found or unauthorized.");
        } catch (PDOException $e) {
            sendError(500, "Failed to update quantity.", $e->getMessage());
        }
    }

    // REMOVE
    elseif ($action === 'remove') {
        if (!$cart_id) sendError(400, "Invalid cart ID.");

        try {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE cart_id=? AND user_id=?");
            $stmt->execute([$cart_id, $user_id]);
            $stmt->rowCount() > 0
                ? sendSuccess("Item removed from cart.")
                : sendError(404, "Cart item not found or unauthorized.");
        } catch (PDOException $e) {
            sendError(500, "Failed to remove item.", $e->getMessage());
        }
    }

    else {
        sendError(400, "Invalid action.");
    }
}
?>
