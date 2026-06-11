<?php
// backend/api/admin/admin_login.php
session_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json; charset=UTF-8");

include_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(["status" => "error", "message" => "Only POST requests are allowed."]));
}

$data = json_decode(file_get_contents("php://input"));
$username = trim($data->username ?? $_POST['username'] ?? '');
$password = $data->password ?? $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "Username and password are required."]));
}

try {
    $stmt = $pdo->prepare("SELECT admin_id, username, password FROM admin WHERE username = :username LIMIT 1");
    $stmt->bindParam(":username", $username);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $admin = $stmt->fetch();

        if (password_verify($password, $admin['password'])) {
            // Secure session setup
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_logged_in'] = true;

            http_response_code(200);
            echo json_encode(["status" => "success", "message" => "Admin login successful!"]);
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Invalid credentials."]);
        }
    } else {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid credentials."]);
    }
} catch (PDOException $e) {
    error_log("Admin Login Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Internal server error."]);
}
?>
