<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\SearchCache;
use App\Config\Config;
use Exception;

class SearchService
{
	private Document $documentModel;
	private SearchCache $cacheModel;
	private CacheService $cacheService;

	public function __construct()
	{
		$this->documentModel = new Document();
		$this->cacheModel = new SearchCache();
		$this->cacheService = new CacheService();
	}

	public function search(
		string $query,
		int $page = 1,
		int $limit = 10,
		string $sortBy = 'relevance',
		string $sortOrder = 'DESC',
		string $mode = 'natural'
	): array
	{
		// Generate cache key
		$cacheKey = $this->generateCacheKey($query, $page, $limit, $sortBy, $sortOrder, $mode);

		// Try to get from cache first
		$cached = $this->cacheService->get($cacheKey);
		if ($cached !== null)
		{
			return $cached;
		}

		// Perform search
		$results = $this->documentModel->searchWithPagination(
			$query,
			$page,
			$limit,
			$sortBy,
			$sortOrder,
			$mode
		);

		// Enhance results with highlighting and snippets
		$results['data'] = $this->enhanceSearchResults($results['data'], $query, $mode);

		// Cache the results
		$ttl = Config::get('cache.ttl', 3600);
		$this->cacheService->set($cacheKey, $results, $ttl);

		// Store in database cache for analytics
		$this->cacheModel->set($cacheKey, $query, $results, $ttl);

		return $results;
	}

	public function getSuggestions(string $query, int $limit = 5): array
	{
		// For basic implementation, return related terms from existing documents
		$suggestions = [];

		// Get documents that match the partial query
		$sql = "SELECT DISTINCT title
                FROM documents
                WHERE title LIKE :query
                ORDER BY title
                LIMIT :limit";

		$results = $this->documentModel->fetchAll($sql, ['query' => "%{$query}%", 'limit' => $limit]);

		foreach ($results as $result)
		{
			$suggestions[] = $result['title'];
		}

		return $suggestions;
	}

	public function getPopularQueries(int $limit = 10, int $days = 30): array
	{
		return $this->cacheModel->getPopularQueries($limit, $days);
	}

	public function highlightText(string $text, string $query, string $mode = 'natural'): string
	{
		$terms = $this->extractSearchTerms($query, $mode);

		foreach ($terms as $term)
		{
			if (strlen($term) < 2) continue; // Skip very short terms

			$pattern = '/\b(' . preg_quote($term, '/') . ')\b/i';
			$text = preg_replace($pattern, '<mark>$1</mark>', $text);
		}

		return $text;
	}

	public function generateSnippet(string $content, string $query, int $maxLength = 200): string
	{
		$terms = $this->extractSearchTerms($query);
		$content = strip_tags($content);

		// Find the best position for the snippet
		$bestPosition = 0;
		$maxScore = 0;

		for ($i = 0; $i < strlen($content) - $maxLength; $i += 50)
		{
			$snippet = substr($content, $i, $maxLength);
			$score = $this->calculateSnippetScore($snippet, $terms);

			if ($score > $maxScore)
			{
				$maxScore = $score;
				$bestPosition = $i;
			}
		}

		$snippet = substr($content, $bestPosition, $maxLength);

		// Adjust to word boundaries
		if ($bestPosition > 0)
		{
			$wordStart = strpos($snippet, ' ');
			if ($wordStart !== false)
			{
				$snippet = substr($snippet, $wordStart + 1);
			}
			$snippet = '...' . $snippet;
		}

		if ($bestPosition + $maxLength < strlen($content))
		{
			$lastSpace = strrpos($snippet, ' ');
			if ($lastSpace !== false)
			{
				$snippet = substr($snippet, 0, $lastSpace);
			}
			$snippet = $snippet . '...';
		}

		return $this->highlightText($snippet, $query);
	}

	public function getSearchStats(): array
	{
		return [
			'cache_stats' => $this->cacheModel->getCacheStats(),
			'popular_queries' => $this->getPopularQueries(5, 7), // Top 5 from last week
			'recent_queries' => $this->cacheModel->getRecentQueries(10)
		];
	}

	public function clearCache(): int
	{
		return $this->cacheModel->clear();
	}

	public function cleanupExpiredCache(): int
	{
		return $this->cacheModel->cleanupExpired();
	}

	private function enhanceSearchResults(array $results, string $query, string $mode): array
	{
		foreach ($results as &$result)
		{
			// Get full content for snippet generation
			$content = $this->documentModel->getContentForHighlighting($result['id']);

			if ($content)
			{
				// Generate snippet with highlighting
				$result['snippet'] = $this->generateSnippet($content, $query);

				// Highlight title
				$result['title_highlighted'] = $this->highlightText($result['title'], $query, $mode);
			}
			else
			{
				$result['snippet'] = '';
				$result['title_highlighted'] = $result['title'];
			}

			// Format relevance score
			if (isset($result['relevance_score']))
			{
				$result['relevance_score'] = round($result['relevance_score'], 4);
			}

			// Format file size
			$result['file_size_formatted'] = $this->formatFileSize($result['file_size']);

			// Format dates
			$result['created_at_formatted'] = date('M j, Y', strtotime($result['created_at']));
		}

		return $results;
	}

	private function extractSearchTerms(string $query, string $mode = 'natural'): array
	{
		switch ($mode)
		{
			case 'boolean':
				// Extract terms from boolean query, removing operators
				$terms = preg_split('/[\s\+\-\(\)\*\"]+/', $query, -1, PREG_SPLIT_NO_EMPTY);
				break;

			case 'wildcard':
				// Remove wildcards and extract base terms
				$query = str_replace('*', '', $query);
				$terms = explode(' ', $query);
				break;

			default:
				$terms = explode(' ', $query);
		}

		// Clean and filter terms
		$terms = array_map('trim', $terms);
		$terms = array_filter($terms, fn($term) => strlen($term) >= 2);

		return array_unique($terms);
	}

	private function calculateSnippetScore(string $snippet, array $terms): int
	{
		$score = 0;
		$snippet = strtolower($snippet);

		foreach ($terms as $term)
		{
			$termLower = strtolower($term);
			$count = substr_count($snippet, $termLower);
			$score += $count * strlen($term); // Longer terms get higher weight
		}

		return $score;
	}

	private function generateCacheKey(
		string $query,
		int $page,
		int $limit,
		string $sortBy,
		string $sortOrder,
		string $mode
	): string
	{
		$data = compact('query', 'page', 'limit', 'sortBy', 'sortOrder', 'mode');
		return 'search_' . md5(json_encode($data));
	}

	private function formatFileSize(int $size): string
	{
		$units = ['B', 'KB', 'MB', 'GB'];
		$unitIndex = 0;

		while ($size >= 1024 && $unitIndex < count($units) - 1)
		{
			$size /= 1024;
			$unitIndex++;
		}

		return round($size, 1) . ' ' . $units[$unitIndex];
	}
}
