<?php

declare(strict_types=1);

namespace App\Utils;

class Validator
{
	private array $errors = [];

	public function validate(array $data, array $rules): bool
	{
		$this->errors = [];

		foreach ($rules as $field => $fieldRules)
		{
			$value = $data[$field] ?? null;
			$this->validateField($field, $value, $fieldRules);
		}

		return empty($this->errors);
	}

	public function getErrors(): array
	{
		return $this->errors;
	}

	public function getFirstError(): ?string
	{
		return empty($this->errors) ? null : reset($this->errors)[0];
	}

	public function hasErrors(): bool
	{
		return !empty($this->errors);
	}

	private function validateField(string $field, $value, array $rules): void
	{
		foreach ($rules as $rule)
		{
			if (is_string($rule))
			{
				$this->applyRule($field, $value, $rule);
			}
			elseif (is_array($rule))
			{
				$ruleName = $rule[0];
				$ruleParams = array_slice($rule, 1);
				$this->applyRule($field, $value, $ruleName, $ruleParams);
			}
		}
	}

	private function applyRule(string $field, $value, string $rule, array $params = []): void
	{
		switch ($rule)
		{
			case 'required':
				if ($value === null || $value === '')
				{
					$this->addError($field, 'This field is required');
				}
				break;

			case 'string':
				if ($value !== null && !is_string($value))
				{
					$this->addError($field, 'This field must be a string');
				}
				break;

			case 'integer':
				if ($value !== null && !is_int($value) && !ctype_digit((string)$value))
				{
					$this->addError($field, 'This field must be an integer');
				}
				break;

			case 'numeric':
				if ($value !== null && !is_numeric($value))
				{
					$this->addError($field, 'This field must be numeric');
				}
				break;

			case 'email':
				if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL))
				{
					$this->addError($field, 'This field must be a valid email address');
				}
				break;

			case 'url':
				if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL))
				{
					$this->addError($field, 'This field must be a valid URL');
				}
				break;

			case 'min':
				$min = $params[0] ?? 0;
				if (is_string($value) && strlen($value) < $min)
				{
					$this->addError($field, "This field must be at least {$min} characters long");
				}
				elseif (is_numeric($value) && $value < $min)
				{
					$this->addError($field, "This field must be at least {$min}");
				}
				break;

			case 'max':
				$max = $params[0] ?? PHP_INT_MAX;
				if (is_string($value) && strlen($value) > $max)
				{
					$this->addError($field, "This field must not exceed {$max} characters");
				}
				elseif (is_numeric($value) && $value > $max)
				{
					$this->addError($field, "This field must not exceed {$max}");
				}
				break;

			case 'length':
				$length = $params[0] ?? 0;
				if (is_string($value) && strlen($value) !== $length)
				{
					$this->addError($field, "This field must be exactly {$length} characters long");
				}
				break;

			case 'in':
				if ($value !== null && !in_array($value, $params))
				{
					$allowed = implode(', ', $params);
					$this->addError($field, "This field must be one of: {$allowed}");
				}
				break;

			case 'not_in':
				if ($value !== null && in_array($value, $params))
				{
					$forbidden = implode(', ', $params);
					$this->addError($field, "This field must not be one of: {$forbidden}");
				}
				break;

			case 'regex':
				$pattern = $params[0] ?? '';
				if ($value !== null && !preg_match($pattern, $value))
				{
					$this->addError($field, 'This field format is invalid');
				}
				break;

			case 'alpha':
				if ($value !== null && !ctype_alpha($value))
				{
					$this->addError($field, 'This field must contain only letters');
				}
				break;

			case 'alpha_numeric':
				if ($value !== null && !ctype_alnum($value))
				{
					$this->addError($field, 'This field must contain only letters and numbers');
				}
				break;

			case 'boolean':
				if ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false']))
				{
					$this->addError($field, 'This field must be a boolean');
				}
				break;

			case 'array':
				if ($value !== null && !is_array($value))
				{
					$this->addError($field, 'This field must be an array');
				}
				break;

			case 'file':
				if (!$this->isValidFile($value))
				{
					$this->addError($field, 'This field must be a valid file');
				}
				break;

			case 'image':
				if (!$this->isValidImage($value))
				{
					$this->addError($field, 'This field must be a valid image');
				}
				break;

			case 'date':
				if ($value !== null && !$this->isValidDate($value))
				{
					$this->addError($field, 'This field must be a valid date');
				}
				break;

			case 'unique':
				// This would require database access - placeholder for now
				break;

			case 'exists':
				// This would require database access - placeholder for now
				break;
		}
	}

	private function addError(string $field, string $message): void
	{
		if (!isset($this->errors[$field]))
		{
			$this->errors[$field] = [];
		}
		$this->errors[$field][] = $message;
	}

	private function isValidFile($value): bool
	{
		return is_array($value) &&
			isset($value['tmp_name'], $value['name'], $value['size'], $value['error']) &&
			$value['error'] === UPLOAD_ERR_OK;
	}

	private function isValidImage($value): bool
	{
		if (!$this->isValidFile($value))
		{
			return false;
		}

		$imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
		return in_array($value['type'], $imageTypes);
	}

	private function isValidDate(string $date): bool
	{
		$formats = ['Y-m-d', 'Y-m-d H:i:s', 'Y/m/d', 'd/m/Y', 'Y-m-d\TH:i:s'];

		foreach ($formats as $format)
		{
			$dateTime = \DateTime::createFromFormat($format, $date);
			if ($dateTime && $dateTime->format($format) === $date)
			{
				return true;
			}
		}

		return false;
	}

	public static function sanitize(array $data): array
	{
		$sanitized = [];

		foreach ($data as $key => $value)
		{
			if (is_string($value))
			{
				$sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
			}
			elseif (is_array($value))
			{
				$sanitized[$key] = self::sanitize($value);
			}
			else
			{
				$sanitized[$key] = $value;
			}
		}

		return $sanitized;
	}
}
