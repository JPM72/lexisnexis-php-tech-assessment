<?php

declare(strict_types=1);

namespace App\Utils;

use Smalot\PdfParser\Parser;
use Exception;

class TextExtractor
{
	private Parser $pdfParser;

	public function __construct()
	{
		$this->pdfParser = new Parser();
	}

	public function extract(string $filePath, string $mimeType): string
	{
		if (!file_exists($filePath))
		{
			throw new Exception('File not found');
		}

		switch ($mimeType)
		{
			case 'text/plain':
				return $this->extractFromText($filePath);

			case 'application/pdf':
				return $this->extractFromPdf($filePath);

			default:
				throw new Exception("Unsupported file type: {$mimeType}");
		}
	}

	private function extractFromText(string $filePath): string
	{
		$content = file_get_contents($filePath);

		if ($content === false)
		{
			throw new Exception('Failed to read text file');
		}

		// Clean up the text
		return $this->cleanText($content);
	}

	private function extractFromPdf(string $filePath): string
	{
		try
		{
			$pdf = $this->pdfParser->parseFile($filePath);
			$text = $pdf->getText();

			return $this->cleanText($text);
		}
		catch (Exception $e)
		{
			throw new Exception('Failed to extract text from PDF: ' . $e->getMessage());
		}
	}

	private function cleanText(string $text): string
	{
		// Remove null bytes
		$text = str_replace("\0", '', $text);

		// Normalize line endings
		$text = str_replace(["\r\n", "\r"], "\n", $text);

		// Remove excessive whitespace but preserve paragraph structure
		$text = preg_replace('/[ \t]+/', ' ', $text);
		$text = preg_replace('/\n{3,}/', "\n\n", $text);

		// Remove control characters except tabs, newlines, and carriage returns
		$text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

		// Trim whitespace
		$text = trim($text);

		// Ensure UTF-8 encoding
		if (!mb_check_encoding($text, 'UTF-8'))
		{
			$text = mb_convert_encoding($text, 'UTF-8', 'auto');
		}

		return $text;
	}

	public function extractWithMetadata(string $filePath, string $mimeType): array
	{
		$text = $this->extract($filePath, $mimeType);

		return [
			'content' => $text,
			'word_count' => $this->countWords($text),
			'character_count' => mb_strlen($text),
			'line_count' => substr_count($text, "\n") + 1,
			'paragraph_count' => $this->countParagraphs($text),
			'language' => $this->detectLanguage($text),
			'extracted_at' => date('c')
		];
	}

	public function extractChunked(string $filePath, string $mimeType, int $chunkSize = 1000): \Generator
	{
		$text = $this->extract($filePath, $mimeType);
		$words = explode(' ', $text);

		$chunks = array_chunk($words, $chunkSize);

		foreach ($chunks as $chunk)
		{
			yield implode(' ', $chunk);
		}
	}

	public function getTextPreview(string $filePath, string $mimeType, int $maxLength = 500): string
	{
		$text = $this->extract($filePath, $mimeType);

		if (mb_strlen($text) <= $maxLength)
		{
			return $text;
		}

		$preview = mb_substr($text, 0, $maxLength);

		// Try to end at a word boundary
		$lastSpace = mb_strrpos($preview, ' ');
		if ($lastSpace !== false && $lastSpace > $maxLength * 0.8)
		{
			$preview = mb_substr($preview, 0, $lastSpace);
		}

		return $preview . '...';
	}

	public function extractKeywords(string $text, int $limit = 10): array
	{
		// Simple keyword extraction - in production, you might use more sophisticated NLP
		$text = strtolower($text);

		// Remove common stop words
		$stopWords = [
			'the',
			'a',
			'an',
			'and',
			'or',
			'but',
			'in',
			'on',
			'at',
			'to',
			'for',
			'of',
			'with',
			'by',
			'from',
			'up',
			'about',
			'into',
			'through',
			'during',
			'before',
			'after',
			'above',
			'below',
			'between',
			'among',
			'throughout',
			'is',
			'are',
			'was',
			'were',
			'be',
			'been',
			'being',
			'have',
			'has',
			'had',
			'do',
			'does',
			'did',
			'will',
			'would',
			'could',
			'should',
			'may',
			'might',
			'must',
			'shall',
			'can',
			'this',
			'that',
			'these',
			'those',
			'i',
			'me',
			'my',
			'myself',
			'we',
			'us',
			'our',
			'ours',
			'ourselves',
			'you',
			'your',
			'yours',
			'yourself',
			'yourselves',
			'he',
			'him',
			'his',
			'himself',
			'she',
			'her',
			'hers',
			'herself',
			'it',
			'its',
			'itself',
			'they',
			'them',
			'their',
			'theirs',
			'themselves',
			'what',
			'which',
			'who',
			'whom',
			'whose',
			'where',
			'when',
			'why',
			'how',
			'all',
			'any',
			'both',
			'each',
			'few',
			'more',
			'most',
			'other',
			'some',
			'such',
			'no',
			'nor',
			'not',
			'only',
			'own',
			'same',
			'so',
			'than',
			'too',
			'very'
		];

		// Extract words (3+ characters)
		preg_match_all('/\b[a-z]{3,}\b/', $text, $matches);
		$words = $matches[0];

		// Remove stop words
		$words = array_diff($words, $stopWords);

		// Count word frequency
		$wordCounts = array_count_values($words);

		// Sort by frequency
		arsort($wordCounts);

		return array_slice(array_keys($wordCounts), 0, $limit);
	}

	private function countWords(string $text): int
	{
		return str_word_count($text);
	}

	private function countParagraphs(string $text): int
	{
		$paragraphs = preg_split('/\n\s*\n/', $text);
		return count(array_filter($paragraphs, fn($p) => trim($p) !== ''));
	}

	private function detectLanguage(string $text): string
	{
		// Simple language detection - in production, you might use a proper language detection library
		$sample = mb_substr($text, 0, 1000);

		// Count common English words
		$englishWords = ['the', 'and', 'is', 'in', 'to', 'of', 'a', 'that', 'it', 'with'];
		$englishCount = 0;

		foreach ($englishWords as $word)
		{
			$englishCount += substr_count(strtolower($sample), $word);
		}

		// Simple heuristic - if we find many English words, assume English
		if ($englishCount > 5)
		{
			return 'en';
		}

		return 'unknown';
	}

	public function getSupportedTypes(): array
	{
		return [
			'text/plain' => [
				'extensions' => ['txt'],
				'description' => 'Plain text files'
			],
			'application/pdf' => [
				'extensions' => ['pdf'],
				'description' => 'PDF documents'
			]
		];
	}

	public function canExtract(string $mimeType): bool
	{
		return array_key_exists($mimeType, $this->getSupportedTypes());
	}
}
