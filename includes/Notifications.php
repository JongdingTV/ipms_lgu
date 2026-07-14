<?php
// ============================================================
// includes/Notifications.php — per-user notification records.
// Self-healing table (CREATE TABLE IF NOT EXISTS on every call), same
// pattern as OTPManager — this repo has no migration runner, so this is
// the real guarantee mechanism, not a migration file.
// Assumes includes/db.php has already been required by the caller.
// ============================================================

final class Notifications
{
    public static function ensureTable(): void
    {
        try {
            getDB()->exec("
                CREATE TABLE IF NOT EXISTS notifications (
                  id INT AUTO_INCREMENT PRIMARY KEY,
                  user_id INT NOT NULL,
                  type VARCHAR(40) NOT NULL DEFAULT 'general',
                  title VARCHAR(150) NOT NULL,
                  message TEXT NOT NULL,
                  link VARCHAR(255) NULL,
                  is_read TINYINT(1) NOT NULL DEFAULT 0,
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  INDEX idx_notif_user (user_id, is_read),
                  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
        }
    }

    /**
     * Fails silently on any DB error (same defensive pattern as logActivity())
     * — a notification failure should never break the action that triggered it.
     * No-ops if $userId is null/0 (e.g. a contractor row with no linked user account).
     */
    public static function notifyUser(?int $userId, string $type, string $title, string $message, ?string $link = null): void
    {
        if (!$userId) {
            return;
        }

        self::ensureTable();
        try {
            getDB()->prepare('
                INSERT INTO notifications (user_id, type, title, message, link)
                VALUES (?, ?, ?, ?, ?)
            ')->execute([$userId, $type, $title, $message, $link]);
        } catch (Throwable $e) {
        }
    }

    public static function unreadCount(int $userId): int
    {
        self::ensureTable();
        try {
            $stmt = getDB()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$userId]);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

function notifyUser(?int $userId, string $type, string $title, string $message, ?string $link = null): void
{
    Notifications::notifyUser($userId, $type, $title, $message, $link);
}
