<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$data_file = '../data/markers.json';
$uploads_dir = '../uploads/';

// Ensure uploads directory exists
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}

function loadMarkers() {
    global $data_file;
    if (!file_exists($data_file)) {
        return [];
    }
    $content = file_get_contents($data_file);
    return json_decode($content, true) ?: [];
}

function saveMarkers($markers) {
    global $data_file;
    return file_put_contents($data_file, json_encode($markers, JSON_PRETTY_PRINT));
}

function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function generateId() {
    return uniqid('marker_', true);
}

function validateImage($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($file['type'], $allowed_types)) {
        return 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.';
    }

    if ($file['size'] > $max_size) {
        return 'File size too large. Maximum 5MB allowed.';
    }

    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        return 'Invalid image file.';
    }

    return null;
}

function uploadImage($file, $marker_id, $photo_number) {
    global $uploads_dir;

    $validation_error = validateImage($file);
    if ($validation_error) {
        return ['success' => false, 'message' => $validation_error];
    }

    $marker_dir = $uploads_dir . $marker_id . '/';
    if (!is_dir($marker_dir)) {
        mkdir($marker_dir, 0755, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'photo' . $photo_number . '.' . $extension;
    $filepath = $marker_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => 'uploads/' . $marker_id . '/' . $filename
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to save image'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get all markers
    $markers = loadMarkers();

    // Convert relative paths to full URLs for frontend
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
                '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/../';

    foreach ($markers as &$marker) {
        if (isset($marker['photos'])) {
            foreach ($marker['photos'] as &$photo) {
                if (!filter_var($photo, FILTER_VALIDATE_URL)) {
                    $photo = $base_url . $photo;
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'markers' => $markers
    ]);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new marker
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        exit;
    }

    // Validate required fields
    if (!isset($_POST['title'], $_POST['lat'], $_POST['lng'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: title, lat, lng'
        ]);
        exit;
    }

    if (!isset($_FILES['photo1'], $_FILES['photo2'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Two photos are required'
        ]);
        exit;
    }

    $title = trim($_POST['title']);
    $lat = floatval($_POST['lat']);
    $lng = floatval($_POST['lng']);

    if (empty($title)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Title cannot be empty'
        ]);
        exit;
    }

    if (abs($lat) > 90 || abs($lng) > 180) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid coordinates'
        ]);
        exit;
    }

    // Generate unique marker ID
    $marker_id = generateId();

    // Upload photos
    $photo_paths = [];
    $upload_errors = [];

    for ($i = 1; $i <= 2; $i++) {
        $photo_key = 'photo' . $i;
        if (isset($_FILES[$photo_key]) && $_FILES[$photo_key]['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadImage($_FILES[$photo_key], $marker_id, $i);
            if ($upload_result['success']) {
                $photo_paths[] = $upload_result['path'];
            } else {
                $upload_errors[] = "Photo {$i}: " . $upload_result['message'];
            }
        } else {
            $upload_errors[] = "Photo {$i}: Upload failed";
        }
    }

    if (!empty($upload_errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => implode('. ', $upload_errors)
        ]);
        exit;
    }

    // Create marker object
    $marker = [
        'id' => $marker_id,
        'title' => $title,
        'lat' => $lat,
        'lng' => $lng,
        'photos' => $photo_paths,
        'created_at' => date('c'),
        'created_by' => $_SERVER['REMOTE_ADDR']
    ];

    // Load existing markers and add new one
    $markers = loadMarkers();
    $markers[] = $marker;

    if (saveMarkers($markers)) {
        // Convert paths to URLs for response
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
                    '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/../';

        $marker['photos'] = array_map(function($photo) use ($base_url) {
            return $base_url . $photo;
        }, $marker['photos']);

        echo json_encode([
            'success' => true,
            'message' => 'Marker added successfully',
            'marker' => $marker
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save marker'
        ]);
    }

} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>