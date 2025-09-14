<?php

/**
 * Markers API Endpoint
 * Handles CRUD operations for map markers
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
            handleGetMarkers();
            break;
        case 'POST':
            handleCreateMarker();
            break;
        case 'DELETE':
            handleDeleteMarkers();
            break;
        default:
            sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError('Server error: ' . $e->getMessage(), 500);
}

/**
 * GET /api/markers.php
 * Returns all markers with their photos
 */
function handleGetMarkers() {
    $markersData = loadJsonFile(MARKERS_FILE);
    if (!$markersData) {
        sendResponse([
            'counter' => 0,
            'markers' => []
        ]);
    }

    // Add photo URLs to markers
    foreach ($markersData['markers'] as &$marker) {
        if (isset($marker['photos'])) {
            foreach ($marker['photos'] as &$photo) {
                $photo['url'] = '/api/photos.php?file=' . urlencode($photo['filename']);
            }
        }
    }

    sendResponse($markersData);
}

/**
 * POST /api/markers.php
 * Creates a new marker with uploaded photos
 */
function handleCreateMarker() {
    // Require authentication for creating markers
    requireAuth();

    // Validate input
    $lat = $_POST['lat'] ?? null;
    $lng = $_POST['lng'] ?? null;

    if (!is_numeric($lat) || !is_numeric($lng)) {
        sendError('Invalid coordinates');
    }

    $lat = (float) $lat;
    $lng = (float) $lng;

    // Validate coordinate ranges
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        sendError('Coordinates out of range');
    }

    // Load current markers data
    $markersData = loadJsonFile(MARKERS_FILE);
    if (!$markersData) {
        $markersData = ['counter' => 0, 'markers' => []];
    }

    // Generate new marker ID
    $markersData['counter']++;
    $markerId = $markersData['counter'];

    // Process uploaded photos
    $photos = [];
    $uploadErrors = [];

    if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
        $fileCount = count($_FILES['photos']['name']);

        if ($fileCount > MAX_PHOTOS_PER_MARKER) {
            sendError('Maximum ' . MAX_PHOTOS_PER_MARKER . ' photos allowed per marker');
        }

        for ($i = 0; $i < $fileCount; $i++) {
            if (empty($_FILES['photos']['name'][$i])) {
                continue;
            }

            $file = [
                'name' => $_FILES['photos']['name'][$i],
                'type' => $_FILES['photos']['type'][$i],
                'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                'error' => $_FILES['photos']['error'][$i],
                'size' => $_FILES['photos']['size'][$i]
            ];

            $result = saveUploadedFile($file, $markerId);
            if ($result['success']) {
                $photos[] = [
                    'filename' => $result['filename'],
                    'original_name' => $result['original_name'],
                    'size' => $result['size']
                ];
            } else {
                $uploadErrors = array_merge($uploadErrors, $result['errors']);
            }
        }
    }

    // If there were upload errors but no photos were saved, return error
    if (!empty($uploadErrors) && empty($photos)) {
        sendError('Photo upload failed: ' . implode(', ', $uploadErrors));
    }

    // Create new marker
    $newMarker = [
        'id' => $markerId,
        'lat' => $lat,
        'lng' => $lng,
        'created_at' => date('Y-m-d\TH:i:s\Z'),
        'photos' => $photos
    ];

    // Add marker to data
    $markersData['markers'][] = $newMarker;

    // Save updated data
    if (!saveJsonFile(MARKERS_FILE, $markersData)) {
        sendError('Failed to save marker data', 500);
    }

    // Return created marker with photo URLs
    foreach ($newMarker['photos'] as &$photo) {
        $photo['url'] = '/api/photos.php?file=' . urlencode($photo['filename']);
    }

    $response = [
        'success' => true,
        'marker' => $newMarker,
        'message' => 'Marker created successfully'
    ];

    if (!empty($uploadErrors)) {
        $response['warnings'] = $uploadErrors;
    }

    sendResponse($response, 201);
}

/**
 * DELETE /api/markers.php
 * Deletes markers (specific marker by ID or all markers)
 */
function handleDeleteMarkers() {
    // Require authentication for deleting markers
    requireAuth();

    $markerId = $_GET['id'] ?? null;
    $deleteAll = $_GET['all'] ?? null;

    $markersData = loadJsonFile(MARKERS_FILE);
    if (!$markersData) {
        sendResponse(['success' => true, 'message' => 'No markers to delete']);
    }

    if ($deleteAll === '1') {
        // Delete all markers and photos
        deleteAllMarkersAndPhotos($markersData);

        // Reset markers data
        $markersData = ['counter' => 0, 'markers' => []];

        if (!saveJsonFile(MARKERS_FILE, $markersData)) {
            sendError('Failed to clear markers data', 500);
        }

        sendResponse([
            'success' => true,
            'message' => 'All markers cleared successfully'
        ]);
    } elseif ($markerId !== null) {
        // Delete specific marker
        $markerId = (int) $markerId;
        $markerIndex = null;
        $marker = null;

        // Find marker
        foreach ($markersData['markers'] as $index => $m) {
            if ($m['id'] === $markerId) {
                $markerIndex = $index;
                $marker = $m;
                break;
            }
        }

        if ($markerIndex === null) {
            sendError('Marker not found', 404);
        }

        // Delete marker photos
        deleteMarkerPhotos($marker);

        // Remove marker from data
        array_splice($markersData['markers'], $markerIndex, 1);

        if (!saveJsonFile(MARKERS_FILE, $markersData)) {
            sendError('Failed to delete marker', 500);
        }

        sendResponse([
            'success' => true,
            'message' => 'Marker deleted successfully'
        ]);
    } else {
        sendError('Missing marker ID or all parameter');
    }
}

/**
 * Delete all markers and their photos
 */
function deleteAllMarkersAndPhotos($markersData) {
    foreach ($markersData['markers'] as $marker) {
        deleteMarkerPhotos($marker);
    }
}

/**
 * Delete photos for a specific marker
 */
function deleteMarkerPhotos($marker) {
    if (!isset($marker['photos']) || !is_array($marker['photos'])) {
        return;
    }

    foreach ($marker['photos'] as $photo) {
        $filepath = UPLOADS_DIR . '/' . $photo['filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    // Try to remove marker directory if empty
    $markerDir = UPLOADS_DIR . '/' . $marker['id'];
    if (file_exists($markerDir) && is_dir($markerDir)) {
        $files = scandir($markerDir);
        if (count($files) <= 2) { // Only . and .. entries
            rmdir($markerDir);
        }
    }
}