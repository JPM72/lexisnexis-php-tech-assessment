<?php

declare(strict_types=1);

namespace App\Models;

class Document extends BaseModel
{
	protected string $table = 'documents';

	public function getList(int $page = 1, int $limit = 10, string $sortBy = 'created_at', string $sortOrder = 'DESC'): array
	{
		$offset = ($page - 1) * $limit;

		// Get total count
		$totalSql = "SELECT COUNT(*) FROM {$this->table}";
		$total = (int)$this->fetchColumn($totalSql);

		// Get documents
		$sql = "SELECT id, title, filename, file_size, mime_type, created_at, updated_at
                FROM {$this->table}
                ORDER BY {$sortBy} {$sortOrder}
                LIMIT :limit OFFSET :offset";

		$documents = $this->fetchAll($sql, compact('limit', 'offset'));

		// Calculate pagination info
		$totalPages = (int)ceil($total / $limit);
		$hasNext = $page < $totalPages;
		$hasPrev = $page > 1;

		return [
			'data' => $documents,
			'pagination' => [
				'current_page' => $page,
				'per_page' => $limit,
				'total' => $total,
				'total_pages' => $totalPages,
				'has_next' => $hasNext,
				'has_prev' => $hasPrev,
				'next_page' => $hasNext ? $page + 1 : null,
				'prev_page' => $hasPrev ? $page - 1 : null
			]
		];
	}

	public function getById(int $id): ?array
	{
		$sql = "SELECT * FROM {$this->table} WHERE id = :id";
		return $this->fetchOne($sql, ['id' => $id]);
	}

	public function search(string $query, string $mode = 'natural'): array
	{
		$searchQuery = $this->buildSearchQuery($query, $mode);

		$sql = "SELECT id, title, filename, file_size, mime_type, created_at,
                       MATCH(title, content_text) AGAINST(:query1 {$searchQuery['mode']}) as relevance_score
                FROM {$this->table}
                WHERE MATCH(title, content_text) AGAINST(:query2 {$searchQuery['mode']})
                ORDER BY relevance_score DESC";

		return $this->fetchAll($sql, ['query1' => $searchQuery['query'], 'query2' => $searchQuery['query']]);
	}

	public function searchWithPagination(
		string $query,
		int $page = 1,
		int $limit = 10,
		string $sortBy = 'relevance',
		string $sortOrder = 'DESC',
		string $mode = 'natural'
	): array
	{
		$offset = ($page - 1) * $limit;
		$searchQuery = $this->buildSearchQuery($query, $mode);

		// Get total count
		$countSql = "SELECT COUNT(*)
                     FROM {$this->table}
                     WHERE MATCH(title, content_text) AGAINST(:query {$searchQuery['mode']})";

		$total = (int)$this->fetchColumn($countSql, ['query' => $searchQuery['query']]);

		// Build ORDER BY clause
		$orderBy = $this->buildOrderByClause($sortBy, $sortOrder);

		// Get search results
		$sql = "SELECT id, title, filename, file_size, mime_type, created_at,
                       MATCH(title, content_text) AGAINST(:query1 {$searchQuery['mode']}) as relevance_score
                FROM {$this->table}
                WHERE MATCH(title, content_text) AGAINST(:query2 {$searchQuery['mode']})
                {$orderBy}
                LIMIT :limit OFFSET :offset";

		$params = [
			'query1' => $searchQuery['query'],
			'query2' => $searchQuery['query'],
			'limit' => $limit,
			'offset' => $offset
		];

		$documents = $this->fetchAll($sql, $params);

		// Calculate pagination info
		$totalPages = (int)ceil($total / $limit);
		$hasNext = $page < $totalPages;
		$hasPrev = $page > 1;

		return [
			'data' => $documents,
			'pagination' => [
				'current_page' => $page,
				'per_page' => $limit,
				'total' => $total,
				'total_pages' => $totalPages,
				'has_next' => $hasNext,
				'has_prev' => $hasPrev,
				'next_page' => $hasNext ? $page + 1 : null,
				'prev_page' => $hasPrev ? $page - 1 : null
			]
		];
	}

	public function getContentForHighlighting(int $id): ?string
	{
		$sql = "SELECT content_text FROM {$this->table} WHERE id = :id";
		$result = $this->fetchColumn($sql, ['id' => $id]);
		return $result ?: null;
	}

	public function getPopularSearchTerms(int $limit = 10, int $days = 30): array
	{
		// This would typically come from a search_queries log table
		// For now, return empty array as it would need additional implementation
		return [];
	}

	public function getDocumentStats(): array
	{
		$stats = [];

		// Total documents
		$stats['total_documents'] = $this->count();

		// Total file size
		$sql = "SELECT SUM(file_size) FROM {$this->table}";
		$stats['total_size'] = (int)$this->fetchColumn($sql);

		// Documents by type
		$sql = "SELECT mime_type, COUNT(*) as count FROM {$this->table} GROUP BY mime_type";
		$stats['by_type'] = $this->fetchAll($sql);

		// Documents by month (last 12 months)
		$sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
                FROM {$this->table}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month DESC";
		$stats['by_month'] = $this->fetchAll($sql);

		return $stats;
	}

	private function buildSearchQuery(string $query, string $mode): array
	{
		switch ($mode)
		{
			case 'boolean':
				return [
					'query' => $query,
					'mode' => 'IN BOOLEAN MODE'
				];

			case 'wildcard':
				// Add wildcards to each term
				$terms = explode(' ', $query);
				$wildcardTerms = array_map(fn($term) => $term . '*', $terms);
				return [
					'query' => '+' . implode(' +', $wildcardTerms),
					'mode' => 'IN BOOLEAN MODE'
				];

			case 'natural':
			default:
				return [
					'query' => $query,
					'mode' => 'IN NATURAL LANGUAGE MODE'
				];
		}
	}

	private function buildOrderByClause(string $sortBy, string $sortOrder): string
	{
		$validSorts = [
			'relevance' => 'relevance_score',
			'created_at' => 'created_at',
			'title' => 'title',
			'file_size' => 'file_size'
		];

		$column = $validSorts[$sortBy] ?? 'relevance_score';
		$order = in_array($sortOrder, ['ASC', 'DESC']) ? $sortOrder : 'DESC';

		return "ORDER BY {$column} {$order}";
	}
}
