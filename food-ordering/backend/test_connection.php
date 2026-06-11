<?php
/**
 * backend/test_connection.php
 * ===========================
 * Deployment Test Script — Run this ONCE after deploying to XAMPP.
 * Visit: http://localhost/food-ordering/backend/test_connection.php
 * DELETE or rename this file after confirming everything works!
 */

// Simple auth guard: only accessible from localhost
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('<h2>403 Forbidden: This script is only accessible from localhost.</h2>');
}

$results = [];

// ── 1. PHP Version Check ─────────────────────────────────────────────────────
$phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
$results[] = [
    'test'   => '1. PHP Version',
    'value'  => PHP_VERSION,
    'status' => $phpOk ? 'pass' : 'fail',
    'note'   => $phpOk ? 'OK (8.0+)' : 'Requires PHP 8.0 or higher'
];

// ── 2. PDO & MySQL Extension Check ──────────────────────────────────────────
$pdoOk = extension_loaded('pdo') && extension_loaded('pdo_mysql');
$results[] = [
    'test'   => '2. PDO MySQL Extension',
    'value'  => $pdoOk ? 'Loaded' : 'Missing',
    'status' => $pdoOk ? 'pass' : 'fail',
    'note'   => $pdoOk ? 'OK' : 'Enable pdo_mysql in php.ini'
];

// ── 3. Database Connection Test ──────────────────────────────────────────────
$dbStatus = 'fail';
$dbNote   = '';
$tableResults = [];

try {
    require_once 'config/db.php'; // Uses the PDO config

    $pdo->query("SELECT 1"); // Quick ping
    $dbStatus = 'pass';
    $dbNote   = 'Connected to food_ordering_db successfully!';

    // ── 4. Table Existence Checks ────────────────────────────────────────────
    $requiredTables = ['admin', 'users', 'foods', 'cart', 'orders', 'order_items', 'payments'];
    foreach ($requiredTables as $table) {
        $stmt  = $pdo->query("SHOW TABLES LIKE '$table'");
        $found = $stmt->rowCount() > 0;
        $tableResults[] = [
            'test'   => "Table: $table",
            'value'  => $found ? 'Exists' : 'Missing',
            'status' => $found ? 'pass' : 'fail',
            'note'   => $found ? '✓' : 'Run backend/database.sql in phpMyAdmin'
        ];
    }

    // ── 5. Record Count Summary ──────────────────────────────────────────────
    $counts = [
        'admin'   => $pdo->query("SELECT COUNT(*) FROM admin")->fetchColumn(),
        'users'   => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'foods'   => $pdo->query("SELECT COUNT(*) FROM foods")->fetchColumn(),
        'orders'  => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    ];
    foreach ($counts as $tbl => $cnt) {
        $tableResults[] = [
            'test'   => "Records in $tbl",
            'value'  => $cnt,
            'status' => 'info',
            'note'   => ''
        ];
    }

} catch (Exception $e) {
    $dbNote = 'Connection FAILED: ' . htmlspecialchars($e->getMessage());
}

$results[] = [
    'test'   => '3. Database Connection',
    'value'  => $dbStatus === 'pass' ? 'Connected' : 'Failed',
    'status' => $dbStatus,
    'note'   => $dbNote
];

$results = array_merge($results, $tableResults);

// ── 6. Uploads Directory Check ───────────────────────────────────────────────
$uploadDir = __DIR__ . '/uploads/foods/';
$uploadOk  = is_dir($uploadDir) && is_writable($uploadDir);
if (!$uploadOk && !is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    $uploadOk = is_writable($uploadDir);
}
$results[] = [
    'test'   => '6. Uploads Directory',
    'value'  => $uploadDir,
    'status' => $uploadOk ? 'pass' : 'fail',
    'note'   => $uploadOk ? 'Writable ✓' : 'Not writable — chmod 777 the folder'
];

// ── 7. Session Test ──────────────────────────────────────────────────────────
session_start();
$_SESSION['deploy_test'] = true;
$sessionOk = isset($_SESSION['deploy_test']);
$results[] = [
    'test'   => '7. PHP Sessions',
    'value'  => $sessionOk ? 'Working' : 'Failed',
    'status' => $sessionOk ? 'pass' : 'fail',
    'note'   => $sessionOk ? 'OK' : 'Check session.save_path in php.ini'
];

// ── Render HTML Report ───────────────────────────────────────────────────────
$allPass = count(array_filter($results, fn($r) => $r['status'] === 'fail')) === 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Foodie — Deployment Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; font-family: 'Poppins', sans-serif; }
        .badge-pass { background: #28a745; }
        .badge-fail { background: #dc3545; }
        .badge-info { background: #0d6efd; }
    </style>
</head>
<body class="p-5">
    <div class="container" style="max-width: 800px;">
        <div class="text-center mb-5">
            <h2 class="fw-bold"><i class="fas fa-utensils text-danger me-2"></i>Foodie Deployment Test</h2>
            <p class="text-muted">Verify your XAMPP + MySQL setup is ready</p>
            <div class="alert alert-<?= $allPass ? 'success' : 'danger' ?> fw-bold fs-5">
                <?= $allPass ? '✅ All tests passed! Your deployment is ready.' : '❌ Some tests failed. Fix the issues above.' ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Test</th>
                            <th>Result</th>
                            <th>Status</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($r['test']) ?></td>
                            <td><code><?= htmlspecialchars((string)$r['value']) ?></code></td>
                            <td><span class="badge badge-<?= $r['status'] ?>"><?= strtoupper($r['status']) ?></span></td>
                            <td class="text-muted small"><?= htmlspecialchars($r['note']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-warning mt-4">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Security:</strong> Delete or rename <code>backend/test_connection.php</code> after confirming your deployment is working.
        </div>

        <div class="card border-0 shadow-sm rounded-4 mt-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">📋 Quick API Test Links</h5>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><a href="/food-ordering/backend/api/admin/food_api.php" target="_blank"><code>GET /backend/api/admin/food_api.php</code></a> — Fetch food menu</li>
                    <li class="list-group-item"><a href="/food-ordering/backend/api/cart_api.php" target="_blank"><code>GET /backend/api/cart_api.php</code></a> — Fetch cart (requires login)</li>
                    <li class="list-group-item"><a href="/food-ordering/backend/api/admin/dashboard_api.php?action=stats" target="_blank"><code>GET /backend/api/admin/dashboard_api.php?action=stats</code></a> — Admin stats (requires admin session)</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
