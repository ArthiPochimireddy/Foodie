<?php
// backend/api/login.php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/db.php';
include_once '../config/security.php';

initSecureSession();
requireMethod('POST');

// Rate limit: max 5 login attempts per 5 minutes (brute-force protection)
checkRateLimit('login_attempts', 5, 300);

// Sanitize and read inputs
$email    = validateEmail(getInput('email'));
$password = getInput('password');

// Validate inputs
if (!$email || empty($password)) {
    sendError(400, "A valid email and password are required.");
}

try {
    // Fetch user — use a prepared statement (SQL injection prevention)
    $stmt = $pdo->prepare("SELECT user_id, full_name, password FROM users WHERE email = :email LIMIT 1");
    $stmt->bindParam(":email", $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();

        // Verify password against bcrypt hash
        if (verifyPassword($password, $user['password'])) {

            // Rehash password if the cost factor has changed
            if (passwordNeedsRehash($user['password'])) {
                $newHash = hashPassword($password);
                $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?")->execute([$newHash, $user['user_id']]);
            }

            // Secure session setup
            regenerateSession();
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['full_name'] = sanitizeString($user['full_name']);
            $_SESSION['email']     = $email;
            $_SESSION['logged_in'] = true;

            // Reset login rate limit on success
            unset($_SESSION['rate']['login_attempts']);

            // Detect AJAX / JSON request vs standard form submission
            $is_json = str_contains(getInput('_', $_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')
                    || json_decode(file_get_contents("php://input")) !== null;

            if ($is_json || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
                sendSuccess("Login successful!", [
                    "user" => ["id" => $user['user_id'], "name" => $user['full_name']]
                ]);
            } else {
                header("Location: ../../index.html");
                exit();
            }

        } else {
            // Same message for wrong password AND email-not-found to prevent enumeration
            sendError(401, "Invalid email or password.", "Failed login attempt for email: $email");
        }
    } else {
        sendError(401, "Invalid email or password.", "Login attempt for non-existent email: $email");
    }

} catch (PDOException $e) {
    sendError(500, "Internal server error. Please try again later.", $e->getMessage());
}
?>
