<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SearchService;
use App\Utils\Response;
use Exception;

class SearchController extends BaseController
{
	private SearchService $searchService;

	public function __construct()
	{
		parent::__construct();
		$this->searchService = new SearchService();
	}

	public function search(): void
	{
		try
		{
			$query = trim($_GET['q'] ?? '');

			if (empty($query))
			{
				Response::error('Search query is required', 400);
				return;
			}

			if (strlen($query) < 2)
			{
				Response::error('Search query must be at least 2 characters long', 400);
				return;
			}

			// Sanitize query
			$query = $this->sanitizeString($query);

			// Get pagination parameters
			$params = $this->getPaginationParams();

			// Get sort parameters
			$sortBy = $this->sanitizeString($_GET['sort'] ?? 'relevance');
			$sortOrder = strtoupper($_GET['order'] ?? 'DESC');

			// Validate sort parameters
			$allowedSorts = ['relevance', 'created_at', 'title', 'file_size'];
			if (!in_array($sortBy, $allowedSorts))
			{
				$sortBy = 'relevance';
			}

			if (!in_array($sortOrder, ['ASC', 'DESC']))
			{
				$sortOrder = 'DESC';
			}

			// Get search mode
			$mode = $this->sanitizeString($_GET['mode'] ?? 'natural');
			$allowedModes = ['natural', 'boolean', 'wildcard'];
			if (!in_array($mode, $allowedModes))
			{
				$mode = 'natural';
			}

			$startTime = microtime(true);

			$result = $this->searchService->search(
				$query,
				$params['page'],
				$params['limit'],
				$sortBy,
				$sortOrder,
				$mode
			);

			$executionTime = round((microtime(true) - $startTime) * 1000, 2); // milliseconds

			// Add search metadata
			$result['metadata'] = [
				'query' => $query,
				'execution_time_ms' => $executionTime,
				'page' => $params['page'],
				'limit' => $params['limit'],
				'sort_by' => $sortBy,
				'sort_order' => $sortOrder,
				'search_mode' => $mode
			];

			$this->logActivity('search', [
				'query' => $query,
				'results_count' => count($result['data']),
				'execution_time' => $executionTime,
				'mode' => $mode
			]);

			Response::success($result, 'Search completed successfully');
		}
		catch (Exception $e)
		{
			Response::error($e->getMessage(), 500);
		}
	}

	public function suggestions(): void
	{
		try
		{
			$query = trim($_GET['q'] ?? '');

			if (empty($query) || strlen($query) < 2)
			{
				Response::success(['suggestions' => []]);
				return;
			}

			$query = $this->sanitizeString($query);
			$limit = min(10, max(1, (int)($_GET['limit'] ?? 5)));

			$suggestions = $this->searchService->getSuggestions($query, $limit);

			Response::success(['suggestions' => $suggestions]);
		}
		catch (Exception $e)
		{
			Response::error($e->getMessage(), 500);
		}
	}

	public function popularQueries(): void
	{
		try
		{
			$limit = min(20, max(1, (int)($_GET['limit'] ?? 10)));
			$days = min(365, max(1, (int)($_GET['days'] ?? 30)));

			$queries = $this->searchService->getPopularQueries($limit, $days);

			Response::success(['popular_queries' => $queries]);
		}
		catch (Exception $e)
		{
			Response::error($e->getMessage(), 500);
		}
	}
}
