<?php

// Application Configuration
define('APP_NAME', 'Weesp Area Interactive Map');
define('APP_VERSION', '2.0');

// File paths (relative to project root)
define('DATA_DIR', dirname(__DIR__) . '/data');
define('UPLOADS_DIR', DATA_DIR . '/uploads');
define('MARKERS_FILE', DATA_DIR . '/markers.json');
define('SESSIONS_FILE', DATA_DIR . '/sessions.json');
define('CONFIG_FILE', DATA_DIR . '/config.json');

// Authentication settings
define('ADMIN_PIN', '1234'); // Change this to your desired PIN
define('SESSION_DURATION', 3600); // Session duration in seconds (1 hour)

// Upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('MAX_PHOTOS_PER_MARKER', 2);

// Map settings
define('DEFAULT_LAT', 52.3086);
define('DEFAULT_LNG', 5.0408);
define('DEFAULT_ZOOM', 13);

// CORS settings
define('ALLOWED_ORIGINS', ['*']); // In production, specify your domain

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('Europe/Amsterdam');