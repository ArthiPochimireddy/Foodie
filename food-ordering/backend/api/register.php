<?php
// backend/api/register.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../config/db.php';
include_once '../config/security.php';

initSecureSession();
requireMethod('POST');

// Rate limit: max 10 registration attempts per 10 minutes per session
checkRateLimit('register_attempts', 10, 600);

// Read and sanitize inputs
$full_name = sanitizeString(getInput('full_name'));
$email     = validateEmail(getInput('email'));
$password  = getInput('password');
$phone     = sanitizePhone(getInput('phone'));
$address   = sanitizeString(getInput('address'));


// Validate required fields
if (empty($full_name) || !$email || empty($password)) {
    sendError(400, "Full name, a valid email, and password are required.");
}

// Validate password strength
$pwdErrors = validatePassword($password);
if (!empty($pwdErrors)) {
    sendError(400, implode(' ', $pwdErrors));
}


try {
    // 2. Prevent Duplicate Accounts (Check if email already exists)
    $check_query = "SELECT user_id FROM users WHERE email = :email LIMIT 1";
    $stmt = $pdo->prepare($check_query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(["status" => "error", "message" => "An account with this email already exists."]);
        exit();
    }

    // 3. Secure Password Hashing
    $hashed_password = hashPassword($password);

    // 4. Store user details in database
    $insert_query = "INSERT INTO users (full_name, email, password, phone, address) 
                     VALUES (:full_name, :email, :password, :phone, :address)";
    
    $stmt = $pdo->prepare($insert_query);
    
    // Bind parameters to prevent SQL Injection
    $stmt->bindParam(":full_name", $full_name);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":password", $hashed_password);
    $stmt->bindParam(":phone", $phone);
    $stmt->bindParam(":address", $address);
    
    if ($stmt->execute()) {
        sendSuccess("Registration successful! You can now log in.", [], 201);
    } else {
        sendError(503, "Unable to register. Please try again.", "DB execute failed on user insert.");
    }
} catch (PDOException $e) {
    sendError(500, "Internal server error. Please try again later.", $e->getMessage());
}
?>
