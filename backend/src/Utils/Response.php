<?php

declare(strict_types=1);

namespace App\Utils;

class Response
{
	public static function success($data = null, string $message = 'Success', int $statusCode = 200): void
	{
		self::json([
			'success' => true,
			'message' => $message,
			'data' => $data,
			'timestamp' => date('c')
		], $statusCode);
	}

	public static function error(string $message, int $statusCode = 400, $errors = null): void
	{
		$response = [
			'success' => false,
			'message' => $message,
			'timestamp' => date('c')
		];

		if ($errors !== null)
		{
			$response['errors'] = $errors;
		}

		self::json($response, $statusCode);
	}

	public static function created($data = null, string $message = 'Resource created successfully'): void
	{
		self::success($data, $message, 201);
	}

	public static function noContent(string $message = 'No content'): void
	{
		self::json([
			'success' => true,
			'message' => $message,
			'timestamp' => date('c')
		], 204);
	}

	public static function unauthorized(string $message = 'Unauthorized'): void
	{
		self::error($message, 401);
	}

	public static function forbidden(string $message = 'Forbidden'): void
	{
		self::error($message, 403);
	}

	public static function notFound(string $message = 'Resource not found'): void
	{
		self::error($message, 404);
	}

	public static function validationError(array $errors, string $message = 'Validation failed'): void
	{
		self::error($message, 422, $errors);
	}

	public static function internalError(string $message = 'Internal server error'): void
	{
		self::error($message, 500);
	}

	public static function paginated(array $data, array $pagination, string $message = 'Data retrieved successfully'): void
	{
		self::success([
			'items' => $data,
			'pagination' => $pagination
		], $message);
	}

	public static function file(string $filePath, string $filename = null, string $mimeType = null): void
	{
		if (!file_exists($filePath))
		{
			self::notFound('File not found');
			return;
		}

		$filename = $filename ?: basename($filePath);
		$mimeType = $mimeType ?: self::getMimeType($filePath);
		$fileSize = filesize($filePath);

		// Set headers
		header('Content-Type: ' . $mimeType);
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . $fileSize);
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: 0');

		// Read and output file
		readfile($filePath);
		exit;
	}

	public static function redirect(string $url, int $statusCode = 302): void
	{
		header("Location: {$url}", true, $statusCode);
		exit;
	}

	public static function cors(array $options = []): void
	{
		$defaultOptions = [
			'origin' => $_ENV['CORS_ORIGIN'] ?? '*',
			'methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
			'headers' => 'Content-Type, Authorization, X-Requested-With',
			'credentials' => 'true',
			'max_age' => 86400
		];

		$options = array_merge($defaultOptions, $options);

		header('Access-Control-Allow-Origin: ' . $options['origin']);
		header('Access-Control-Allow-Methods: ' . $options['methods']);
		header('Access-Control-Allow-Headers: ' . $options['headers']);
		header('Access-Control-Allow-Credentials: ' . $options['credentials']);
		header('Access-Control-Max-Age: ' . $options['max_age']);
	}

	public static function setHeader(string $name, string $value): void
	{
		header("{$name}: {$value}");
	}

	public static function setHeaders(array $headers): void
	{
		foreach ($headers as $name => $value)
		{
			self::setHeader($name, $value);
		}
	}

	public static function stream(callable $generator): void
	{
		// Set headers for streaming
		header('Content-Type: application/json');
		header('Cache-Control: no-cache');
		header('Connection: keep-alive');

		// Disable output buffering
		if (ob_get_level())
		{
			ob_end_clean();
		}

		// Call the generator
		$generator();

		exit;
	}

	private static function json(array $data, int $statusCode = 200): void
	{
		http_response_code($statusCode);
		header('Content-Type: application/json; charset=utf-8');

		$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($json === false)
		{
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'message' => 'Failed to encode JSON response',
				'timestamp' => date('c')
			]);
		}
		else
		{
			echo $json;
		}

		exit;
	}

	private static function getMimeType(string $filePath): string
	{
		if (function_exists('finfo_open'))
		{
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mimeType = finfo_file($finfo, $filePath);
			finfo_close($finfo);
			return $mimeType ?: 'application/octet-stream';
		}

		$extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

		$mimeTypes = [
			'txt' => 'text/plain',
			'pdf' => 'application/pdf',
			'json' => 'application/json',
			'xml' => 'application/xml',
			'csv' => 'text/csv',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif'
		];

		return $mimeTypes[$extension] ?? 'application/octet-stream';
	}
}
