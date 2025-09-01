<?php

declare(strict_types=1);

namespace App\Config;

class Config
{
	private static array $config = [];

	public static function get(string $key, $default = null)
	{
		if (empty(self::$config))
		{
			self::loadConfig();
		}

		return self::$config[$key] ?? $default;
	}

	public static function set(string $key, $value): void
	{
		self::$config[$key] = $value;
	}

	private static function loadConfig(): void
	{
		self::$config = [
			// Database
			'db' => [
				'host' => $_ENV['DB_HOST'] ?? 'localhost',
				'name' => $_ENV['DB_NAME'] ?? 'document_search',
				'user' => $_ENV['DB_USER'] ?? 'root',
				'pass' => $_ENV['DB_PASS'] ?? '',
				'port' => (int)($_ENV['DB_PORT'] ?? 3306),
			],

			// File storage
			'storage' => [
				'upload_path' => $_ENV['UPLOAD_PATH'] ?? 'storage/uploads',
				'cache_path' => $_ENV['CACHE_PATH'] ?? 'storage/cache',
				'log_path' => $_ENV['LOG_PATH'] ?? 'storage/logs',
				'max_file_size' => (int)($_ENV['MAX_FILE_SIZE'] ?? 10485760), // 10MB
			],

			// CORS
			'cors' => [
				'origin' => $_ENV['CORS_ORIGIN'] ?? 'http://localhost:4200',
			],

			// Cache
			'cache' => [
				'ttl' => (int)($_ENV['CACHE_TTL'] ?? 3600),
				'cleanup_interval' => (int)($_ENV['CACHE_CLEANUP_INTERVAL'] ?? 86400),
			],

			// Search
			'search' => [
				'results_per_page' => (int)($_ENV['SEARCH_RESULTS_PER_PAGE'] ?? 10),
				'max_results' => (int)($_ENV['MAX_SEARCH_RESULTS'] ?? 100),
			],

			// Application
			'app' => [
				'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
				'env' => $_ENV['APP_ENV'] ?? 'development',
				'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
			],

			// File types
			'allowed_file_types' => explode(',', $_ENV['ALLOWED_FILE_TYPES'] ?? 'text/plain,application/pdf'),
		];
	}
}
