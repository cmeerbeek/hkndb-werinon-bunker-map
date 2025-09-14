<?php
require_once '../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WO2 bunker map | Historische Kring Nederhorst den Berg</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Authentication Modal -->
    <div id="authOverlay" class="auth-overlay hidden">
        <div class="auth-modal">
            <h2>🔐 Authentication Required</h2>
            <p>Enter your PIN to add markers to the map:</p>
            <input type="password" id="pinInput" placeholder="Enter PIN" maxlength="6">
            <div id="authError" class="auth-error hidden">Incorrect PIN. Try again.</div>
            <button id="loginBtn" class="btn">Login</button>
            <button id="cancelLoginBtn" class="btn clear">Cancel</button>
        </div>
    </div>

    <!-- Photo Overlay Modal -->
    <div id="photoOverlay" class="photo-overlay hidden">
        <div class="photo-overlay-content">
            <button class="photo-overlay-close" id="photoOverlayClose">×</button>
            <img id="photoOverlayImg" src="" alt="Full size photo">
            <div class="photo-overlay-info" id="photoOverlayInfo"></div>
        </div>
    </div>

    <!-- Photo Upload Modal -->
    <div id="photoUploadModal" class="photo-upload-modal hidden">
        <div class="upload-modal-content">
            <h3>📸 Add Photos to Marker</h3>
            <p>Upload up to 2 photos for this location:</p>

            <div class="photo-upload-area" id="photoUploadArea">
                <p>📁 Drag & drop photos here or click to select</p>
                <p><small>Supports: JPG, PNG, GIF (max 5MB each)</small></p>
            </div>

            <input type="file" id="photoInput" accept="image/*" multiple style="display: none;">

            <div id="photoPreview" class="photo-preview"></div>

            <div class="modal-actions">
                <button id="savePhotos" class="btn">💾 Save Marker</button>
                <button id="cancelUpload" class="btn clear">❌ Cancel</button>
            </div>
        </div>
    </div>

    <div class="header">
        <h1>Weesp Area Interactive Map</h1>
        <p>View existing markers or login to add new photo markers</p>
    </div>

    <div class="controls">
        <div class="control-group">
            <button id="loginToAddBtn" class="btn">🔐 Login to Add Markers</button>
        </div>
        <div class="control-group auth-only hidden">
            <button id="toggleMode" class="btn">🎯 Add Marker Mode: OFF</button>
        </div>
        <div class="control-group auth-only hidden">
            <button id="clearMarkers" class="btn clear">🗑️ Clear All Markers</button>
        </div>
        <div class="control-group auth-only hidden">
            <button id="logoutBtn" class="btn logout">🚪 Logout</button>
        </div>
        <div class="control-group">
            <span class="marker-count">Markers: <span id="markerCount">0</span></span>
        </div>
    </div>

    <div class="map-container">
        <div id="map"></div>
    </div>

    <div id="status" class="status"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script src="assets/js/map-app.js"></script>
</body>
</html>