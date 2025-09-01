<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Utils\TextExtractor;
use App\Config\Config;
use Exception;

class DocumentService
{
	private Document $documentModel;
	private TextExtractor $textExtractor;

	public function __construct()
	{
		$this->documentModel = new Document();
		$this->textExtractor = new TextExtractor();
	}

	public function upload(array $file): array
	{
		// Validate file
		$this->validateFile($file);

		// Generate unique filename
		$filename = $this->generateUniqueFilename($file['name']);
		$uploadPath = Config::get('storage.upload_path', 'storage/uploads');
		$filePath = $uploadPath . '/' . $filename;

		// Create upload directory if it doesn't exist
		if (!is_dir($uploadPath))
		{
			if (!mkdir($uploadPath, 0755, true))
			{
				throw new Exception('Failed to create upload directory');
			}
		}

		// Move uploaded file
		if (!move_uploaded_file($file['tmp_name'], $filePath))
		{
			throw new Exception('Failed to move uploaded file');
		}

		try
		{
			// Extract text content
			$contentText = $this->textExtractor->extract($filePath, $file['type']);

			// Prepare document data
			$documentData = [
				'title' => $this->extractTitle($file['name']),
				'filename' => $file['name'],
				'file_path' => $filePath,
				'content_text' => $contentText,
				'file_size' => $file['size'],
				'mime_type' => $file['type']
			];

			// Save to database
			$id = $this->documentModel->create($documentData);
			$documentData['id'] = $id;

			// Remove sensitive file path from response
			unset($documentData['file_path']);
			unset($documentData['content_text']);

			return $documentData;
		}
		catch (Exception $e)
		{
			// Cleanup uploaded file on error
			if (file_exists($filePath))
			{
				unlink($filePath);
			}
			throw $e;
		}
	}

	public function getList(int $page = 1, int $limit = 10, string $sortBy = 'created_at', string $sortOrder = 'DESC'): array
	{
		return $this->documentModel->getList($page, $limit, $sortBy, $sortOrder);
	}

	public function getById(int $id): ?array
	{
		$document = $this->documentModel->getById($id);

		if ($document)
		{
			// Remove sensitive information and add additional metadata
			unset($document['content_text']);

			// Add file existence check
			$document['file_exists'] = file_exists($document['file_path']);

			// Format file size
			$document['file_size_formatted'] = $this->formatFileSize($document['file_size']);

			// Remove file path from response
			unset($document['file_path']);
		}

		return $document;
	}

	public function delete(int $id): bool
	{
		$document = $this->documentModel->getById($id);

		if (!$document)
		{
			return false;
		}

		try
		{
			// Start transaction
			$this->documentModel->beginTransaction();

			// Delete from database
			$deleted = $this->documentModel->delete($id);

			if ($deleted)
			{
				// Delete physical file
				if (file_exists($document['file_path']))
				{
					if (!unlink($document['file_path']))
					{
						throw new Exception('Failed to delete physical file');
					}
				}

				$this->documentModel->commit();
				return true;
			}
			else
			{
				$this->documentModel->rollback();
				return false;
			}
		}
		catch (Exception $e)
		{
			$this->documentModel->rollback();
			throw $e;
		}
	}

	public function getStats(): array
	{
		return $this->documentModel->getDocumentStats();
	}

	public function updateMetadata(int $id, array $metadata): bool
	{
		$allowedFields = ['title'];
		$updateData = [];

		foreach ($allowedFields as $field)
		{
			if (isset($metadata[$field]))
			{
				$updateData[$field] = trim($metadata[$field]);
			}
		}

		if (empty($updateData))
		{
			return false;
		}

		return $this->documentModel->update($id, $updateData);
	}

	public function bulkDelete(array $ids): array
	{
		$results = [
			'deleted' => [],
			'failed' => []
		];

		foreach ($ids as $id)
		{
			try
			{
				if ($this->delete($id))
				{
					$results['deleted'][] = $id;
				}
				else
				{
					$results['failed'][] = ['id' => $id, 'reason' => 'Document not found'];
				}
			}
			catch (Exception $e)
			{
				$results['failed'][] = ['id' => $id, 'reason' => $e->getMessage()];
			}
		}

		return $results;
	}

	private function validateFile(array $file): void
	{
		// Check for upload errors
		if ($file['error'] !== UPLOAD_ERR_OK)
		{
			throw new Exception($this->getUploadErrorMessage($file['error']));
		}

		// Check file size
		$maxSize = Config::get('storage.max_file_size', 10485760); // 10MB default
		if ($file['size'] > $maxSize)
		{
			throw new Exception('File size exceeds maximum allowed size (' . $this->formatFileSize($maxSize) . ')');
		}

		// Check file type
		$allowedTypes = Config::get('allowed_file_types', ['text/plain', 'application/pdf']);
		if (!in_array($file['type'], $allowedTypes))
		{
			throw new Exception('File type not allowed. Allowed types: ' . implode(', ', $allowedTypes));
		}

		// Additional security check using file extension
		$allowedExtensions = ['txt', 'pdf'];
		$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if (!in_array($extension, $allowedExtensions))
		{
			throw new Exception('File extension not allowed');
		}

		// Check if file is actually uploaded
		if (!is_uploaded_file($file['tmp_name']))
		{
			throw new Exception('Invalid upload');
		}
	}

	private function generateUniqueFilename(string $originalName): string
	{
		$extension = pathinfo($originalName, PATHINFO_EXTENSION);
		$basename = pathinfo($originalName, PATHINFO_FILENAME);

		// Sanitize filename
		$basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
		$basename = substr($basename, 0, 50); // Limit length

		return time() . '_' . uniqid() . '_' . $basename . '.' . $extension;
	}

	private function extractTitle(string $filename): string
	{
		$title = pathinfo($filename, PATHINFO_FILENAME);

		// Replace underscores and dashes with spaces
		$title = str_replace(['_', '-'], ' ', $title);

		// Capitalize words
		$title = ucwords(strtolower($title));

		return $title;
	}

	private function formatFileSize(int $size): string
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
