<?php

declare(strict_types=1);

namespace App\Middleware;

class CorsMiddleware
{
	public static function handle(): void
	{
		$origin = $_ENV['CORS_ORIGIN'] ?? 'http://localhost:4200';
		$methods = 'GET, POST, PUT, DELETE, PATCH, OPTIONS';
		$headers = 'Content-Type, Authorization, X-Requested-With, Accept, Origin';

		// Allow specific origin or all origins if configured
		if ($origin === '*')
		{
			header('Access-Control-Allow-Origin: *');
		}
		else
		{
			$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
			$allowedOrigins = explode(',', $origin);
			$allowedOrigins = array_map('trim', $allowedOrigins);

			if (in_array($requestOrigin, $allowedOrigins))
			{
				header('Access-Control-Allow-Origin: ' . $requestOrigin);
			}
		}

		header('Access-Control-Allow-Methods: ' . $methods);
		header('Access-Control-Allow-Headers: ' . $headers);
		header('Access-Control-Allow-Credentials: true');
		header('Access-Control-Max-Age: 86400'); // 24 hours

		// Handle preflight OPTIONS request
		if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
		{
			http_response_code(200);
			exit;
		}
	}

	public static function configure(array $options): void
	{
		$defaultOptions = [
			'origin' => $_ENV['CORS_ORIGIN'] ?? 'http://localhost:4200',
			'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
			'headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
			'credentials' => true,
			'max_age' => 86400
		];

		$config = array_merge($defaultOptions, $options);

		// Set origin
		if ($config['origin'] === '*')
		{
			header('Access-Control-Allow-Origin: *');
		}
		else
		{
			$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
			$allowedOrigins = is_array($config['origin']) ? $config['origin'] : [$config['origin']];

			if (in_array($requestOrigin, $allowedOrigins))
			{
				header('Access-Control-Allow-Origin: ' . $requestOrigin);
			}
		}

		// Set methods
		if (is_array($config['methods']))
		{
			header('Access-Control-Allow-Methods: ' . implode(', ', $config['methods']));
		}
		else
		{
			header('Access-Control-Allow-Methods: ' . $config['methods']);
		}

		// Set headers
		if (is_array($config['headers']))
		{
			header('Access-Control-Allow-Headers: ' . implode(', ', $config['headers']));
		}
		else
		{
			header('Access-Control-Allow-Headers: ' . $config['headers']);
		}

		// Set credentials
		if ($config['credentials'])
		{
			header('Access-Control-Allow-Credentials: true');
		}

		// Set max age
		header('Access-Control-Max-Age: ' . $config['max_age']);
	}
}
