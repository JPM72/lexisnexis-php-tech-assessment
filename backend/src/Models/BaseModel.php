<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;
use PDOStatement;

abstract class BaseModel
{
	protected PDO $db;
	protected string $table;

	public function __construct()
	{
		$this->db = Database::getInstance();
	}

	protected function execute(string $sql, array $params = []): PDOStatement
	{
		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		return $stmt;
	}

	protected function fetchAll(string $sql, array $params = []): array
	{
		$stmt = $this->execute($sql, $params);
		return $stmt->fetchAll();
	}

	protected function fetchOne(string $sql, array $params = []): ?array
	{
		$stmt = $this->execute($sql, $params);
		$result = $stmt->fetch();
		return $result ?: null;
	}

	protected function fetchColumn(string $sql, array $params = []): mixed
	{
		$stmt = $this->execute($sql, $params);
		return $stmt->fetchColumn();
	}

	public function create(array $data): int
	{
		$fields = array_keys($data);
		$placeholders = array_map(fn($field) => ":$field", $fields);

		$sql = sprintf(
			"INSERT INTO %s (%s) VALUES (%s)",
			$this->table,
			implode(', ', $fields),
			implode(', ', $placeholders)
		);

		$this->execute($sql, $data);
		return (int)$this->db->lastInsertId();
	}

	public function update(int $id, array $data): bool
	{
		$fields = array_keys($data);
		$setClause = array_map(fn($field) => "$field = :$field", $fields);

		$sql = sprintf(
			"UPDATE %s SET %s WHERE id = :id",
			$this->table,
			implode(', ', $setClause)
		);

		$data['id'] = $id;
		$stmt = $this->execute($sql, $data);

		return $stmt->rowCount() > 0;
	}

	public function delete(int $id): bool
	{
		$sql = "DELETE FROM {$this->table} WHERE id = :id";
		$stmt = $this->execute($sql, ['id' => $id]);

		return $stmt->rowCount() > 0;
	}

	public function findById(int $id): ?array
	{
		$sql = "SELECT * FROM {$this->table} WHERE id = :id";
		return $this->fetchOne($sql, ['id' => $id]);
	}

	public function findAll(int $limit = 100, int $offset = 0): array
	{
		$sql = "SELECT * FROM {$this->table} LIMIT :limit OFFSET :offset";
		return $this->fetchAll($sql, compact('limit', 'offset'));
	}

	public function count(): int
	{
		$sql = "SELECT COUNT(*) FROM {$this->table}";
		return (int)$this->fetchColumn($sql);
	}

	public function exists(int $id): bool
	{
		$sql = "SELECT 1 FROM {$this->table} WHERE id = :id";
		return $this->fetchColumn($sql, ['id' => $id]) !== false;
	}

	protected function buildWhereClause(array $conditions): array
	{
		if (empty($conditions))
		{
			return ['', []];
		}

		$whereParts = [];
		$params = [];

		foreach ($conditions as $field => $value)
		{
			if (is_array($value))
			{
				$placeholders = [];
				foreach ($value as $i => $v)
				{
					$placeholder = "{$field}_{$i}";
					$placeholders[] = ":$placeholder";
					$params[$placeholder] = $v;
				}
				$whereParts[] = "$field IN (" . implode(', ', $placeholders) . ")";
			}
			else
			{
				$whereParts[] = "$field = :$field";
				$params[$field] = $value;
			}
		}

		return [' WHERE ' . implode(' AND ', $whereParts), $params];
	}

	public function beginTransaction(): void
	{
		$this->db->beginTransaction();
	}

	public function commit(): void
	{
		$this->db->commit();
	}

	public function rollback(): void
	{
		$this->db->rollback();
	}
}
