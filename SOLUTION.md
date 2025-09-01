# Document Search System - Solution Design

## Architecture Overview

This document search system is built with a clear separation between backend and frontend, following modern web development practices while maintaining simplicity and performance.

## Backend Design Decisions

### 1. Vanilla PHP Approach

**Decision**: Use vanilla PHP without major frameworks
**Rationale**: 
- Keeps the application lightweight and fast
- Demonstrates core PHP skills
- Easier to customize and optimize for specific use cases
- Minimal dependencies reduce potential security vulnerabilities

**Implementation**:
- Custom routing system for clean URL handling
- PSR-4 autoloading for organized code structure
- Dependency injection container for service management

### 2. Database Schema Design

**Documents Table**:
```sql
CREATE TABLE documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    content_text LONGTEXT,
    file_size INT,
    mime_type VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FULLTEXT(title, content_text)
);
```

**Search Cache Table**:
```sql
CREATE TABLE search_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    query_hash VARCHAR(64) UNIQUE,
    query_text VARCHAR(255),
    results JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP
);
```

**Design Rationale**:
- FULLTEXT indexing on `title` and `content_text` for efficient search
- JSON storage for cached results provides flexibility
- Separate cache table allows for easy cache management and cleanup

### 3. Search Implementation

**Full-Text Search**:
- Uses MySQL's native FULLTEXT search capabilities
- Implements relevance scoring using MATCH() AGAINST() 
- Boolean mode search for advanced queries

**Caching Strategy**:
- File-based caching for search results
- Cache key based on query hash
- TTL-based expiration (configurable)
- Cache warming for frequent queries

### 4. File Processing

**Text Extraction**:
- PDF processing using smalot/pdfparser library
- Chunked reading for large files to prevent memory issues
- Error handling for corrupt or unsupported files

**Storage Strategy**:
- Files stored on local filesystem
- Metadata stored in database
- Configurable upload directory and file size limits

## Frontend Design Decisions

### 1. Angular Architecture

**Feature-Based Modules**:
- Documents module: Upload, list, view, delete operations
- Search module: Search interface and results display
- Shared module: Reusable components and pipes

**State Management**:
- Service-based state management using RxJS
- No external state management library (appropriate for app complexity)
- Reactive programming patterns throughout

### 2. UI/UX Design

**Material Design**:
- Angular Material for consistent, professional UI
- Responsive design using Angular Flex Layout
- Accessible components with proper ARIA attributes

**Performance Features**:
- Debounced search input (300ms delay)
- Virtual scrolling for large result sets
- OnPush change detection strategy
- Lazy loading of feature modules

### 3. Search Interface

**Real-time Search**:
- RxJS operators for efficient search handling
- Debouncing to prevent excessive API calls
- Loading states and error handling

**Result Display**:
- Highlighted search terms in results
- Sorting by relevance or date
- Pagination for large result sets
- Performance metrics display

## Performance Optimizations

### Backend Optimizations

1. **Database Indexing**:
   - FULLTEXT index on searchable content
   - Regular indexes on frequently queried columns
   - Query optimization using EXPLAIN

2. **Caching Layer**:
   - Search result caching with TTL
   - File-based cache for simplicity
   - Cache invalidation strategies

3. **File Handling**:
   - Streaming for large file uploads
   - Chunked processing to prevent memory exhaustion
   - Efficient text extraction algorithms

### Frontend Optimizations

1. **Angular Performance**:
   - OnPush change detection strategy
   - Lazy loading of feature modules
   - TrackBy functions for efficient list rendering

2. **HTTP Optimization**:
   - Request debouncing and throttling
   - HTTP interceptors for error handling
   - Loading states to improve perceived performance

## Trade-offs Made

### 1. Vanilla PHP vs Framework
**Trade-off**: Development speed vs performance and simplicity
**Decision**: Chose vanilla PHP for better performance and learning demonstration
**Impact**: Slightly longer development time but better performance and cleaner code

### 2. File-based Caching vs Redis
**Trade-off**: Infrastructure complexity vs performance
**Decision**: File-based caching for simplicity
**Impact**: Easier deployment but slightly lower performance than Redis

### 3. MySQL FULLTEXT vs Elasticsearch
**Trade-off**: Setup complexity vs advanced search features
**Decision**: MySQL FULLTEXT for simplicity
**Impact**: Faster setup but limited advanced search capabilities

### 4. Service-based State vs NgRx
**Trade-off**: Learning curve vs application complexity
**Decision**: Simple services for this application size
**Impact**: Easier to understand and maintain for small to medium applications

## Scalability Considerations

### Current Limitations
- File storage on local filesystem
- Single database instance
- No horizontal scaling

### Future Improvements
- Cloud storage integration (AWS S3, Google Cloud Storage)
- Database clustering and read replicas
- Microservices architecture for larger scale
- Advanced search with Elasticsearch
- CDN integration for file delivery

## Security Measures

1. **File Upload Security**:
   - File type validation
   - File size limits
   - Virus scanning (future enhancement)

2. **Database Security**:
   - Prepared statements to prevent SQL injection
   - Input validation and sanitization
   - Database connection encryption

3. **API Security**:
   - CORS configuration
   - Rate limiting (future enhancement)
   - Authentication middleware ready

## Testing Strategy

### Backend Testing
- Unit tests for services and models
- Integration tests for API endpoints
- Database testing with fixtures

### Frontend Testing
- Component unit tests
- Service testing with mocks
- E2E testing for critical user flows

## Monitoring and Logging

### Performance Monitoring
- Search query execution time logging
- File upload performance tracking
- API response time monitoring

### Error Logging
- Structured logging with context
- Error aggregation and alerting
- User action tracking for debugging

## Conclusion

This solution balances simplicity, performance, and maintainability while meeting all specified requirements. The architecture allows for future enhancements and scaling while providing a solid foundation for a document search system.