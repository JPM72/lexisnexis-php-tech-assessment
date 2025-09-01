<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Config;
use Exception;

class FileService
{
	private string $uploadDir;
	private int $maxFileSize;
	private array $allowedTypes;

	public function __construct()
	{
		$this->uploadDir = Config::get('storage.upload_path', 'storage/uploads');
		$this->maxFileSize = Config::get('storage.max_file_size', 10485760);
		$this->allowedTypes = Config::get('allowed_file_types', ['text/plain', 'application/pdf']);

		$this->ensureUploadDirectory();
	}

	public function validateUpload(array $file): void
	{
		if ($file['error'] !== UPLOAD_ERR_OK)
		{
			throw new Exception($this->getUploadErrorMessage($file['error']));
		}

		if ($file['size'] > $this->maxFileSize)
		{
			throw new Exception('File too large. Maximum size: ' . $this->formatSize($this->maxFileSize));
		}

		if (!in_array($file['type'], $this->allowedTypes))
		{
			throw new Exception('Invalid file type. Allowed: ' . implode(', ', $this->allowedTypes));
		}

		// Additional MIME type validation using finfo
		if (function_exists('finfo_open'))
		{
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$detectedType = finfo_file($finfo, $file['tmp_name']);
			finfo_close($finfo);

			if ($detectedType !== $file['type'])
			{
				throw new Exception('File type mismatch detected');
			}
		}

		// Validate file extension
		$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		$allowedExtensions = ['txt', 'pdf'];

		if (!in_array($extension, $allowedExtensions))
		{
			throw new Exception('Invalid file extension');
		}
	}

	public function generateUniqueFilename(string $originalName): string
	{
		$info = pathinfo($originalName);
		$extension = $info['extension'] ?? '';
		$basename = $info['filename'] ?? 'file';

		// Sanitize basename
		$basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
		$basename = substr($basename, 0, 50); // Limit length

		return sprintf(
			'%s_%s_%s.%s',
			date('Y-m-d'),
			uniqid(),
			$basename,
			$extension
		);
	}

	public function saveFile(array $file, ?string $customName = null): string
	{
		$this->validateUpload($file);

		$filename = $customName ?: $this->generateUniqueFilename($file['name']);
		$destination = $this->uploadDir . '/' . $filename;

		if (!move_uploaded_file($file['tmp_name'], $destination))
		{
			throw new Exception('Failed to save file');
		}

		// Set appropriate permissions
		chmod($destination, 0644);

		return $filename;
	}

	public function deleteFile(string $filename): bool
	{
		$filePath = $this->uploadDir . '/' . $filename;

		if (!file_exists($filePath))
		{
			return true; // Already deleted
		}

		return unlink($filePath);
	}

	public function getFileInfo(string $filename): ?array
	{
		$filePath = $this->uploadDir . '/' . $filename;

		if (!file_exists($filePath))
		{
			return null;
		}

		$stat = stat($filePath);

		return [
			'filename' => $filename,
			'path' => $filePath,
			'size' => $stat['size'],
			'size_formatted' => $this->formatSize($stat['size']),
			'created_at' => date('c', $stat['ctime']),
			'modified_at' => date('c', $stat['mtime']),
			'mime_type' => $this->getMimeType($filePath),
			'is_readable' => is_readable($filePath)
		];
	}

	public function readFileChunked(string $filename, int $chunkSize = 8192): \Generator
	{
		$filePath = $this->uploadDir . '/' . $filename;

		if (!file_exists($filePath))
		{
			throw new Exception('File not found');
		}

		$handle = fopen($filePath, 'rb');
		if (!$handle)
		{
			throw new Exception('Cannot open file');
		}

		try
		{
			while (!feof($handle))
			{
				$chunk = fread($handle, $chunkSize);
				if ($chunk === false)
				{
					break;
				}
				yield $chunk;
			}
		}
		finally
		{
			fclose($handle);
		}
	}

	public function getStorageStats(): array
	{
		$totalSize = 0;
		$fileCount = 0;
		$typeStats = [];

		$pattern = $this->uploadDir . '/*';
		foreach (glob($pattern) as $file)
		{
			if (is_file($file))
			{
				$fileCount++;
				$size = filesize($file);
				$totalSize += $size;

				$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
				if (!isset($typeStats[$extension]))
				{
					$typeStats[$extension] = ['count' => 0, 'size' => 0];
				}
				$typeStats[$extension]['count']++;
				$typeStats[$extension]['size'] += $size;
			}
		}

		// Format type stats
		foreach ($typeStats as &$stats)
		{
			$stats['size_formatted'] = $this->formatSize($stats['size']);
		}

		return [
			'total_files' => $fileCount,
			'total_size' => $totalSize,
			'total_size_formatted' => $this->formatSize($totalSize),
			'by_type' => $typeStats,
			'upload_directory' => $this->uploadDir,
			'max_file_size' => $this->maxFileSize,
			'max_file_size_formatted' => $this->formatSize($this->maxFileSize),
			'allowed_types' => $this->allowedTypes,
			'disk_free_space' => disk_free_space($this->uploadDir),
			'disk_free_space_formatted' => $this->formatSize((int)disk_free_space($this->uploadDir))
		];
	}

	public function cleanupOrphanedFiles(array $validFilenames): array
	{
		$deleted = [];
		$pattern = $this->uploadDir . '/*';

		foreach (glob($pattern) as $file)
		{
			if (is_file($file))
			{
				$filename = basename($file);

				if (!in_array($filename, $validFilenames))
				{
					if (unlink($file))
					{
						$deleted[] = $filename;
					}
				}
			}
		}

		return $deleted;
	}

	public function createBackup(string $filename, string $backupDir): bool
	{
		$sourcePath = $this->uploadDir . '/' . $filename;

		if (!file_exists($sourcePath))
		{
			return false;
		}

		if (!is_dir($backupDir))
		{
			mkdir($backupDir, 0755, true);
		}

		$backupPath = $backupDir . '/' . date('Y-m-d_H-i-s_') . $filename;

		return copy($sourcePath, $backupPath);
	}

	private function ensureUploadDirectory(): void
	{
		if (!is_dir($this->uploadDir))
		{
			if (!mkdir($this->uploadDir, 0755, true))
			{
				throw new Exception("Failed to create upload directory: {$this->uploadDir}");
			}
		}

		if (!is_writable($this->uploadDir))
		{
			throw new Exception("Upload directory is not writable: {$this->uploadDir}");
		}

		// Create .htaccess file for security
		$htaccessPath = $this->uploadDir . '/.htaccess';
		if (!file_exists($htaccessPath))
		{
			$htaccessContent = "Options -Indexes\nOptions -ExecCGI\nAddHandler cgi-script .php .php3 .php4 .phtml .pl .py .jsp .asp .sh .cgi\n";
			file_put_contents($htaccessPath, $htaccessContent);
		}
	}

	private function getMimeType(string $filePath): string
	{
		if (function_exists('finfo_open'))
		{
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mimeType = finfo_file($finfo, $filePath);
			finfo_close($finfo);
			return $mimeType ?: 'application/octet-stream';
		}

		return mime_content_type($filePath) ?: 'application/octet-stream';
	}

	private function formatSize(int $size): string
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$unitIndex = 0;

		while ($size >= 1024 && $unitIndex < count($units) - 1)
		{
			$size /= 1024;
			$unitIndex++;
		}

		return round($size, 2) . ' ' . $units[$unitIndex];
	}

	private function getUploadErrorMessage(int $error): string
	{
		switch ($error)
		{
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return 'File is too large';
			case UPLOAD_ERR_PARTIAL:
				return 'File was only partially uploaded';
			case UPLOAD_ERR_NO_FILE:
				return 'No file was uploaded';
			case UPLOAD_ERR_NO_TMP_DIR:
				return 'Missing temporary folder';
			case UPLOAD_ERR_CANT_WRITE:
				return 'Failed to write file to disk';
			case UPLOAD_ERR_EXTENSION:
				return 'File upload stopped by extension';
			default:
				return 'Unknown upload error';
		}
	}
}
