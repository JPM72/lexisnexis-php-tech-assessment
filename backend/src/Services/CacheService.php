<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Config;
use Exception;

class CacheService
{
	private string $cacheDir;

	public function __construct()
	{
		$this->cacheDir = Config::get('storage.cache_path', 'storage/cache');
		$this->ensureCacheDirectory();
	}

	public function get(string $key): mixed
	{
		$filePath = $this->getFilePath($key);

		if (!file_exists($filePath))
		{
			return null;
		}

		$data = file_get_contents($filePath);
		if ($data === false)
		{
			return null;
		}

		$cached = json_decode($data, true);
		if (!$cached || !isset($cached['expires_at'], $cached['data']))
		{
			$this->delete($key);
			return null;
		}

		// Check if expired
		if (time() > $cached['expires_at'])
		{
			$this->delete($key);
			return null;
		}

		return $cached['data'];
	}

	public function set(string $key, mixed $data, int $ttl = 3600): bool
	{
		$filePath = $this->getFilePath($key);

		$cacheData = [
			'data' => $data,
			'created_at' => time(),
			'expires_at' => time() + $ttl,
			'key' => $key
		];

		$encoded = json_encode($cacheData, JSON_UNESCAPED_UNICODE);
		if ($encoded === false)
		{
			return false;
		}

		// Write to temporary file first, then rename for atomicity
		$tmpFile = $filePath . '.tmp';
		if (file_put_contents($tmpFile, $encoded, LOCK_EX) === false)
		{
			return false;
		}

		return rename($tmpFile, $filePath);
	}

	public function delete(string $key): bool
	{
		$filePath = $this->getFilePath($key);

		if (!file_exists($filePath))
		{
			return true;
		}

		return unlink($filePath);
	}

	public function clear(): int
	{
		$count = 0;
		$pattern = $this->cacheDir . '/*';

		foreach (glob($pattern) as $file)
		{
			if (is_file($file) && unlink($file))
			{
				$count++;
			}
		}

		return $count;
	}

	public function cleanupExpired(): int
	{
		$count = 0;
		$pattern = $this->cacheDir . '/*';

		foreach (glob($pattern) as $file)
		{
			if (!is_file($file))
			{
				continue;
			}

			$data = file_get_contents($file);
			if ($data === false)
			{
				continue;
			}

			$cached = json_decode($data, true);
			if (!$cached || !isset($cached['expires_at']))
			{
				// Invalid cache file, remove it
				if (unlink($file))
				{
					$count++;
				}
				continue;
			}

			if (time() > $cached['expires_at'])
			{
				if (unlink($file))
				{
					$count++;
				}
			}
		}

		return $count;
	}

	public function exists(string $key): bool
	{
		return $this->get($key) !== null;
	}

	public function getStats(): array
	{
		$pattern = $this->cacheDir . '/*';
		$files = glob($pattern);
		$totalSize = 0;
		$activeCount = 0;
		$expiredCount = 0;
		$oldestFile = null;
		$newestFile = null;

		foreach ($files as $file)
		{
			if (!is_file($file))
			{
				continue;
			}

			$size = filesize($file);
			$totalSize += $size;

			$mtime = filemtime($file);
			if ($oldestFile === null || $mtime < $oldestFile)
			{
				$oldestFile = $mtime;
			}
			if ($newestFile === null || $mtime > $newestFile)
			{
				$newestFile = $mtime;
			}

			// Check if expired
			$data = file_get_contents($file);
			if ($data !== false)
			{
				$cached = json_decode($data, true);
				if ($cached && isset($cached['expires_at']))
				{
					if (time() > $cached['expires_at'])
					{
						$expiredCount++;
					}
					else
					{
						$activeCount++;
					}
				}
			}
		}

		return [
			'total_files' => count($files),
			'active_entries' => $activeCount,
			'expired_entries' => $expiredCount,
			'total_size' => $totalSize,
			'total_size_formatted' => $this->formatSize($totalSize),
			'oldest_entry' => $oldestFile ? date('c', $oldestFile) : null,
			'newest_entry' => $newestFile ? date('c', $newestFile) : null,
			'cache_directory' => $this->cacheDir
		];
	}

	public function warmup(array $keys, callable $generator): array
	{
		$results = [
			'warmed' => 0,
			'failed' => 0,
			'errors' => []
		];

		foreach ($keys as $key)
		{
			try
			{
				if (!$this->exists($key))
				{
					$data = $generator($key);
					if ($this->set($key, $data))
					{
						$results['warmed']++;
					}
					else
					{
						$results['failed']++;
						$results['errors'][] = "Failed to cache key: {$key}";
					}
				}
			}
			catch (Exception $e)
			{
				$results['failed']++;
				$results['errors'][] = "Error generating data for key {$key}: " . $e->getMessage();
			}
		}

		return $results;
	}

	private function getFilePath(string $key): string
	{
		// Sanitize key for filesystem
		$safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
		return $this->cacheDir . '/' . $safeKey . '.cache';
	}

	private function ensureCacheDirectory(): void
	{
		if (!is_dir($this->cacheDir))
		{
			if (!mkdir($this->cacheDir, 0755, true))
			{
				throw new Exception("Failed to create cache directory: {$this->cacheDir}");
			}
		}

		if (!is_writable($this->cacheDir))
		{
			throw new Exception("Cache directory is not writable: {$this->cacheDir}");
		}
	}

	private function formatSize(int $size): string
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
