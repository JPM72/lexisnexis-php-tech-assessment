<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Response;
use App\Utils\Validator;

abstract class BaseController
{
	protected Validator $validator;

	public function __construct()
	{
		$this->validator = new Validator();
	}

	protected function getJsonInput(): array
	{
		$input = file_get_contents('php://input');
		$decoded = json_decode($input, true);

		if (json_last_error() !== JSON_ERROR_NONE)
		{
			Response::error('Invalid JSON input', 400);
			exit;
		}

		return $decoded ?? [];
	}

	protected function validateRequired(array $data, array $required): void
	{
		$missing = [];

		foreach ($required as $field)
		{
			if (!isset($data[$field]) || empty($data[$field]))
			{
				$missing[] = $field;
			}
		}

		if (!empty($missing))
		{
			Response::error('Missing required fields: ' . implode(', ', $missing), 400);
			exit;
		}
	}

	protected function getPaginationParams(): array
	{
		$page = max(1, (int)($_GET['page'] ?? 1));
		$limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
		$offset = ($page - 1) * $limit;

		return compact('page', 'limit', 'offset');
	}

	protected function sanitizeString(string $input): string
	{
		return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
	}

	protected function logActivity(string $action, array $data = []): void
	{
		$log = [
			'timestamp' => date('c'),
			'action' => $action,
			'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
			'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
			'data' => $data
		];

		$logFile = ($_ENV['LOG_PATH'] ?? 'storage/logs') . '/activity.log';

		if (!is_dir(dirname($logFile)))
		{
			mkdir(dirname($logFile), 0755, true);
		}

		file_put_contents($logFile, json_encode($log) . "\n", FILE_APPEND | LOCK_EX);
	}
}
