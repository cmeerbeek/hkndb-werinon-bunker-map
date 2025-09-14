<?php

/**
 * Authentication API Endpoint
 * Handles login/logout and session management
 */

require_once '../../includes/functions.php';

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendResponse(['status' => 'ok']);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'POST':
            handleLogin();
            break;
        case 'DELETE':
            handleLogout();
            break;
        case 'GET':
            handleCheckAuth();
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}

/**
 * POST /api/auth.php
 * Authenticates user with PIN and creates session
 */
function handleLogin() {
    // Get PIN from request body
    $input = json_decode(file_get_contents('php://input'), true);
    $pin = $input['pin'] ?? $_POST['pin'] ?? '';

    if (empty($pin)) {
        sendError('PIN is required');
    }

    // Validate PIN
    if ($pin !== ADMIN_PIN) {
        // Add a small delay to prevent brute force attacks
        sleep(1);
        sendError('Invalid PIN', 401);
    }

    // Create session
    $token = createSession();
    if (!$token) {
        sendError('Failed to create session', 500);
    }

    sendResponse([
        'success' => true,
        'token' => $token,
        'expires_in' => SESSION_DURATION,
        'message' => 'Authentication successful'
    ]);
}

/**
 * DELETE /api/auth.php
 * Logs out user by removing session
 */
function handleLogout() {
    $token = getSessionToken();

    if ($token) {
        removeSession($token);
    }

    sendResponse([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

/**
 * GET /api/auth.php
 * Checks if current session is valid
 */
function handleCheckAuth() {
    $token = getSessionToken();

    if (!$token) {
        sendResponse([
            'authenticated' => false,
            'message' => 'No session token provided'
        ]);
    }

    $isValid = validateSession($token);

    sendResponse([
        'authenticated' => $isValid,
        'message' => $isValid ? 'Session is valid' : 'Session is invalid or expired'
    ]);
}