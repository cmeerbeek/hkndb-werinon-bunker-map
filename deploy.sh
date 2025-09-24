#!/bin/bash

# Nederhorst Bunker Map Deployment Script
# This script helps deploy the application to a web hosting platform

echo "🚀 Nederhorst Bunker Map Deployment"
echo "=================================="

# Check if we're in the right directory
if [ ! -f "index.html" ] || [ ! -d "api" ]; then
    echo "❌ Error: Please run this script from the project root directory"
    exit 1
fi

echo "📋 Pre-deployment checklist:"
echo "1. ✅ HTML file exists: index.html"
echo "2. ✅ API directory exists: api/"
echo "3. ✅ Data directory exists: data/"
echo "4. ✅ Uploads directory exists: uploads/"

# Check PHP files
if [ -f "api/auth.php" ] && [ -f "api/markers.php" ]; then
    echo "5. ✅ PHP API files exist"
else
    echo "5. ❌ PHP API files missing"
    exit 1
fi

# Check security files
if [ -f "data/.htaccess" ] && [ -f "api/.htaccess" ] && [ -f "uploads/.htaccess" ]; then
    echo "6. ✅ Security .htaccess files exist"
else
    echo "6. ❌ Security files missing"
    exit 1
fi

echo ""
echo "📁 Directory structure:"
find . -type f -name "*.php" -o -name "*.html" -o -name "*.json" -o -name ".htaccess" | sort

echo ""
echo "🔧 Configuration notes:"
echo "- Default access code is set to '1234' in api/auth.php"
echo "- Change the access code before deployment!"
echo "- Map is centered on Nederhorst den Berg coordinates"
echo "- Maximum 2 photos per marker, 5MB each"

echo ""
echo "📤 Deployment instructions:"
echo "1. Upload all files to your web hosting server"
echo "2. Ensure PHP is enabled on your hosting platform"
echo "3. Set proper permissions:"
echo "   - data/ directory: 755 (writable)"
echo "   - uploads/ directory: 755 (writable)"
echo "   - api/ directory: 755"
echo "4. Update the access code in api/auth.php"
echo "5. Access your application via index.html"

echo ""
echo "🔐 Security reminders:"
echo "- Change the default access code in api/auth.php"
echo "- .htaccess files protect sensitive directories"
echo "- File upload validation is implemented"
echo "- Session-based authentication with rate limiting"

echo ""
echo "✨ Deployment ready! Upload the entire directory to your web host."

# Create a simple readme for the deployment
cat > DEPLOYMENT.md << 'EOF'
# Deployment Instructions

## Requirements
- Web hosting with PHP support
- Apache web server (for .htaccess files)

## Files to Upload
Upload the entire project directory to your web hosting root or subdirectory.

## Directory Permissions
Set the following permissions after upload:
```
chmod 755 data/
chmod 755 uploads/
chmod 755 api/
chmod 644 index.html
chmod 644 api/*.php
```

## Configuration
1. Edit `api/auth.php` and change the default access code from '1234' to your preferred code
2. Test the application by accessing index.html in your browser

## Usage
1. Open the application in a web browser
2. Click "Sign In" and enter your access code
3. Click on the map to add markers
4. Upload 2 photos per marker with a title

## Troubleshooting
- Ensure PHP sessions are enabled on your hosting
- Check file permissions if uploads fail
- Verify .htaccess files are supported by your hosting provider
EOF

echo "📝 Created DEPLOYMENT.md with detailed instructions"