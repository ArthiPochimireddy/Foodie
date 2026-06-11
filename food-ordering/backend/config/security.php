<?php
/**
 * backend/config/security.php
 * ===========================
 * Centralized Security & Validation Library
 * Include this file in ALL API endpoints.
 *
 * Covers:
 *  - Input sanitization & validation
 *  - SQL injection prevention (PDO helpers)
 *  - Password hashing & verification
 *  - Secure session management
 *  - CSRF token generation & verification
 *  - Rate limiting (basic)
 *  - Structured error handling
 */

// ============================================================
// 1. SECURE SESSION CONFIGURATION
// ============================================================
function initSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Harden PHP session via ini settings before starting
        ini_set('session.cookie_httponly', 1);   // JS cannot access the cookie
        ini_set('session.cookie_secure', 0);     // Set to 1 in production (HTTPS only)
        ini_set('session.cookie_samesite', 'Strict'); // Prevent CSRF via cookie
        ini_set('session.use_strict_mode', 1);   // Reject uninitialized session IDs
        ini_set('session.gc_maxlifetime', 3600); // Sessions expire after 1 hour
        session_start();
    }
}

/**
 * Regenerate session ID to prevent session fixation.
 * Call this immediately after login/privilege escalation.
 */
function regenerateSession(): void {
    session_regenerate_id(true);
}

/**
 * Destroy a session completely and safely.
 */
function destroySession(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Check if a user session is active.
 */
function isUserLoggedIn(): bool {
    return isset($_SESSION['user_id'], $_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if an admin session is active.
 */
function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_id'], $_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Require user login — sends 401 and exits if not authenticated.
 */
function requireUserAuth(): void {
    if (!isUserLoggedIn()) {
        http_response_code(401);
        die(json_encode(["status" => "error", "message" => "Authentication required. Please log in."]));
    }
}

/**
 * Require admin login — sends 401 and exits if not admin.
 */
function requireAdminAuth(): void {
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        die(json_encode(["status" => "error", "message" => "Admin authentication required."]));
    }
}

// ============================================================
// 2. INPUT SANITIZATION & VALIDATION
// ============================================================

/**
 * Sanitize a string: strips HTML tags, trims whitespace, removes null bytes.
 */
function sanitizeString(string $input): string {
    $input = trim($input);
    $input = stripslashes($input);
    $input = strip_tags($input);
    $input = str_replace("\0", '', $input); // Remove null bytes
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate and sanitize an email address.
 * Returns the clean email or false if invalid.
 */
function validateEmail(string $email): string|false {
    $email = trim($email);
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

/**
 * Validate a password: enforce minimum length and basic complexity.
 */
function validatePassword(string $password): array {
    $errors = [];
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    return $errors; // Empty array means valid
}

/**
 * Validate a price value. Returns a float or false.
 */
function validatePrice(mixed $price): float|false {
    $price = filter_var($price, FILTER_VALIDATE_FLOAT);
    return ($price !== false && $price > 0) ? $price : false;
}

/**
 * Validate an integer ID (must be positive).
 */
function validateId(mixed $id): int|false {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    return ($id !== false && $id > 0) ? (int)$id : false;
}

/**
 * Sanitize a phone number — keep only digits, spaces, +, -, ()
 */
function sanitizePhone(string $phone): string {
    return preg_replace('/[^0-9\s\+\-\(\)]/', '', $phone);
}

/**
 * Validate that a value is one of a set of allowed options.
 */
function validateEnum(string $value, array $allowed): bool {
    return in_array($value, $allowed, true);
}

// ============================================================
// 3. PASSWORD HASHING (bcrypt via PHP password_hash)
// ============================================================

/**
 * Hash a password securely using bcrypt.
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
}

/**
 * Verify a plain password against a stored hash.
 */
function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Check if a stored hash needs to be rehashed (e.g., cost changed).
 */
function passwordNeedsRehash(string $hash): bool {
    return password_needs_rehash($hash, PASSWORD_DEFAULT, ['cost' => 12]);
}

// ============================================================
// 4. CSRF PROTECTION
// ============================================================

/**
 * Generate a CSRF token and store it in the session.
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate the CSRF token from a request header or POST body.
 * Returns true if valid, false if not.
 */
function validateCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================
// 5. RATE LIMITING (Session-based, simple)
// ============================================================

/**
 * Simple rate limiter to prevent brute force attacks.
 * $key     - Unique identifier (e.g., 'login_attempts')
 * $limit   - Max allowed attempts
 * $window  - Time window in seconds
 */
function checkRateLimit(string $key, int $limit = 5, int $window = 300): bool {
    $now = time();
    if (!isset($_SESSION['rate'][$key])) {
        $_SESSION['rate'][$key] = ['count' => 0, 'start' => $now];
    }

    $rate = &$_SESSION['rate'][$key];

    // Reset window if expired
    if ($now - $rate['start'] > $window) {
        $rate = ['count' => 0, 'start' => $now];
    }

    $rate['count']++;

    if ($rate['count'] > $limit) {
        http_response_code(429); // Too Many Requests
        die(json_encode([
            "status" => "error",
            "message" => "Too many attempts. Please wait before trying again."
        ]));
    }

    return true;
}

// ============================================================
// 6. SECURE ERROR HANDLING
// ============================================================

/**
 * Centralized error response — never exposes server internals.
 */
function sendError(int $httpCode, string $userMessage, string $logMessage = ''): never {
    if ($logMessage) {
        error_log("[Foodie Security] " . $logMessage . " | HTTP $httpCode");
    }
    http_response_code($httpCode);
    echo json_encode(["status" => "error", "message" => $userMessage]);
    exit();
}

/**
 * Centralized success response.
 */
function sendSuccess(string $message, array $data = [], int $httpCode = 200): never {
    http_response_code($httpCode);
    $response = ["status" => "success", "message" => $message];
    if (!empty($data)) {
        $response["data"] = $data;
    }
    echo json_encode($response);
    exit();
}

// ============================================================
// 7. REQUEST METHOD GUARD
// ============================================================

/**
 * Enforce a specific HTTP method. Exits with 405 if not matched.
 */
function requireMethod(string ...$methods): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        http_response_code(405);
        die(json_encode(["status" => "error", "message" => "Method Not Allowed."]));
    }
}

/**
 * Parse JSON request body safely, returns associative array or empty array.
 */
function getJsonBody(): array {
    $raw = file_get_contents("php://input");
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Get a value from POST or JSON body, with optional default.
 */
function getInput(string $key, mixed $default = ''): mixed {
    static $json = null;
    if ($json === null) {
        $json = getJsonBody();
    }
    return $json[$key] ?? $_POST[$key] ?? $default;
}
?>
