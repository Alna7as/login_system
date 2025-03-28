<?php
session_start();
header('Content-Type: application/json');

require_once '../config/db.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    // Check if email exists
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email not found']);
        exit;
    }

    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store reset token
    $stmt = $conn->prepare('UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?');
    $stmt->execute([$token, $expiry, $email]);

    // In a production environment, send email with reset link
    // For demo purposes, we'll just return the token
    echo json_encode([
        'success' => true,
        'message' => 'Password reset instructions sent to your email',
        'debug_token' => $token // Remove this in production
    ]);

} catch(PDOException $e) {
    error_log('Password reset error: ' . $e->getMessage());
    http_response_code(500);
    if ($e->getCode() == '42S22') {
        echo json_encode(['success' => false, 'message' => 'Database schema error. Please contact administrator.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.']);
    }
}
?>