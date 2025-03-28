<?php
session_start();
header('Content-Type: application/json');

// Include database connection
require_once '../config/db.php';

// Rate limiting
$_SESSION['login_attempts'] = isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] : 0;
$_SESSION['last_login_attempt'] = isset($_SESSION['last_login_attempt']) ? $_SESSION['last_login_attempt'] : 0;

// Check rate limiting
if (time() - $_SESSION['last_login_attempt'] > 3600) {
    $_SESSION['login_attempts'] = 0;
}

if ($_SESSION['login_attempts'] >= 5 && time() - $_SESSION['last_login_attempt'] < 3600) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please try again later.']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['username']) || !isset($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

$username = filter_var($data['username'], FILTER_SANITIZE_STRING);
$password = $data['password'];

// Validate input
if (strlen($username) < 3 || strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

try {
    // Check if user exists
    $stmt = $conn->prepare('SELECT id, username, password FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Reset login attempts on successful login
        $_SESSION['login_attempts'] = 0;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        echo json_encode(['success' => true]);
    } else {
        // Increment login attempts
        $_SESSION['login_attempts']++;
        $_SESSION['last_login_attempt'] = time();
        
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
} catch (PDOException $e) {
    error_log('Login error - Details: ' . $e->getMessage() . ' | SQL State: ' . $e->getCode());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}