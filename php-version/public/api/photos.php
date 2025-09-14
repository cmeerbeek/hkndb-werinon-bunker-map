<?php

/**
 * Photos API Endpoint
 * Handles photo serving and upload operations
 */

require_once '../../includes/functions.php';

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendResponse(['status' => 'ok']);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleServePhoto();
            break;
        case 'POST':
            handleUploadPhoto();
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}

/**
 * GET /api/photos.php?file=path
 * Serves photo files with proper headers
 */
function handleServePhoto() {
    $filename = $_GET['file'] ?? '';

    if (empty($filename)) {
        sendError('Missing file parameter', 400);
    }

    // Sanitize filename to prevent path traversal
    $filename = str_replace(['..', '/', '\\'], '', $filename);
    $filepath = UPLOADS_DIR . '/' . $filename;

    // Verify file exists and is within uploads directory
    $realPath = realpath($filepath);
    $uploadsPath = realpath(UPLOADS_DIR);

    if (!$realPath || !$uploadsPath || strpos($realPath, $uploadsPath) !== 0) {
        http_response_code(404);
        exit('File not found');
    }

    if (!file_exists($realPath) || !is_file($realPath)) {
        http_response_code(404);
        exit('File not found');
    }

    // Get file info
    $fileInfo = pathinfo($realPath);
    $extension = strtolower($fileInfo['extension']);

    // Set appropriate content type
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];

    $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';

    // Set headers
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($realPath));
    header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($realPath)) . ' GMT');

    // Add CORS headers
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array('*', ALLOWED_ORIGINS) || in_array($origin, ALLOWED_ORIGINS)) {
        header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    }

    // Check if client has cached version
    $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
    if ($ifModifiedSince && strtotime($ifModifiedSince) >= filemtime($realPath)) {
        http_response_code(304);
        exit();
    }

    // Serve file
    readfile($realPath);
    exit();
}

/**
 * POST /api/photos.php
 * Handles standalone photo uploads (used during marker creation)
 */
function handleUploadPhoto() {
    // This endpoint is primarily used by the markers endpoint
    // But can be used for other photo operations if needed
    requireAuth();

    if (!isset($_FILES['photo']) || empty($_FILES['photo']['name'])) {
        sendError('No photo uploaded');
    }

    $markerId = $_POST['marker_id'] ?? null;
    if (!$markerId) {
        sendError('Missing marker_id parameter');
    }

    $result = saveUploadedFile($_FILES['photo'], $markerId);
    if (!$result['success']) {
        sendError('Upload failed: ' . implode(', ', $result['errors']));
    }

    $photo = [
        'filename' => $result['filename'],
        'original_name' => $result['original_name'],
        'size' => $result['size'],
        'url' => '/api/photos.php?file=' . urlencode($result['filename'])
    ];

    sendResponse([
        'success' => true,
        'photo' => $photo,
        'message' => 'Photo uploaded successfully'
    ], 201);
}