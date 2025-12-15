#!/bin/bash

# UtilitySign WordPress Plugin Deployment Script
# Enhanced version with exclusion patterns and comprehensive validation
# This script deploys the plugin files to the local WordPress test site

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
SOURCE_DIR="/Users/christian/Dropbox/Devora/Prosjekter/Cursor/UtilitySign/wp-plugin/utilitysign"
DEST_DIR="/Users/christian/Local Sites/test/app/public/wp-content/plugins/utilitysign"
DEPLOY_IGNORE_FILE=".deployignore"
BACKUP_DIR="${DEST_DIR}_backup_$(date +%Y%m%d_%H%M%S)"

# Essential files that must exist after deployment
ESSENTIAL_FILES=(
    "utilitysign.php"
    "uninstall.php"
    "readme.txt"
    "includes/"
    "assets/"
    "vendor/"
    "views/"
    "libs/"
    "config/"
    # NOTE: src/ is NOT included - source files should never be deployed to production
)

# Parse command line arguments
DRY_RUN=false
BACKUP=false
VERBOSE=false
FORCE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --no-backup)
            BACKUP=false
            shift
            ;;
        --verbose|-v)
            VERBOSE=true
            shift
            ;;
        --force)
            FORCE=true
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS]"
            echo "Options:"
            echo "  --dry-run     Show what would be deployed without actually deploying"
            echo "  --no-backup   Skip creating backup before deployment"
            echo "  --verbose     Show detailed output"
            echo "  --force       Force deployment even if validation fails"
            echo "  --help        Show this help message"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Logging function
log() {
    local level=$1
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    case $level in
        "INFO")
            echo -e "${BLUE}[INFO]${NC} $message"
            ;;
        "SUCCESS")
            echo -e "${GREEN}[SUCCESS]${NC} $message"
            ;;
        "WARNING")
            echo -e "${YELLOW}[WARNING]${NC} $message"
            ;;
        "ERROR")
            echo -e "${RED}[ERROR]${NC} $message"
            ;;
        "DEBUG")
            if [ "$VERBOSE" = true ]; then
                echo -e "${PURPLE}[DEBUG]${NC} $message"
            fi
            ;;
    esac
}

# Error handling function
handle_error() {
    local exit_code=$1
    local message="$2"
    log "ERROR" "$message"
    exit $exit_code
}

# Validation function
validate_environment() {
    log "INFO" "Validating deployment environment..."
    
    # Check if source directory exists
    if [ ! -d "$SOURCE_DIR" ]; then
        handle_error 1 "Source directory does not exist: $SOURCE_DIR"
    fi
    
    # Check if .deployignore file exists, create default if missing
    if [ ! -f "$SOURCE_DIR/$DEPLOY_IGNORE_FILE" ]; then
        log "WARNING" "Deploy ignore file not found: $SOURCE_DIR/$DEPLOY_IGNORE_FILE"
        log "INFO" "Creating default .deployignore file..."
        cat > "$SOURCE_DIR/$DEPLOY_IGNORE_FILE" << 'EOF'
# Development files
node_modules/
.git/
*.log
npm-debug.log*
yarn-debug.log*
yarn-error.log*

# Build artifacts (keep dist/ but exclude source maps in production)
*.map

# IDE files
.vscode/
.idea/
*.swp
*.swo
*~

# OS files
.DS_Store
Thumbs.db

# Test files
__tests__/
*.test.*
*.spec.*
coverage/

# Documentation
README.md
CHANGELOG.md
docs/

# Package files
package-lock.json
yarn.lock
pnpm-lock.yaml

# Environment files
.env
.env.local
.env.development.local
.env.test.local
.env.production.local

# Temporary files
tmp/
temp/
.tmp/

# Vite cache
.vite/

# TypeScript build info
*.tsbuildinfo

# ESLint cache
.eslintcache

# Prettier cache
.prettiercache
EOF
        log "SUCCESS" "Default .deployignore file created"
    fi
    
    # Check if rsync is available
    if ! command -v rsync &> /dev/null; then
        handle_error 1 "rsync is not installed or not in PATH"
    fi
    
    # Check if destination parent directory exists
    local dest_parent=$(dirname "$DEST_DIR")
    if [ ! -d "$dest_parent" ]; then
        handle_error 1 "Destination parent directory does not exist: $dest_parent"
    fi
    
    log "SUCCESS" "Environment validation passed"
}

# Create backup function
create_backup() {
    if [ "$BACKUP" = true ] && [ -d "$DEST_DIR" ]; then
        log "INFO" "Creating backup of existing deployment..."
        if [ "$DRY_RUN" = true ]; then
            log "DEBUG" "Would create backup at: $BACKUP_DIR"
        else
            if cp -r "$DEST_DIR" "$BACKUP_DIR"; then
                log "SUCCESS" "Backup created at: $BACKUP_DIR"
            else
                log "WARNING" "Failed to create backup, continuing with deployment..."
            fi
        fi
    fi
}

# Clean destination function
clean_destination() {
    if [ -d "$DEST_DIR" ]; then
        log "INFO" "Cleaning destination directory..."
        if [ "$DRY_RUN" = true ]; then
            log "DEBUG" "Would clean destination directory: $DEST_DIR"
        else
            rm -rf "$DEST_DIR"/*
            rm -rf "$DEST_DIR"/.[^.]*
            log "SUCCESS" "Destination directory cleaned"
        fi
    else
        log "INFO" "Creating destination directory..."
        if [ "$DRY_RUN" = true ]; then
            log "DEBUG" "Would create destination directory: $DEST_DIR"
        else
            mkdir -p "$DEST_DIR"
            log "SUCCESS" "Destination directory created"
        fi
    fi
}

# Deploy function
deploy_files() {
    log "INFO" "Starting file deployment..."
    
    local rsync_opts="-av"
    if [ "$DRY_RUN" = true ]; then
        rsync_opts="$rsync_opts --dry-run"
        log "DEBUG" "DRY RUN MODE - No files will actually be copied"
    fi
    
    if [ "$VERBOSE" = true ]; then
        rsync_opts="$rsync_opts --progress"
    fi
    
    # Change to source directory
    cd "$SOURCE_DIR"
    
    # Deploy files using rsync with exclusion patterns
    if rsync $rsync_opts --exclude-from="$DEPLOY_IGNORE_FILE" ./ "$DEST_DIR/"; then
        if [ "$DRY_RUN" = true ]; then
            log "SUCCESS" "Dry run completed successfully"
        else
            log "SUCCESS" "Files deployed successfully"
        fi
    else
        handle_error 1 "Deployment failed during rsync operation"
    fi
    
    # Special handling for vendor directory to ensure all files are copied
    if [ "$DRY_RUN" = false ]; then
        log "INFO" "Ensuring vendor directory is complete..."
        if [ -d "vendor" ]; then
            rsync -av vendor/ "$DEST_DIR/vendor/" --exclude="*.md" --exclude="composer.json"
            log "SUCCESS" "Vendor directory synchronized"
        fi
    fi
}

# Validate deployment function
validate_deployment() {
    if [ "$DRY_RUN" = true ]; then
        log "DEBUG" "Skipping deployment validation in dry-run mode"
        return 0
    fi
    
    log "INFO" "Validating deployed files..."
    local missing_files=()
    
    for file in "${ESSENTIAL_FILES[@]}"; do
        if [ ! -e "$DEST_DIR/$file" ]; then
            missing_files+=("$file")
        fi
    done
    
    if [ ${#missing_files[@]} -gt 0 ]; then
        log "ERROR" "Missing essential files after deployment:"
        for file in "${missing_files[@]}"; do
            log "ERROR" "  - $file"
        done
        
        if [ "$FORCE" = false ]; then
            handle_error 1 "Deployment validation failed. Use --force to override."
        else
            log "WARNING" "Deployment validation failed but continuing due to --force flag"
        fi
    else
        log "SUCCESS" "All essential files present after deployment"
    fi
}

# Set file permissions function
set_permissions() {
    if [ "$DRY_RUN" = true ]; then
        log "DEBUG" "Would set file permissions"
        return 0
    fi
    
    log "INFO" "Setting appropriate file permissions..."
    
    # Set directory permissions
    find "$DEST_DIR" -type d -exec chmod 755 {} \;
    
    # Set file permissions
    find "$DEST_DIR" -type f -exec chmod 644 {} \;
    
    # Make scripts executable
    find "$DEST_DIR" -name "*.sh" -exec chmod 755 {} \;
    
    log "SUCCESS" "File permissions set"
}

# Main execution
main() {
    echo -e "${CYAN}================================================${NC}"
    echo -e "${CYAN}    UtilitySign WordPress Plugin Deployment Script    ${NC}"
    echo -e "${CYAN}================================================${NC}"
    echo ""
    
    log "INFO" "Starting deployment process..."
    log "INFO" "Source: $SOURCE_DIR"
    log "INFO" "Destination: $DEST_DIR"
    log "INFO" "Deploy ignore file: $DEPLOY_IGNORE_FILE"
    
    if [ "$DRY_RUN" = true ]; then
        log "WARNING" "DRY RUN MODE - No changes will be made"
    fi
    
    if [ "$BACKUP" = false ]; then
        log "WARNING" "Backup disabled - existing files will be overwritten"
    fi
    
    echo ""
    
    # Execute deployment steps
    validate_environment
    create_backup
    clean_destination
    deploy_files
    validate_deployment
    set_permissions
    
    echo ""
    echo -e "${GREEN}================================================${NC}"
    echo -e "${GREEN}           Deployment Completed Successfully!  ${NC}"
    echo -e "${GREEN}================================================${NC}"
    echo ""
    
    log "INFO" "Next Steps:"
    echo "1. Visit WordPress admin: http://devora-test.local/wp-admin/"
    echo "2. Activate the plugin if not already active"
    echo "3. Test the functionality"
    echo ""
    
    log "INFO" "Useful URLs:"
    echo "• WordPress Admin: http://devora-test.local/wp-admin/"
    echo "• Frontend: http://devora-test.local/"
    echo "• Plugin Settings: http://devora-test.local/wp-admin/admin.php?page=utilitysign"
    echo ""
    
    if [ "$BACKUP" = true ] && [ -d "$BACKUP_DIR" ]; then
        log "INFO" "Backup location: $BACKUP_DIR"
    fi
    
    log "SUCCESS" "Deployment script completed successfully!"
}

# Run main function
main "$@"