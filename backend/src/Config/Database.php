<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;

class Database
{
	private static ?PDO $instance = null;

	public static function getInstance(): PDO
	{
		if (self::$instance === null)
		{
			$host = $_ENV['DB_HOST'] ?? 'localhost';
			$dbname = $_ENV['DB_NAME'] ?? 'document_search';
			$username = $_ENV['DB_USER'] ?? 'root';
			$password = $_ENV['DB_PASS'] ?? '';
			$port = $_ENV['DB_PORT'] ?? '3306';

			$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

			try
			{
				self::$instance = new PDO($dsn, $username, $password, [
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
					PDO::ATTR_EMULATE_PREPARES => false,
					PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
				]);

				// Set timezone
				self::$instance->exec("SET time_zone = '+00:00'");
			}
			catch (PDOException $e)
			{
				throw new PDOException("Database connection failed: " . $e->getMessage());
			}
		}

		return self::$instance;
	}

	public static function beginTransaction(): void
	{
		self::getInstance()->beginTransaction();
	}

	public static function commit(): void
	{
		self::getInstance()->commit();
	}

	public static function rollback(): void
	{
		self::getInstance()->rollback();
	}

	public static function inTransaction(): bool
	{
		return self::getInstance()->inTransaction();
	}
}
