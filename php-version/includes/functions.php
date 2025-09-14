<?php

/**
 * Utility functions for the Weesp Map Application
 */

// Include configuration
require_once dirname(__DIR__) . '/config/config.php';

/**
 * Send JSON response and exit
 */
function sendResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');

    // Add CORS headers
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array('*', ALLOWED_ORIGINS) || in_array($origin, ALLOWED_ORIGINS)) {
        header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    }
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');

    echo json_encode($data);
    exit;
}

/**
 * Send error response
 */
function sendError($message, $status = 400) {
    sendResponse(['error' => $message], $status);
}

/**
 * Initialize data directory structure
 */
function initializeDataStructure() {
    // Create data directory if it doesn't exist
    if (!file_exists(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }

    // Create uploads directory
    if (!file_exists(UPLOADS_DIR)) {
        mkdir(UPLOADS_DIR, 0755, true);
    }

    // Initialize markers.json if it doesn't exist
    if (!file_exists(MARKERS_FILE)) {
        $initialData = [
            'counter' => 0,
            'markers' => []
        ];
        saveJsonFile(MARKERS_FILE, $initialData);
    }

    // Initialize sessions.json if it doesn't exist
    if (!file_exists(SESSIONS_FILE)) {
        $initialData = [
            'active_sessions' => []
        ];
        saveJsonFile(SESSIONS_FILE, $initialData);
    }
}

/**
 * Load JSON file with file locking
 */
function loadJsonFile($filename) {
    if (!file_exists($filename)) {
        return null;
    }

    $handle = fopen($filename, 'r');
    if (!$handle) {
        return null;
    }

    if (flock($handle, LOCK_SH)) {
        $content = fread($handle, filesize($filename));
        flock($handle, LOCK_UN);
        fclose($handle);

        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }
    } else {
        fclose($handle);
    }

    return null;
}

/**
 * Save JSON file with atomic write and file locking
 */
function saveJsonFile($filename, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    // Create backup if file exists
    if (file_exists($filename)) {
        copy($filename, $filename . '.bak');
    }

    // Write to temporary file first
    $tempFile = $filename . '.tmp';
    $handle = fopen($tempFile, 'w');
    if (!$handle) {
        return false;
    }

    if (flock($handle, LOCK_EX)) {
        $result = fwrite($handle, $json);
        flock($handle, LOCK_UN);
        fclose($handle);

        if ($result !== false) {
            // Atomically move temp file to final location
            return rename($tempFile, $filename);
        }
    } else {
        fclose($handle);
    }

    // Clean up temp file on failure
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }

    return false;
}

/**
 * Generate secure random token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Validate session token
 */
function validateSession($token) {
    if (empty($token)) {
        return false;
    }

    $sessions = loadJsonFile(SESSIONS_FILE);
    if (!$sessions) {
        return false;
    }

    $currentTime = time();
    $validSessions = [];
    $tokenValid = false;

    // Check each session and clean up expired ones
    foreach ($sessions['active_sessions'] as $session) {
        $expiresAt = strtotime($session['expires']);
        if ($expiresAt > $currentTime) {
            $validSessions[] = $session;
            if ($session['token'] === $token) {
                $tokenValid = true;
            }
        }
    }

    // Save cleaned up sessions if any were removed
    if (count($validSessions) !== count($sessions['active_sessions'])) {
        $sessions['active_sessions'] = $validSessions;
        saveJsonFile(SESSIONS_FILE, $sessions);
    }

    return $tokenValid;
}

/**
 * Create new session
 */
function createSession() {
    $token = generateSecureToken();
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_DURATION);

    $sessions = loadJsonFile(SESSIONS_FILE);
    if (!$sessions) {
        $sessions = ['active_sessions' => []];
    }

    $sessions['active_sessions'][] = [
        'token' => $token,
        'expires' => $expiresAt
    ];

    if (saveJsonFile(SESSIONS_FILE, $sessions)) {
        return $token;
    }

    return false;
}

/**
 * Remove session
 */
function removeSession($token) {
    if (empty($token)) {
        return false;
    }

    $sessions = loadJsonFile(SESSIONS_FILE);
    if (!$sessions) {
        return true;
    }

    $filteredSessions = array_filter($sessions['active_sessions'], function($session) use ($token) {
        return $session['token'] !== $token;
    });

    if (count($filteredSessions) !== count($sessions['active_sessions'])) {
        $sessions['active_sessions'] = array_values($filteredSessions);
        return saveJsonFile(SESSIONS_FILE, $sessions);
    }

    return true;
}

/**
 * Validate file upload
 */
function validateFileUpload($file) {
    $errors = [];

    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        $errors[] = 'No file was uploaded';
        return $errors;
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'File is too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'File upload was interrupted';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errors[] = 'Server configuration error';
                break;
            default:
                $errors[] = 'Upload failed';
        }
        return $errors;
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = 'File is too large (max ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB)';
    }

    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        $errors[] = 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS);
    }

    // Validate file content (basic image validation)
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $errors[] = 'File is not a valid image';
    }

    return $errors;
}

/**
 * Save uploaded file
 */
function saveUploadedFile($file, $markerId) {
    $errors = validateFileUpload($file);
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Create marker directory
    $markerDir = UPLOADS_DIR . '/' . $markerId;
    if (!file_exists($markerDir)) {
        mkdir($markerDir, 0755, true);
    }

    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'photo_' . $markerId . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $markerDir . '/' . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $markerId . '/' . $filename,
            'original_name' => $file['name'],
            'size' => $file['size']
        ];
    }

    return ['success' => false, 'errors' => ['Failed to save file']];
}

/**
 * Get session token from headers
 */
function getSessionToken() {
    $headers = getallheaders();

    // Check Authorization header
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.*)$/i', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }

    // Check custom header
    if (isset($headers['X-Session-Token'])) {
        return $headers['X-Session-Token'];
    }

    // Check POST/GET parameter
    return $_POST['session_token'] ?? $_GET['session_token'] ?? null;
}

/**
 * Require authentication for protected endpoints
 */
function requireAuth() {
    $token = getSessionToken();
    if (!validateSession($token)) {
        sendError('Authentication required', 401);
    }
    return $token;
}

// Initialize data structure on include
initializeDataStructure();