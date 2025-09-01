#!/bin/bash

# Document Search System Setup Script
# This script sets up the development environment

set -e  # Exit on any error

echo "🚀 Setting up Document Search System..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if required commands exist
check_command() {
    if ! command -v "$1" &> /dev/null; then
        echo -e "${RED}Error: $1 is not installed${NC}"
        exit 1
    fi
}

echo "📋 Checking prerequisites..."
check_command php
check_command composer
check_command node
check_command npm
check_command mysql

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;")
echo -e "Current PHP version: $PHP_VERSION${NC}"
if [ "$(printf '%s\n' "8.0" "$PHP_VERSION" | sort -V | head -n1)" != "8.0" ]; then
    echo -e "${RED}Error: PHP 8.0 or higher is required."
    exit 1
fi

echo -e "${GREEN}✅ Prerequisites check passed${NC}"

# Create directory structure
echo "📁 Creating directory structure..."
mkdir -p backend/storage/uploads
mkdir -p backend/storage/cache
mkdir -p backend/storage/logs
mkdir -p docs
mkdir -p scripts

# Set permissions
chmod 755 backend/storage
chmod 755 backend/storage/uploads
chmod 755 backend/storage/cache
chmod 755 backend/storage/logs

echo -e "${GREEN}✅ Directory structure created${NC}"

# Setup backend
echo "⚙️ Setting up backend..."
cd backend

if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo -e "${YELLOW}⚠️  Please configure your .env file with database credentials${NC}"
    else
        echo -e "${RED}Error: .env.example not found${NC}"
        exit 1
    fi
fi

# Install backend dependencies
if [ -f composer.json ]; then
    echo "📦 Installing PHP dependencies..."
    composer install --no-dev --optimize-autoloader
    echo -e "${GREEN}✅ Backend dependencies installed${NC}"
else
    echo -e "${RED}Error: composer.json not found${NC}"
    exit 1
fi

cd ..

# Setup frontend
echo "🎨 Setting up frontend..."
cd frontend

if [ -f package.json ]; then
    echo "📦 Installing Node.js dependencies..."
    npm install
    echo -e "${GREEN}✅ Frontend dependencies installed${NC}"
else
    echo -e "${RED}Error: package.json not found${NC}"
    exit 1
fi

cd ..

# Database setup prompt
echo ""
echo -e "${BLUE}📊 Database Setup${NC}"
echo "Please ensure your MySQL server is running and create a database named 'document_search'"
echo ""
echo "To create the database, run:"
echo "mysql -u root -p -e \"CREATE DATABASE document_search CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""
echo ""
echo "Then run the migrations:"
echo "mysql -u root -p document_search < backend/database/migrations/001_create_documents_table.sql"
echo "mysql -u root -p document_search < backend/database/migrations/002_create_search_cache_table.sql"
echo ""
echo "Optionally, add sample data:"
echo "mysql -u root -p document_search < backend/database/seeds/sample_documents.sql"

# Create gitkeep files
touch backend/storage/uploads/.gitkeep
touch backend/storage/cache/.gitkeep
touch backend/storage/logs/.gitkeep

# Create basic .htaccess for security
cat > backend/storage/.htaccess << 'EOF'
Options -Indexes
Deny from all
EOF

echo ""
echo -e "${GREEN}🎉 Setup completed successfully!${NC}"
echo ""
echo "To start the development servers:"
echo ""
echo -e "${BLUE}Backend:${NC}"
echo "cd backend && php -S localhost:8000 -t public"
echo ""
echo -e "${BLUE}Frontend:${NC}"
echo "cd frontend && ng serve"
echo ""
echo "Then visit http://localhost:4200 to access the application"
echo ""
echo -e "${YELLOW}Don't forget to:${NC}"
echo "1. Configure your .env file with database credentials"
echo "2. Create and setup your database"
echo "3. Run the database migrations"