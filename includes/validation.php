<?php
/**
 * YBT Management Software — Validation Helpers
 */

class Validator {
    private array $errors = [];
    private array $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public static function make(array $data): self {
        return new self($data);
    }

    public function required(string $field, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? null;
        if ($value === null || $value === '' || $value === []) {
            $this->errors[$field] = "{$label} is required.";
        }
        return $this;
    }

    public function email(string $field, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$label} must be a valid email address.";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && mb_strlen($value) < $min) {
            $this->errors[$field] = "{$label} must be at least {$min} characters.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && mb_strlen($value) > $max) {
            $this->errors[$field] = "{$label} must not exceed {$max} characters.";
        }
        return $this;
    }

    public function numeric(string $field, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !is_numeric($value)) {
            $this->errors[$field] = "{$label} must be a number.";
        }
        return $this;
    }

    public function min(string $field, float $min, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && is_numeric($value) && (float)$value < $min) {
            $this->errors[$field] = "{$label} must be at least {$min}.";
        }
        return $this;
    }

    public function max(string $field, float $max, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && is_numeric($value) && (float)$value > $max) {
            $this->errors[$field] = "{$label} must not exceed {$max}.";
        }
        return $this;
    }

    public function date(string $field, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !strtotime($value)) {
            $this->errors[$field] = "{$label} must be a valid date.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field] = "{$label} contains an invalid value.";
        }
        return $this;
    }

    public function phone(string $field, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !preg_match('/^[\+]?[0-9\s\-\(\)]{7,20}$/', $value)) {
            $this->errors[$field] = "{$label} must be a valid phone number.";
        }
        return $this;
    }

    public function confirmed(string $field, string $confirmField, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        $confirm = $this->data[$confirmField] ?? '';
        if ($value !== $confirm) {
            $this->errors[$field] = "{$label} confirmation does not match.";
        }
        return $this;
    }

    public function unique(string $field, string $table, string $column, ?int $exceptId = null, string $label = ''): self {
        $label = $label ?: ucfirst(str_replace('_', ' ', $field));
        $value = $this->data[$field] ?? '';
        if ($value === '') return $this;

        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $params = [$value];
        if ($exceptId) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }
        if (Database::fetchColumn($sql, $params) > 0) {
            $this->errors[$field] = "{$label} already exists.";
        }
        return $this;
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function fails(): bool {
        return !$this->passes();
    }

    public function errors(): array {
        return $this->errors;
    }

    public function firstError(): string {
        return reset($this->errors) ?: '';
    }

    public function addError(string $field, string $message): self {
        $this->errors[$field] = $message;
        return $this;
    }

    /**
     * Get validated/sanitized value
     */
    public function get(string $field, $default = '') {
        return $this->data[$field] ?? $default;
    }
}

/**
 * Quick sanitization helpers
 */
function sanitize_string(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function sanitize_email(string $value): string {
    return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
}

function sanitize_int($value): int {
    return (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT);
}

function sanitize_float($value): float {
    return (float)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}
