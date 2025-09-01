<?php

declare(strict_types=1);

namespace App\Utils;

use Exception;

class Router
{
	private array $routes = [];
	private array $middleware = [];

	public function get(string $path, $handler): void
	{
		$this->addRoute('GET', $path, $handler);
	}

	public function post(string $path, $handler): void
	{
		$this->addRoute('POST', $path, $handler);
	}

	public function put(string $path, $handler): void
	{
		$this->addRoute('PUT', $path, $handler);
	}

	public function delete(string $path, $handler): void
	{
		$this->addRoute('DELETE', $path, $handler);
	}

	public function patch(string $path, $handler): void
	{
		$this->addRoute('PATCH', $path, $handler);
	}

	public function options(string $path, $handler): void
	{
		$this->addRoute('OPTIONS', $path, $handler);
	}

	public function addMiddleware(callable $middleware): void
	{
		$this->middleware[] = $middleware;
	}

	public function handleRequest(): void
	{
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		$path = $this->getRequestPath();

		// Execute middleware
		foreach ($this->middleware as $middleware)
		{
			$result = call_user_func($middleware);
			if ($result === false)
			{
				return; // Middleware blocked the request
			}
		}

		// Find matching route
		$route = $this->findRoute($method, $path);

		if (!$route)
		{
			$this->handleNotFound();
			return;
		}

		// Execute route handler
		try
		{
			$this->executeHandler($route['handler'], $route['params']);
		}
		catch (Exception $e)
		{
			$this->handleError($e);
		}
	}

	private function addRoute(string $method, string $path, $handler): void
	{
		$pattern = $this->convertPathToRegex($path);

		$this->routes[] = [
			'method' => $method,
			'path' => $path,
			'pattern' => $pattern,
			'handler' => $handler
		];
	}

	private function getRequestPath(): string
	{
		$path = $_SERVER['REQUEST_URI'] ?? '/';

		// Remove query string
		if (($pos = strpos($path, '?')) !== false)
		{
			$path = substr($path, 0, $pos);
		}

		// Remove trailing slash (except for root)
		if ($path !== '/' && substr($path, -1) === '/')
		{
			$path = substr($path, 0, -1);
		}

		return $path;
	}

	private function convertPathToRegex(string $path): string
	{
		// Convert path parameters like {id} to regex groups
		$pattern = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $path);

		// Escape forward slashes and add delimiters
		$pattern = '#^' . str_replace('/', '\/', $pattern) . '$#';

		return $pattern;
	}

	private function findRoute(string $method, string $path): ?array
	{
		foreach ($this->routes as $route)
		{
			if ($route['method'] !== $method)
			{
				continue;
			}

			if (preg_match($route['pattern'], $path, $matches))
			{
				// Extract named parameters
				$params = [];
				foreach ($matches as $key => $value)
				{
					if (is_string($key))
					{
						$params[$key] = $value;
					}
				}

				return [
					'handler' => $route['handler'],
					'params' => $params
				];
			}
		}

		return null;
	}

	private function executeHandler($handler, array $params): void
	{
		if (is_callable($handler))
		{
			// Direct callable
			call_user_func($handler, $params);
		}
		elseif (is_array($handler) && count($handler) === 2)
		{
			// [ClassName, methodName] format
			[$className, $methodName] = $handler;

			if (!class_exists($className))
			{
				throw new Exception("Controller class not found: {$className}");
			}

			$controller = new $className();

			if (!method_exists($controller, $methodName))
			{
				throw new Exception("Method not found: {$className}::{$methodName}");
			}

			// Pass parameters to method
			if (!empty($params))
			{
				// Convert string parameters to appropriate types for method signature
				$reflection = new \ReflectionMethod($controller, $methodName);
				$methodParams = [];

				foreach ($reflection->getParameters() as $param)
				{
					$paramName = $param->getName();

					if (isset($params[$paramName]))
					{
						$value = $params[$paramName];

						// Type conversion based on parameter type
						if ($param->hasType())
						{
							$type = $param->getType();
							if ($type instanceof \ReflectionNamedType)
							{
								switch ($type->getName())
								{
									case 'int':
										$value = (int)$value;
										break;
									case 'float':
										$value = (float)$value;
										break;
									case 'bool':
										$value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
										break;
								}
							}
						}

						$methodParams[] = $value;
					}
					elseif (!$param->isOptional())
					{
						throw new Exception("Required parameter missing: {$paramName}");
					}
				}

				call_user_func_array([$controller, $methodName], $methodParams);
			}
			else
			{
				$controller->$methodName();
			}
		}
		else
		{
			throw new Exception("Invalid handler format");
		}
	}

	private function handleNotFound(): void
	{
		http_response_code(404);
		header('Content-Type: application/json');

		echo json_encode([
			'success' => false,
			'message' => 'Route not found',
			'error' => [
				'code' => 404,
				'type' => 'NOT_FOUND'
			]
		]);
	}

	private function handleError(Exception $e): void
	{
		http_response_code(500);
		header('Content-Type: application/json');

		$response = [
			'success' => false,
			'message' => 'Internal server error'
		];

		// Include error details in development
		if ($_ENV['APP_DEBUG'] ?? false)
		{
			$response['error'] = [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'trace' => $e->getTraceAsString()
			];
		}

		echo json_encode($response);
	}

	public function getRoutes(): array
	{
		return array_map(function ($route)
		{
			return [
				'method' => $route['method'],
				'path' => $route['path']
			];
		}, $this->routes);
	}
}
