<?php

declare(strict_types=1);

require_once '../vendor/autoload.php';

use App\Config\Database;
use App\Utils\Router;
use App\Middleware\CorsMiddleware;
use App\Controllers\DocumentController;
use App\Controllers\SearchController;

// Load environment variables
if (file_exists('../.env'))
{
	$env = parse_ini_file('../.env');
	foreach ($env as $key => $value)
	{
		$_ENV[$key] = $value;
	}
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', $_ENV['APP_DEBUG'] ?? '1');

// Set timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

// Handle CORS
CorsMiddleware::handle();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
{
	http_response_code(200);
	exit;
}

try
{
	// Initialize router
	$router = new Router();

	// Document routes
	$router->post('/api/documents', [DocumentController::class, 'create']);
	$router->get('/api/documents', [DocumentController::class, 'list']);
	$router->get('/api/documents/{id}', [DocumentController::class, 'get']);
	$router->delete('/api/documents/{id}', [DocumentController::class, 'delete']);

	// Search routes
	$router->get('/api/search', [SearchController::class, 'search']);

	// Health check route
	$router->get('/api/health', function ()
	{
		header('Content-Type: application/json');
		echo json_encode([
			'status' => 'OK',
			'timestamp' => date('c'),
			'version' => '1.0.0'
		]);
	});

	// Handle request
	$router->handleRequest();
}
catch (Exception $e)
{
	// Global error handler
	http_response_code(500);
	header('Content-Type: application/json');

	echo json_encode([
		'success' => false,
		'message' => $_ENV['APP_DEBUG'] ? $e->getMessage() : 'Internal server error',
		'error' => $_ENV['APP_DEBUG'] ? [
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'trace' => $e->getTraceAsString()
		] : null
	]);
}
