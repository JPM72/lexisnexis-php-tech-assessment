-- Create search cache table
CREATE TABLE IF NOT EXISTS search_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    query_hash VARCHAR(64) UNIQUE NOT NULL,
    query_text VARCHAR(255) NOT NULL,
    results JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,

    -- Indexes
    INDEX idx_query_hash (query_hash),
    INDEX idx_expires_at (expires_at),
    INDEX idx_created_at (created_at),
    INDEX idx_query_text (query_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create event to automatically cleanup expired cache entries
-- Note: This requires the MySQL Event Scheduler to be enabled
DROP EVENT IF EXISTS cleanup_expired_cache;
DELIMITER $$
CREATE EVENT cleanup_expired_cache
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    DELETE FROM search_cache WHERE expires_at <= NOW();
END$$
DELIMITER ;