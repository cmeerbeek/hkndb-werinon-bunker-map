# Weesp Interactive Map - PHP Version

A PHP-based interactive map application for managing photo markers with filesystem-based storage (no database required).

## Features

- Interactive Leaflet.js map with OpenStreetMap tiles
- User authentication with PIN-based login
- Add photo markers with up to 2 photos per marker
- Photo overlay for full-size viewing
- Responsive design for mobile and desktop
- File-based storage (JSON + photo files)
- RESTful API endpoints
- Session-based authentication

## Requirements

- PHP 7.4 or higher
- Web server with mod_rewrite (Apache) or equivalent
- File upload capabilities (minimum 10MB per file)
- Write permissions for data directory

## Installation

1. **Upload Files**
   ```bash
   # Upload the entire php-version folder to your web server
   # Set the document root to the 'public' directory
   ```

2. **Set Permissions**
   ```bash
   chmod 755 data/
   chmod 755 data/uploads/
   chmod 644 data/*.json
   ```

3. **Configure Settings**
   Edit `config/config.php` to customize:
   - `ADMIN_PIN`: Change the default PIN from "1234"
   - `MAX_FILE_SIZE`: Adjust maximum photo file size
   - `SESSION_DURATION`: Set session timeout
   - `ALLOWED_ORIGINS`: Configure CORS for your domain

4. **Web Server Configuration**

   **Apache:**
   - Ensure mod_rewrite is enabled
   - The included .htaccess files handle URL rewriting and security

   **Nginx:**
   ```nginx
   location /api/ {
       try_files $uri $uri/ /api/$uri.php?$query_string;
   }

   location ~ ^/data/ {
       deny all;
   }
   ```

## File Structure

```
php-version/
├── public/              # Web-accessible directory (document root)
│   ├── index.php       # Main application
│   ├── api/            # API endpoints
│   │   ├── auth.php    # Authentication
│   │   ├── markers.php # Marker CRUD
│   │   └── photos.php  # Photo serving
│   ├── assets/
│   │   ├── css/style.css
│   │   └── js/map-app.js
│   └── .htaccess       # Apache configuration
├── data/               # Data storage (not web-accessible)
│   ├── markers.json    # Marker data
│   ├── sessions.json   # Active sessions
│   ├── uploads/        # Photo storage
│   └── .htaccess       # Deny access
├── config/
│   └── config.php      # Application settings
└── includes/
    └── functions.php   # Utility functions
```

## API Endpoints

### Authentication
- `POST /api/auth.php` - Login with PIN
- `DELETE /api/auth.php` - Logout
- `GET /api/auth.php` - Check session status

### Markers
- `GET /api/markers.php` - Get all markers
- `POST /api/markers.php` - Create marker (requires auth)
- `DELETE /api/markers.php?id=X` - Delete marker (requires auth)
- `DELETE /api/markers.php?all=1` - Clear all markers (requires auth)

### Photos
- `GET /api/photos.php?file=path` - Serve photo file

## Data Storage

### markers.json
```json
{
  "counter": 5,
  "markers": [
    {
      "id": 1,
      "lat": 52.3086,
      "lng": 5.0408,
      "created_at": "2025-01-15T10:30:00Z",
      "photos": [
        {
          "filename": "1/photo_1_001.jpg",
          "original_name": "bunker.jpg",
          "size": 245760
        }
      ]
    }
  ]
}
```

### Photo Storage
- Photos stored in `data/uploads/{marker_id}/`
- Organized by marker ID for easy management
- Original filenames preserved in metadata
- Automatic cleanup when markers are deleted

## Security Features

- PIN-based authentication with session tokens
- File upload validation (type, size, content)
- Path traversal protection
- CORS configuration
- Session timeout and cleanup
- Protected data directory
- Input sanitization and validation

## Customization

### Changing the PIN
Edit `config/config.php`:
```php
define('ADMIN_PIN', 'your-new-pin');
```

### Upload Limits
Edit `config/config.php`:
```php
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_PHOTOS_PER_MARKER', 3);
```

### Map Settings
Edit `config/config.php`:
```php
define('DEFAULT_LAT', 52.3086);
define('DEFAULT_LNG', 5.0408);
define('DEFAULT_ZOOM', 13);
```

## Backup and Migration

### Backup
```bash
# Backup all data
cp -r data/ backup/data-$(date +%Y%m%d)/
```

### Migration
```bash
# Copy data directory to new server
rsync -av data/ newserver:/path/to/data/
```

## Troubleshooting

### Common Issues

1. **"Failed to save marker"**
   - Check file permissions on `data/` directory
   - Verify PHP upload_max_filesize setting
   - Check disk space

2. **"Authentication required"**
   - Session may have expired
   - Check PHP session configuration
   - Verify `sessions.json` permissions

3. **Photos not displaying**
   - Check `uploads/` directory permissions
   - Verify photo paths in markers.json
   - Check web server file serving configuration

### Debug Mode
Enable error reporting in `config/config.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## License

Same license as the original project.