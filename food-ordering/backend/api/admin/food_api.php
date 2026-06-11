<?php
// backend/api/admin/food_api.php
session_start();

// Security: In a real app, verify admin session here
// if (!isset($_SESSION['admin_logged_in'])) {
//     http_response_code(401);
//     die(json_encode(["status" => "error", "message" => "Unauthorized access."]));
// }

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../config/db.php';

// Define the uploads directory
$upload_dir = '../../uploads/foods/';

// Ensure upload directory exists
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET requests (Fetch foods)
if ($method === 'GET') {
    try {
        $query = "SELECT * FROM foods ORDER BY created_at DESC";
        $stmt = $pdo->query($query);
        $foods = $stmt->fetchAll();
        
        echo json_encode(["status" => "success", "data" => $foods]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to fetch foods."]);
    }
    exit();
}

// Handle POST requests (Add, Edit, Delete)
if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // --- ADD FOOD ITEM ---
    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $image_name = '';

        if (empty($title) || $price <= 0) {
            http_response_code(400);
            die(json_encode(["status" => "error", "message" => "Valid Title and Price are required."]));
        }

        // Image Upload Logic
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($file_ext, $allowed_exts)) {
                $image_name = 'food_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
            }
        }

        try {
            $query = "INSERT INTO foods (title, description, price, category, image_name) VALUES (:title, :description, :price, :category, :image_name)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':price' => $price,
                ':category' => $category,
                ':image_name' => $image_name
            ]);
            
            echo json_encode(["status" => "success", "message" => "Food item added successfully!"]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error adding food."]);
        }
    }
    
    // --- UPDATE FOOD ITEM ---
    else if ($action === 'update') {
        $food_id = intval($_POST['food_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
        
        if ($food_id <= 0 || empty($title)) {
            http_response_code(400);
            die(json_encode(["status" => "error", "message" => "Valid Food ID and Title required."]));
        }

        try {
            // First, get current image name in case we need to delete it
            $stmt = $pdo->prepare("SELECT image_name FROM foods WHERE food_id = ?");
            $stmt->execute([$food_id]);
            $current_food = $stmt->fetch();
            
            $image_name = $current_food['image_name'] ?? '';

            // Handle new image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($file_ext, $allowed_exts)) {
                    $new_image_name = 'food_' . time() . '_' . rand(1000, 9999) . '.' . $file_ext;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_image_name)) {
                        // Delete old image
                        if (!empty($image_name) && file_exists($upload_dir . $image_name)) {
                            unlink($upload_dir . $image_name);
                        }
                        $image_name = $new_image_name;
                    }
                }
            }

            $query = "UPDATE foods SET title = :title, description = :description, price = :price, category = :category, image_name = :image_name, is_active = :is_active WHERE food_id = :food_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':title' => $title,
                ':description' => $description,
                ':price' => $price,
                ':category' => $category,
                ':image_name' => $image_name,
                ':is_active' => $is_active,
                ':food_id' => $food_id
            ]);
            
            echo json_encode(["status" => "success", "message" => "Food item updated successfully!"]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error updating food."]);
        }
    }
    
    // --- DELETE FOOD ITEM ---
    else if ($action === 'delete') {
        $food_id = intval($_POST['food_id'] ?? 0);
        
        if ($food_id <= 0) {
            http_response_code(400);
            die(json_encode(["status" => "error", "message" => "Invalid Food ID."]));
        }

        try {
            // Get image name to delete the physical file
            $stmt = $pdo->prepare("SELECT image_name FROM foods WHERE food_id = ?");
            $stmt->execute([$food_id]);
            $food = $stmt->fetch();

            if ($food) {
                // Delete from DB
                $delete_stmt = $pdo->prepare("DELETE FROM foods WHERE food_id = ?");
                $delete_stmt->execute([$food_id]);
                
                // Unlink physical file
                if (!empty($food['image_name']) && file_exists($upload_dir . $food['image_name'])) {
                    unlink($upload_dir . $food['image_name']);
                }
                
                echo json_encode(["status" => "success", "message" => "Food item deleted successfully!"]);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Food item not found."]);
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Database error deleting food."]);
        }
    }
    
    else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid action specified."]);
    }
}
?>
