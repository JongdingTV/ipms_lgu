<?php
/**
 * OTP generation, delivery, and verification for email confirmation.
 */

require_once __DIR__ . '/../includes/config.php';

class OTPManager {
    private PDO $db;
    private int $otp_validity_minutes = 2;

    public function __construct() {
        global $pdo;
        $this->db = $pdo;
    }

    public function generateOTP(int $length = 6): string {
        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new OTP for a user. Older unverified codes for the same user
     * are left in place but become unreachable, since verification only
     * ever looks at the most recent row.
     */
    public function createOTP(int $user_id): array {
        try {
            $otp_code = $this->generateOTP();
            // created_at is written explicitly (not left to the DB's CURRENT_TIMESTAMP default) so
            // every timestamp on this row shares PHP's clock. The app server and DB server can run
            // in different timezones; mixing a DB-clock created_at with PHP time()/strtotime() math
            // elsewhere silently produces wildly wrong elapsed-time values.
            $createdAt = date('Y-m-d H:i:s');
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->otp_validity_minutes} minutes"));

            $stmt = $this->db->prepare('
                INSERT INTO otp_tokens (user_id, otp_code, created_at, expires_at)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$user_id, $otp_code, $createdAt, $expiresAt]);

            return [
                'success' => true,
                'otp_code' => $otp_code,
                'expires_in_minutes' => $this->otp_validity_minutes,
                'expires_at' => $expiresAt,
            ];
        } catch (Throwable $e) {
            error_log('OTP creation failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate a verification code.'];
        }
    }

    /**
     * Most recent OTP row for a user, regardless of what code was submitted.
     * Verification is driven off this so failed guesses always count against
     * the attempt limit instead of silently missing (the old query matched
     * WHERE otp_code = ?, so a wrong guess matched no row and never
     * incremented anything).
     */
    public function getLatestToken(int $user_id): ?array {
        $stmt = $this->db->prepare('
            SELECT id, otp_code, created_at, expires_at, verified, attempts, max_attempts
            FROM otp_tokens
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute([$user_id]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);
        return $token ?: null;
    }

    /**
     * How many OTPs have been requested for this user in the last hour,
     * used to cap resend abuse (email bombing) independent of the
     * short per-code cooldown enforced by the caller.
     */
    public function countRecentRequests(int $user_id, int $windowMinutes = 60): int {
        // Boundary computed in PHP, not SQL NOW(), to stay on the same clock as created_at.
        $boundary = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM otp_tokens
            WHERE user_id = ? AND created_at >= ?
        ');
        $stmt->execute([$user_id, $boundary]);
        return (int) $stmt->fetchColumn();
    }

    public function verifyOTP(int $user_id, string $submittedCode): array {
        $token = $this->getLatestToken($user_id);

        if (!$token) {
            return ['success' => false, 'message' => 'No verification code found. Please request a new one.'];
        }

        if ((int) $token['verified'] === 1) {
            return ['success' => false, 'message' => 'This code was already used. Please request a new one.'];
        }

        if (strtotime($token['expires_at']) < time()) {
            return ['success' => false, 'message' => 'This code has expired. Please request a new one.'];
        }

        if ((int) $token['attempts'] >= (int) $token['max_attempts']) {
            return ['success' => false, 'message' => 'Too many incorrect attempts. Please request a new one.'];
        }

        // Constant-time comparison so response timing can't leak how much of the code matched.
        if (!hash_equals((string) $token['otp_code'], $submittedCode)) {
            $this->db->prepare('UPDATE otp_tokens SET attempts = attempts + 1 WHERE id = ?')
                ->execute([$token['id']]);

            $remaining = (int) $token['max_attempts'] - ((int) $token['attempts'] + 1);
            return [
                'success' => false,
                'message' => $remaining > 0
                    ? "Incorrect code. {$remaining} attempt(s) remaining."
                    : 'Too many incorrect attempts. Please request a new one.',
            ];
        }

        $this->db->prepare('UPDATE otp_tokens SET verified = 1, verified_at = NOW() WHERE id = ?')
            ->execute([$token['id']]);

        return ['success' => true, 'message' => 'OTP verified successfully'];
    }

    public function sendOTPEmail(string $user_email, string $user_name, string $otp_code): array {
        $subject = 'Your IPMS verification code';

        $safeName = htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($otp_code, ENT_QUOTES, 'UTF-8');

        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; padding: 20px; border-radius: 5px; }
                .header { color: #0f7a5f; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
                .content { color: #333; line-height: 1.6; }
                .otp-box { background-color: #f0f0f0; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px; }
                .otp-code { font-size: 32px; font-weight: bold; color: #0f7a5f; letter-spacing: 5px; }
                .footer { color: #666; font-size: 12px; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>Infrastructure Project Management System (IPMS)</div>
                <div class='content'>
                    <p>Hello <strong>{$safeName}</strong>,</p>
                    <p>Your verification code is:</p>
                    <div class='otp-box'>
                        <div class='otp-code'>{$safeCode}</div>
                    </div>
                    <p><strong>Validity:</strong> This code expires in {$this->otp_validity_minutes} minute(s).</p>
                    <p><strong>Security note:</strong> Never share this code with anyone. The IPMS team will never ask you for it.</p>
                    <p>If you did not request this, you can safely ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->sendEmail($user_email, $subject, $message);
    }

    private function sendEmail(string $to, string $subject, string $htmlBody): array {
        if (MAIL_PASSWORD === '' || MAIL_PASSWORD === null) {
            error_log('OTP email not sent: MAIL_PASSWORD is not configured (.env).');
            return ['success' => false, 'message' => 'Email delivery is not configured on this server yet.'];
        }

        // Strip CR/LF defensively even though $to/$subject are already validated upstream.
        $to = str_replace(["\r", "\n"], '', $to);
        $subject = str_replace(["\r", "\n"], '', $subject);

        try {
            $this->smtpSend($to, $subject, $htmlBody);
            return ['success' => true, 'message' => 'Email sent to ' . $to];
        } catch (Throwable $e) {
            error_log('OTP email send failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to send email. Please try again later.'];
        }
    }

    /**
     * Minimal SMTP client (STARTTLS) so we don't need Composer/PHPMailer just
     * to send a one-line code. PHP's mail() has no SMTP relay configured on
     * this host and would silently fail, so this talks to MAIL_HOST directly.
     */
    private function smtpSend(string $to, string $subject, string $htmlBody): void {
        $host = MAIL_HOST;
        $port = MAIL_PORT;
        $username = MAIL_USERNAME;
        $password = MAIL_PASSWORD;
        $fromEmail = MAIL_FROM_EMAIL;
        $fromName = MAIL_FROM_NAME;

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 15);
        if (!$socket) {
            throw new RuntimeException("SMTP connection to {$host}:{$port} failed: {$errstr} ({$errno})");
        }

        try {
            $localName = $_SERVER['SERVER_NAME'] ?? 'localhost';

            $this->smtpExpect($socket, '220');
            $this->smtpCommand($socket, "EHLO {$localName}", '250');
            $this->smtpCommand($socket, 'STARTTLS', '220');

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }

            $this->smtpCommand($socket, "EHLO {$localName}", '250');
            $this->smtpCommand($socket, 'AUTH LOGIN', '334');
            $this->smtpCommand($socket, base64_encode($username), '334');
            $this->smtpCommand($socket, base64_encode($password), '235');

            $this->smtpCommand($socket, "MAIL FROM:<{$fromEmail}>", '250');
            $this->smtpCommand($socket, "RCPT TO:<{$to}>", '250');
            $this->smtpCommand($socket, 'DATA', '354');

            $headers = "From: {$fromName} <{$fromEmail}>\r\n";
            $headers .= "To: <{$to}>\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= 'Date: ' . date('r') . "\r\n";

            // Dot-stuff any line that starts with '.' per RFC 5321, then terminate with the bare-dot line.
            $body = preg_replace('/^\./m', '..', $htmlBody);
            $this->smtpCommand($socket, $headers . "\r\n" . $body . "\r\n.", '250');
            $this->smtpCommand($socket, 'QUIT', '221');
        } finally {
            fclose($socket);
        }
    }

    private function smtpCommand($socket, string $command, string $expectedCode): string {
        fwrite($socket, $command . "\r\n");
        return $this->smtpExpect($socket, $expectedCode);
    }

    private function smtpExpect($socket, string $expectedCode): string {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            // A space (not '-') in column 4 marks the final line of a multi-line SMTP reply.
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        if (strpos($response, $expectedCode) !== 0) {
            throw new RuntimeException("SMTP error, expected {$expectedCode}, got: {$response}");
        }

        return $response;
    }

    public function cleanExpiredOTPs(): int {
        try {
            $stmt = $this->db->prepare('DELETE FROM otp_tokens WHERE expires_at < ?');
            $stmt->execute([date('Y-m-d H:i:s')]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('Failed to clean expired OTPs: ' . $e->getMessage());
            return 0;
        }
    }

    public function getValidityMinutes(): int {
        return $this->otp_validity_minutes;
    }
}
