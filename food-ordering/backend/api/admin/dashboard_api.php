<?php
// backend/api/admin/dashboard_api.php
session_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Content-Type: application/json; charset=UTF-8");

// Secure Admin Authentication Guard
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(["status" => "error", "message" => "Unauthorized. Admin login required."]));
}

include_once '../../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ============================================================
// GET REQUESTS - Data Retrieval
// ============================================================
if ($method === 'GET') {

    // --- DASHBOARD STATISTICS ---
    if ($action === 'stats') {
        try {
            $stats = [];

            // Total Users
            $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

            // Total Food Items
            $stats['total_foods'] = $pdo->query("SELECT COUNT(*) FROM foods WHERE is_active = 1")->fetchColumn();

            // Total Orders
            $stats['total_orders'] = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

            // Pending Orders
            $stats['pending_orders'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();

            // Total Revenue (from completed payments)
            $revenue = $pdo->query("SELECT SUM(amount) FROM payments WHERE payment_status = 'completed'")->fetchColumn();
            $stats['total_revenue'] = number_format(floatval($revenue), 2, '.', '');

            // Recent 5 Orders
            $recent = $pdo->query("
                SELECT o.order_id, u.full_name, o.total_amount, o.order_status, o.order_date
                FROM orders o JOIN users u ON o.user_id = u.user_id
                ORDER BY o.order_date DESC LIMIT 5
            ")->fetchAll();
            $stats['recent_orders'] = $recent;

            echo json_encode(["status" => "success", "data" => $stats]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to fetch statistics."]);
        }
    }

    // --- VIEW ALL USERS ---
    else if ($action === 'get_users') {
        try {
            $users = $pdo->query("
                SELECT user_id, full_name, email, phone, address, created_at 
                FROM users ORDER BY created_at DESC
            ")->fetchAll();
            echo json_encode(["status" => "success", "data" => $users]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to fetch users."]);
        }
    }

    // --- VIEW ALL FOOD ITEMS ---
    else if ($action === 'get_foods') {
        try {
            $foods = $pdo->query("
                SELECT * FROM foods ORDER BY created_at DESC
            ")->fetchAll();
            echo json_encode(["status" => "success", "data" => $foods]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to fetch food items."]);
        }
    }

    // --- VIEW ALL ORDERS ---
    else if ($action === 'get_orders') {
        try {
            $orders = $pdo->query("
                SELECT o.order_id, u.full_name, u.email, o.total_amount, 
                       o.order_status, o.delivery_address, o.order_date
                FROM orders o 
                JOIN users u ON o.user_id = u.user_id
                ORDER BY o.order_date DESC
            ")->fetchAll();
            echo json_encode(["status" => "success", "data" => $orders]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to fetch orders."]);
        }
    }

    // --- VIEW ORDER ITEMS (for a specific order) ---
    else if ($action === 'get_order_items') {
        $order_id = intval($_GET['order_id'] ?? 0);
        if ($order_id <= 0) {
            http_response_code(400);
            die(json_encode(["status" => "error", "message" => "Valid Order ID required."]));
        }
        try {
            $stmt = $pdo->prepare("
                SELECT oi.quantity, oi.price, f.title, f.image_name,
                       (oi.quantity * oi.price) as line_total
                FROM order_items oi
                JOIN foods f ON oi.food_id = f.food_id
                WHERE oi.order_id = :order_id
            ");
            $stmt->execute([':order_id' => $order_id]);
            echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to fetch order items."]);
        }
    }

    // --- VIEW ALL PAYMENT DETAILS ---
    else if ($action === 'get_payments') {
        try {
            $payments = $pdo->query("
                SELECT p.payment_id, p.transaction_id, p.amount, p.payment_method, 
                       p.payment_status, p.payment_date, u.full_name, u.email, o.order_id
                FROM payments p
                JOIN orders o ON p.order_id = o.order_id
                JOIN users u ON o.user_id = u.user_id
                ORDER BY p.payment_date DESC
            ")->fetchAll();
            echo json_encode(["status" => "success", "data" => $payments]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to fetch payments."]);
        }
    }

    else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
    }
}

// ============================================================
// POST REQUESTS - Data Modification
// ============================================================
else if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    $action = $data->action ?? $_POST['action'] ?? '';

    // --- UPDATE ORDER STATUS ---
    if ($action === 'update_order_status') {
        $order_id = intval($data->order_id ?? $_POST['order_id'] ?? 0);
        $new_status = trim($data->order_status ?? $_POST['order_status'] ?? '');
        $allowed_statuses = ['pending', 'processing', 'out_for_delivery', 'completed', 'cancelled'];

        if ($order_id <= 0 || !in_array($new_status, $allowed_statuses)) {
            http_response_code(400);
            die(json_encode(["status" => "error", "message" => "Valid Order ID and status are required."]));
        }

        try {
            $stmt = $pdo->prepare("UPDATE orders SET order_status = :status WHERE order_id = :id");
            $stmt->execute([':status' => $new_status, ':id' => $order_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["status" => "success", "message" => "Order #$order_id status updated to '$new_status'."]);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Order not found."]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to update order status."]);
        }
    }

    // --- DELETE ORDER ---
    else if ($action === 'delete_order') {
        $order_id = intval($data->order_id ?? $_POST['order_id'] ?? 0);

        if ($order_id <= 0) {
            http_response_code(400);
            die(json_encode(["status" => "error", "message" => "Valid Order ID is required."]));
        }

        try {
            // Foreign key CASCADE handles deleting order_items and payments automatically
            $stmt = $pdo->prepare("DELETE FROM orders WHERE order_id = :id");
            $stmt->execute([':id' => $order_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["status" => "success", "message" => "Order #$order_id deleted successfully."]);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Order not found."]);
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to delete order."]);
        }
    }

    // --- ADMIN LOGOUT ---
    else if ($action === 'logout') {
        session_unset();
        session_destroy();
        echo json_encode(["status" => "success", "message" => "Admin logged out successfully."]);
    }

    else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
    }
}
?>
