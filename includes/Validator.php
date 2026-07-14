<?php
// ============================================================
// includes/Validator.php — shared request validation.
// Assumes includes/db.php has already been required by the caller
// (for respond()), matching the convention every api/*.php already follows.
// ============================================================
require_once __DIR__ . '/FileUpload.php';

final class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    private function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->run();
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleSpec) {
            $rulesList = is_array($ruleSpec) ? $ruleSpec : explode('|', (string) $ruleSpec);
            $value = $this->data[$field] ?? null;
            $isPresent = array_key_exists($field, $this->data) && $value !== null && $value !== '';

            if (!$isPresent) {
                if (in_array('required', $rulesList, true)) {
                    $this->errors[$field] = $this->fieldLabel($field) . ' is required.';
                }
                continue;
            }

            foreach ($rulesList as $rule) {
                if (isset($this->errors[$field])) {
                    break;
                }
                $this->applyRule($field, $value, (string) $rule);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
        $label = $this->fieldLabel($field);

        switch ($name) {
            case 'required':
            case 'nullable':
                break;

            case 'string':
                if (!is_scalar($value)) {
                    $this->errors[$field] = $label . ' must be text.';
                }
                break;

            case 'min':
                if (is_string($value) && mb_strlen($value) < (int) $param) {
                    $this->errors[$field] = $label . ' must be at least ' . $param . ' characters.';
                } elseif (!is_string($value) && is_numeric($value) && (float) $value < (float) $param) {
                    $this->errors[$field] = $label . ' must be at least ' . $param . '.';
                }
                break;

            case 'max':
                if (is_string($value) && mb_strlen($value) > (int) $param) {
                    $this->errors[$field] = $label . ' must be at most ' . $param . ' characters.';
                } elseif (!is_string($value) && is_numeric($value) && (float) $value > (float) $param) {
                    $this->errors[$field] = $label . ' must be at most ' . $param . '.';
                }
                break;

            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->errors[$field] = $label . ' must be a whole number.';
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->errors[$field] = $label . ' must be a number.';
                }
                break;

            case 'boolean':
                $normalized = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
                if (!in_array($normalized, ['0', '1'], true)) {
                    $this->errors[$field] = $label . ' must be true or false.';
                }
                break;

            case 'in':
                $allowed = $param !== null ? explode(',', $param) : [];
                $normalized = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
                if (!in_array($normalized, $allowed, true)) {
                    $this->errors[$field] = $label . ' must be one of: ' . implode(', ', $allowed) . '.';
                }
                break;

            case 'email':
                if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field] = $label . ' must be a valid email address.';
                }
                break;

            case 'date':
                $timestamp = is_scalar($value) ? strtotime((string) $value) : false;
                if ($timestamp === false) {
                    $this->errors[$field] = $label . ' must be a valid date.';
                } elseif ($param === 'future' && $timestamp <= time()) {
                    $this->errors[$field] = $label . ' must be a future date.';
                } elseif ($param === 'past' && $timestamp >= time()) {
                    $this->errors[$field] = $label . ' must be a past date.';
                }
                break;
        }
    }

    private function fieldLabel(string $field): string
    {
        return ucfirst(str_replace(['_', '.'], ' ', $field));
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function addError(string $field, string $message): self
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    /** Only the keys mentioned in $rules, trimmed if scalar strings. */
    public function validated(): array
    {
        $out = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $value = $this->data[$field];
                $out[$field] = is_string($value) ? trim($value) : $value;
            }
        }
        return $out;
    }

    /** Responds with {error, errors} and exits if validation failed; otherwise returns validated(). */
    public function stopOnFailure(string $summary = 'Validation failed.', int $status = 422): array
    {
        if ($this->fails()) {
            respond(['error' => $summary, 'errors' => $this->errors()], $status);
        }
        return $this->validated();
    }

    /** Passthrough so file-field errors land in the same {field: message} shape as scalar rules. */
    public static function file(?array $fileEntry, array $constraints): ?string
    {
        return FileUpload::validate($fileEntry, $constraints);
    }

    /**
     * Returns the first failing rule's message, or null if the password passes.
     * Mirrors citizen/register.php's inline checks exactly — not wired into the
     * generic rule engine above since password rules aren't a single reusable
     * field-shape (they're an ordered checklist with distinct messages).
     */
    public static function passwordStrength(string $password): ?string
    {
        if (mb_strlen($password) < 8) {
            return 'Password must be at least 8 characters long.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number.';
        }
        if (!preg_match('/[!@#$%^&*]/', $password)) {
            return 'Password must contain at least one special character (!@#$%^&*).';
        }
        return null;
    }
}
