#!/bin/bash

# Deployment script for Weesp Map PHP application
# Usage: ./deploy.sh [destination_path]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== Weesp Map PHP Deployment Script ===${NC}"

# Check if destination is provided
if [ -z "$1" ]; then
    echo -e "${RED}Error: Please provide destination path${NC}"
    echo "Usage: $0 /path/to/web/server/root"
    exit 1
fi

DEST="$1"

# Check if destination exists
if [ ! -d "$DEST" ]; then
    echo -e "${RED}Error: Destination directory does not exist: $DEST${NC}"
    exit 1
fi

echo -e "${YELLOW}Destination: $DEST${NC}"

# Create backup if files exist
if [ -d "$DEST/public" ]; then
    BACKUP_DIR="$DEST/backup-$(date +%Y%m%d_%H%M%S)"
    echo -e "${YELLOW}Creating backup at: $BACKUP_DIR${NC}"
    mkdir -p "$BACKUP_DIR"
    cp -r "$DEST/public" "$BACKUP_DIR/" 2>/dev/null || true
    cp -r "$DEST/data" "$BACKUP_DIR/" 2>/dev/null || true
fi

echo -e "${YELLOW}Copying files...${NC}"

# Copy application files
cp -r public/ "$DEST/"
cp -r config/ "$DEST/"
cp -r includes/ "$DEST/"

# Create data directory if it doesn't exist
mkdir -p "$DEST/data/uploads"

# Copy data directory structure but preserve existing data
if [ ! -f "$DEST/data/markers.json" ]; then
    cp -r data/ "$DEST/"
else
    echo -e "${YELLOW}Preserving existing data files${NC}"
    cp data/.htaccess "$DEST/data/"
fi

# Set permissions
echo -e "${YELLOW}Setting permissions...${NC}"
chmod -R 755 "$DEST/public"
chmod -R 755 "$DEST/data"
chmod 644 "$DEST/data"/*.json 2>/dev/null || true

echo -e "${GREEN}=== Deployment Complete ===${NC}"
echo -e "${YELLOW}Next steps:${NC}"
echo "1. Configure your web server document root to: $DEST/public"
echo "2. Update the PIN in: $DEST/config/config.php"
echo "3. Test the application in your browser"
echo "4. Check file permissions if you encounter issues"

# Display current configuration
echo -e "\n${YELLOW}Current configuration:${NC}"
if [ -f "$DEST/config/config.php" ]; then
    grep "ADMIN_PIN" "$DEST/config/config.php" | head -1
    grep "MAX_FILE_SIZE" "$DEST/config/config.php" | head -1
fi