# Document Search System

A lightweight document search system built with PHP backend and Angular frontend for internal research purposes.

## 🚀 Features

### Backend (PHP)
- RESTful API with vanilla PHP
- Document upload (PDF, TXT) with drag-and-drop
- Full-text search with highlighting
- MySQL database with optimized indexing
- File-based caching system
- Performance optimizations

### Frontend (Angular)
- Modern Angular 14+ with TypeScript
- Angular Material UI components
- Real-time search with debouncing
- Responsive design
- Document management interface
- Search results with highlighting

## 📋 Requirements

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Node.js 16+ and npm
- Composer
- Angular CLI

## 🛠️ Quick Setup

1. **Clone and Setup**
   ```bash
   git clone <repository-url>
   cd document-search-system
   chmod +x scripts/setup.sh
   ./scripts/setup.sh
   ```

2. **Environment Configuration**
   ```bash
   cp backend/.env.example backend/.env
   # Edit .env with your database settings
   ```

3. **Install Dependencies**
   ```bash
   # Backend
   cd backend && composer install
   
   # Frontend
   cd frontend && npm install
   ```

4. **Database Setup**
   ```bash
   mysql -u root -p < database/migrations/001_create_documents_table.sql
   mysql -u root -p < database/migrations/002_create_search_cache_table.sql
   ```

5. **Start Development Servers**
   ```bash
   # Backend (Terminal 1)
   cd backend && php -S localhost:8000 -t public
   
   # Frontend (Terminal 2)
   cd frontend && ng serve
   ```

6. **Access Application**
   - Frontend: http://localhost:4200
   - Backend API: http://localhost:8000

## 🏗️ Architecture

### Backend Structure
- **Controllers**: Handle HTTP requests and responses
- **Services**: Business logic and data processing
- **Models**: Database interactions
- **Utils**: Helper classes and utilities
- **Middleware**: Request/response processing

### Frontend Structure
- **Features**: Modular components (documents, search)
- **Shared**: Reusable components and services
- **Core**: Application-wide services
- **Layout**: Navigation and structure components

## 🔧 API Endpoints

- `POST /api/documents` - Upload document
- `GET /api/documents` - List documents (paginated)
- `GET /api/documents/{id}` - Get specific document
- `DELETE /api/documents/{id}` - Delete document
- `GET /api/search?q={query}` - Search documents

## 🧪 Testing

```bash
# Backend tests
cd backend && ./vendor/bin/phpunit

# Frontend tests
cd frontend && ng test
```

## 🚀 Production Deployment

See `docs/deployment-guide.md` for detailed production setup instructions.

## 📖 Documentation

- [API Documentation](docs/api-documentation.md)
- [Database Schema](docs/database-schema.md)
- [Deployment Guide](docs/deployment-guide.md)

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License.