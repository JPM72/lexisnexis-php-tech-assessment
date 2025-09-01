<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DocumentService;
use App\Utils\Response;
use Exception;

class DocumentController extends BaseController
{
	private DocumentService $documentService;

	public function __construct()
	{
		parent::__construct();
		$this->documentService = new DocumentService();
	}

	public function create(): void
	{
		try
		{
			if (empty($_FILES['document']))
			{
				Response::error('No file uploaded', 400);
				return;
			}

			$file = $_FILES['document'];

			// Validate file
			if ($file['error'] !== UPLOAD_ERR_OK)
			{
				Response::error('File upload failed', 400);
				return;
			}

			$result = $this->documentService->upload($file);

			$this->logActivity('document_upload', [
				'document_id' => $result['id'],
				'filename' => $result['filename'],
				'file_size' => $result['file_size']
			]);

			Response::success($result, 'Document uploaded successfully');
		}
		catch (Exception $e)
		{
			Response::error($e->getMessage(), 500);
		}
	}

	public function list(): void
	{
		try
		{
			$params = $this->getPaginationParams();
			$sortBy = $this->sanitizeString($_GET['sort'] ?? 'created_at');
			$sortOrder = strtoupper($_GET['order'] ?? 'DESC');

			// Validate sort parameters
			$allowedSorts = ['id', 'title', 'filename', 'file_size', 'created_at', 'updated_at'];
			if (!in_array($sortBy, $allowedSorts))
			{
				$sortBy = 'created_at';
			}

			if (!in_array($sortOrder, ['ASC', 'DESC']))
			{
				$sortOrder = 'DESC';
			}

			$result = $this->documentService->getList(
				$params['page'],
				$params['limit'],
				$sortBy,
				$sortOrder
			);

			Response::success($result);
		}
		catch (Exception $e)
		{
			Response::error($e->getMessage(), 500);
		}
	}

	public function get(int $id): void
	{
		try
		{
			$document = $this->documentService->getById($id);

			if (!$document)
			{
				Response::error('Document not found', 404);
				return;
			}

			$this->logActivity('document_view', ['document_id' => $id]);

			Response::success($document);
		}
		catch (Exception $e)
		{
			Response::error($e->getMessage(), 500);
		}
	}

	public function delete(int $id): void
	{
		try
		{
			$document = $this->documentService->getById($id);

			if (!$document)
			{
				Response::error('Document not found', 404);
				return;
			}

			$result = $this->documentService->delete($id);

			if ($result)
			{
				$this->logActivity('document_delete', [
					'document_id' => $id,
					'filename' => $document['filename']
				]);

				Response::success(null, 'Document deleted successfully');
			}
			else
			{
				Response::error('Failed to delete document', 500);
			}
		}
		catch (Exception $e)
		{
			Response::error($e->getMessage(), 500);
		}
	}

	public function download(int $id): void
	{
		try
		{
			$document = $this->documentService->getById($id);

			if (!$document)
			{
				Response::error('Document not found', 404);
				return;
			}

			if (!file_exists($document['file_path']))
			{
				Response::error('File not found on disk', 404);
				return;
			}

			$this->logActivity('document_download', ['document_id' => $id]);

			// Send file headers
			header('Content-Description: File Transfer');
			header('Content-Type: ' . $document['mime_type']);
			header('Content-Disposition: attachment; filename="' . $document['filename'] . '"');
			header('Content-Length: ' . filesize($document['file_path']));
			header('Cache-Control: must-revalidate');
			header('Pragma: public');

			// Read and output file
			readfile($document['file_path']);
			exit;
		}
		catch (Exception $e)
		{
			Response::error($e->getMessage(), 500);
		}
	}
}
