<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['pincode'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Pincode is required']);
    exit;
}

$pincode = trim($input['pincode']);

// Set your access code here
$correct_pincode = '1234';

if ($pincode === $correct_pincode) {
    $_SESSION['authenticated'] = true;
    $_SESSION['auth_time'] = time();

    echo json_encode([
        'success' => true,
        'message' => 'Authentication successful'
    ]);
} else {
    // Rate limiting: simple attempt tracking
    if (!isset($_SESSION['auth_attempts'])) {
        $_SESSION['auth_attempts'] = 0;
        $_SESSION['last_attempt'] = time();
    }

    $_SESSION['auth_attempts']++;
    $_SESSION['last_attempt'] = time();

    // Block for 5 minutes after 5 failed attempts
    if ($_SESSION['auth_attempts'] >= 5 && (time() - $_SESSION['last_attempt']) < 300) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many failed attempts. Please try again later.'
        ]);
        exit;
    }

    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid access code'
    ]);
}
?>