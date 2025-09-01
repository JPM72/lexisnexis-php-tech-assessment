<?php

declare(strict_types=1);

namespace App\Models;

class SearchCache extends BaseModel
{
	protected string $table = 'search_cache';

	public function get(string $queryHash): ?array
	{
		$sql = "SELECT * FROM {$this->table}
                WHERE query_hash = :query_hash
                AND expires_at > NOW()";

		$result = $this->fetchOne($sql, ['query_hash' => $queryHash]);

		if ($result)
		{
			$result['results'] = json_decode($result['results'], true);
		}

		return $result;
	}

	public function set(string $queryHash, string $queryText, array $results, int $ttl = 3600): bool
	{
		$expiresAt = date('Y-m-d H:i:s', time() + $ttl);

		$data = [
			'query_hash' => $queryHash,
			'query_text' => $queryText,
			'results' => json_encode($results),
			'expires_at' => $expiresAt
		];

		// Use REPLACE to handle duplicates
		$sql = "REPLACE INTO {$this->table} (query_hash, query_text, results, expires_at, created_at)
                VALUES (:query_hash, :query_text, :results, :expires_at, NOW())";

		$stmt = $this->execute($sql, $data);
		return $stmt->rowCount() > 0;
	}

	public function cleanupExpired(): int
	{
		$sql = "DELETE FROM {$this->table} WHERE expires_at <= NOW()";
		$stmt = $this->execute($sql);
		return $stmt->rowCount();
	}

	public function getPopularQueries(int $limit = 10, int $days = 30): array
	{
		$sql = "SELECT query_text, COUNT(*) as search_count
                FROM {$this->table}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY query_text
                ORDER BY search_count DESC, created_at DESC
                LIMIT :limit";

		return $this->fetchAll($sql, compact('days', 'limit'));
	}

	public function getRecentQueries(int $limit = 10): array
	{
		$sql = "SELECT DISTINCT query_text, created_at
                FROM {$this->table}
                ORDER BY created_at DESC
                LIMIT :limit";

		return $this->fetchAll($sql, compact('limit'));
	}

	public function getCacheStats(): array
	{
		$stats = [];

		// Total cached queries
		$stats['total_queries'] = $this->count();

		// Active cache entries
		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE expires_at > NOW()";
		$stats['active_entries'] = (int)$this->fetchColumn($sql);

		// Expired cache entries
		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE expires_at <= NOW()";
		$stats['expired_entries'] = (int)$this->fetchColumn($sql);

		// Cache hit rate (would need additional tracking)
		$stats['hit_rate'] = 0; // Placeholder

		// Most recent cache entries
		$sql = "SELECT query_text, created_at FROM {$this->table}
                ORDER BY created_at DESC LIMIT 5";
		$stats['recent_queries'] = $this->fetchAll($sql);

		return $stats;
	}

	public function invalidateByPattern(string $pattern): int
	{
		$sql = "DELETE FROM {$this->table} WHERE query_text LIKE :pattern";
		$stmt = $this->execute($sql, ['pattern' => "%{$pattern}%"]);
		return $stmt->rowCount();
	}

	public function clear(): int
	{
		$sql = "DELETE FROM {$this->table}";
		$stmt = $this->execute($sql);
		return $stmt->rowCount();
	}
}
