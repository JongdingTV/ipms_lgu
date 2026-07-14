<?php
/**
 * OTP Management System
 * Implements OTP with 1-2 minute expiration as per panelist requirements.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class OTPManager
{
    private PDO $db;
    private int $otp_validity_minutes = 2; // 1-2 minutes (configurable)

    public function __construct()
    {
        $this->db = getDB();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS otp_tokens (
              id INT AUTO_INCREMENT PRIMARY KEY,
              user_id INT NOT NULL,
              otp_code VARCHAR(10) NOT NULL,
              verified TINYINT(1) NOT NULL DEFAULT 0,
              attempts INT UNSIGNED NOT NULL DEFAULT 0,
              max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
              expires_at DATETIME NOT NULL,
              verified_at DATETIME NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_otp_user (user_id),
              INDEX idx_otp_expires (expires_at),
              CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Self-healing column add: this repo has no migration runner, so any
        // environment that only ever relied on this class to create the table
        // (rather than running database/migrations/add_purpose_to_otp_tokens.sql)
        // still ends up with the purpose column present.
        try {
            $this->db->exec("ALTER TABLE otp_tokens ADD COLUMN IF NOT EXISTS purpose VARCHAR(30) NOT NULL DEFAULT 'general' AFTER user_id");
        } catch (Throwable $e) {
        }
    }

    /** Generate a numeric OTP code of the given length. */
    public function generateOTP($length = 6): string
    {
        return str_pad((string) random_int(0, (int) pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /** Create and persist a new OTP for a user, scoped to $purpose. */
    public function createOTP($user_id, string $purpose = 'general'): array
    {
        try {
            $this->cleanExpiredOTPs();

            $otp_code = $this->generateOTP();
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$this->otp_validity_minutes} minutes"));

            $stmt = $this->db->prepare('
                INSERT INTO otp_tokens (user_id, purpose, otp_code, expires_at)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$user_id, $purpose, $otp_code, $expires_at]);

            return [
                'success' => true,
                'otp_code' => $otp_code,
                'expires_in_minutes' => $this->otp_validity_minutes,
                'expires_at' => $expires_at,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate OTP: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * True if $user_id already has an unexpired, unverified OTP for $purpose
     * created within the last $withinSeconds — used to throttle unauthenticated
     * senders (e.g. forgot-password) against repeated-submission email spam.
     */
    public function hasRecentOTP($user_id, string $purpose, int $withinSeconds): bool
    {
        try {
            $stmt = $this->db->prepare('
                SELECT created_at FROM otp_tokens
                WHERE user_id = ? AND purpose = ?
                ORDER BY created_at DESC
                LIMIT 1
            ');
            $stmt->execute([$user_id, $purpose]);
            $createdAt = $stmt->fetchColumn();

            return $createdAt !== false && (time() - strtotime((string) $createdAt)) < $withinSeconds;
        } catch (Exception $e) {
            return false;
        }
    }

    /** Send an OTP code to a user's email. */
    public function sendOTPEmail($user_email, $user_name, $otp_code): array
    {
        $subject = 'Your IPMS One-Time Password (OTP)';

        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; padding: 20px; border-radius: 5px; }
                .header { color: #116466; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
                .content { color: #333; line-height: 1.6; }
                .otp-box { background-color: #f0f0f0; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px; }
                .otp-code { font-size: 32px; font-weight: bold; color: #116466; letter-spacing: 5px; }
                .footer { color: #666; font-size: 12px; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>Infrastructure Project Management System (IPMS)</div>
                <div class='content'>
                    <p>Hello <strong>" . htmlspecialchars((string) $user_name, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
                    <p>Your One-Time Password (OTP) for IPMS is:</p>
                    <div class='otp-box'>
                        <div class='otp-code'>" . htmlspecialchars((string) $otp_code, ENT_QUOTES, 'UTF-8') . "</div>
                    </div>
                    <p><strong>Validity:</strong> This OTP expires in {$this->otp_validity_minutes} minute(s)</p>
                    <p><strong>Security note:</strong> Never share this OTP with anyone. The IPMS team will never ask you for this code.</p>
                    <p>If you did not request this OTP, please ignore this email or contact support immediately.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " Infrastructure Project Management System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->sendEmail($user_email, $subject, $message);
    }

    /**
     * Sends a plain (non-OTP) notification email — e.g. "your application was
     * approved" — reusing the same SMTP sender as sendOTPEmail(). $bodyHtml is
     * wrapped in the same visual shell so approval emails look consistent
     * with OTP emails.
     */
    public function sendPlainEmail(string $to, string $subject, string $bodyHtml): array
    {
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; padding: 20px; border-radius: 5px; }
                .header { color: #116466; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
                .content { color: #333; line-height: 1.6; }
                .footer { color: #666; font-size: 12px; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>Infrastructure Project Management System (IPMS)</div>
                <div class='content'>{$bodyHtml}</div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " Infrastructure Project Management System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->sendEmail($to, $subject, $message);
    }

    /** Sends via real SMTP when credentials are configured. */
    private function sendEmail($to, $subject, $message): array
    {
        if (MAIL_PASSWORD === '') {
            // No SMTP credentials configured. Fail closed in production so a
            // misconfigured server never silently skips verification; outside
            // production the caller may fall back to showing the code
            // directly so the flow stays demoable without real mail setup.
            return [
                'success' => false,
                'dev_fallback' => APP_ENV !== 'production',
                'message' => 'Mail is not configured on this server.',
            ];
        }

        try {
            $this->smtpSend((string) $to, $subject, $message);
            return ['success' => true, 'message' => 'OTP sent successfully to ' . $to];
        } catch (Throwable $e) {
            error_log('OTP email send failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send the verification email. Please try again later.'];
        }
    }

    /** Minimal dependency-free SMTP client (STARTTLS/AUTH LOGIN) for MAIL_* config. */
    private function smtpSend(string $to, string $subject, string $htmlBody): void
    {
        $host = MAIL_HOST;
        $port = (int) MAIL_PORT;
        $encryption = strtolower(MAIL_ENCRYPTION);
        $scheme = $encryption === 'ssl' ? 'ssl' : 'tcp';

        $socket = @stream_socket_client("{$scheme}://{$host}:{$port}", $errno, $errstr, 15);
        if (!$socket) {
            throw new RuntimeException("Unable to connect to mail server ({$errstr}).");
        }
        stream_set_timeout($socket, 15);

        $domain = ltrim((string) strrchr(MAIL_FROM_EMAIL, '@'), '@') ?: 'localhost';

        $this->smtpReadResponse($socket, '220');
        $this->smtpCommand($socket, "EHLO {$domain}", '250');

        if ($encryption === 'tls') {
            $this->smtpCommand($socket, 'STARTTLS', '220');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed.');
            }
            $this->smtpCommand($socket, "EHLO {$domain}", '250');
        }

        $this->smtpCommand($socket, 'AUTH LOGIN', '334');
        $this->smtpCommand($socket, base64_encode(MAIL_USERNAME), '334');
        $this->smtpCommand($socket, base64_encode(MAIL_PASSWORD), '235');

        $this->smtpCommand($socket, 'MAIL FROM:<' . MAIL_FROM_EMAIL . '>', '250');
        $this->smtpCommand($socket, 'RCPT TO:<' . $to . '>', '250');
        $this->smtpCommand($socket, 'DATA', '354');

        $headers = implode("\r\n", [
            'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Date: ' . date('r'),
        ]);
        $body = str_replace("\n.", "\n..", $htmlBody);
        $this->smtpCommand($socket, $headers . "\r\n\r\n" . $body . "\r\n.", '250');

        fwrite($socket, "QUIT\r\n");
        fclose($socket);
    }

    private function smtpCommand($socket, string $command, string $expectedCode): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->smtpReadResponse($socket, $expectedCode);
    }

    private function smtpReadResponse($socket, string $expectedCode): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (!isset($line[3]) || $line[3] !== '-') {
                break;
            }
        }
        if (substr($response, 0, 3) !== $expectedCode) {
            throw new RuntimeException('Unexpected SMTP response: ' . trim($response));
        }
        return $response;
    }

    /**
     * Verify an OTP code for a user. Looks up the user's latest token (not
     * one matching the guessed code) so wrong guesses can actually be
     * counted against max_attempts — the original version compared
     * user_id+otp_code together, which meant a wrong guess matched zero rows
     * and attempts could never be incremented.
     */
    public function verifyOTP($user_id, $otp_code, string $purpose = 'general'): array
    {
        try {
            $stmt = $this->db->prepare('
                SELECT id, otp_code, verified, attempts, max_attempts, expires_at
                FROM otp_tokens
                WHERE user_id = ? AND purpose = ?
                ORDER BY created_at DESC
                LIMIT 1
            ');
            $stmt->execute([$user_id, $purpose]);
            $token = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$token) {
                return ['success' => false, 'message' => 'No OTP was requested for this account.'];
            }
            if ((int) $token['verified'] === 1) {
                return ['success' => false, 'message' => 'This code was already used. Please request a new one.'];
            }
            if (strtotime($token['expires_at']) < time()) {
                return ['success' => false, 'message' => 'This code has expired. Please request a new one.'];
            }
            if ((int) $token['attempts'] >= (int) $token['max_attempts']) {
                return ['success' => false, 'message' => 'Too many incorrect attempts. Please request a new code.'];
            }

            if (!hash_equals((string) $token['otp_code'], (string) $otp_code)) {
                $this->db->prepare('UPDATE otp_tokens SET attempts = attempts + 1 WHERE id = ?')->execute([$token['id']]);
                $remaining = max(0, (int) $token['max_attempts'] - (int) $token['attempts'] - 1);
                return ['success' => false, 'message' => "Incorrect code. {$remaining} attempt(s) remaining."];
            }

            $this->db->prepare('UPDATE otp_tokens SET verified = 1, verified_at = NOW() WHERE id = ?')
                ->execute([$token['id']]);

            return ['success' => true, 'message' => 'OTP verified successfully', 'token_id' => $token['id']];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'OTP verification failed: ' . $e->getMessage()];
        }
    }

    /** Clean expired OTPs. */
    public function cleanExpiredOTPs(): int
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM otp_tokens WHERE expires_at < NOW()');
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log('Failed to clean expired OTPs: ' . $e->getMessage());
            return 0;
        }
    }

    public function getValidityMinutes(): int
    {
        return $this->otp_validity_minutes;
    }

    public function setValidityMinutes(int $minutes): void
    {
        if ($minutes >= 1 && $minutes <= 5) {
            $this->otp_validity_minutes = $minutes;
        }
    }
}
