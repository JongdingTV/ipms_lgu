<?php
// ============================================================
// includes/Settings.php — admin-editable key/value settings (system_settings table).
// Display/storage only this phase: nothing in auth/session.php reads these yet,
// so editing them here does not change real session-timeout/lockout behavior.
// Assumes includes/db.php has already been required by the caller (for getDB()).
// ============================================================

final class Settings
{
    private static ?array $cache = null;

    /** All settings, casted by value_type, keyed by setting_key. Cached per-request. */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [];
        try {
            $rows = getDB()->query('SELECT setting_key, setting_value, value_type FROM system_settings')->fetchAll();
            foreach ($rows as $row) {
                self::$cache[$row['setting_key']] = self::cast($row['setting_value'], $row['value_type']);
            }
        } catch (Throwable $e) {
            self::$cache = [];
        }

        return self::$cache;
    }

    /** Never throws — falls back to $default on any missing key or DB error. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, mixed $value, int $updatedBy): void
    {
        $valueType = match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };

        $stored = match ($valueType) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };

        $stmt = getDB()->prepare('
            INSERT INTO system_settings (setting_key, setting_value, value_type, updated_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_by = VALUES(updated_by)
        ');
        $stmt->execute([$key, $stored, $valueType, $updatedBy]);

        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    public static function refresh(): void
    {
        self::$cache = null;
    }

    private static function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => $value === '1',
            'integer' => (int) $value,
            'json' => json_decode((string) $value, true),
            default => $value,
        };
    }
}

function getSetting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

function setSetting(string $key, mixed $value, int $updatedBy): void
{
    Settings::set($key, $value, $updatedBy);
}
