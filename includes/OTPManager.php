<?php
/**
 * OTP Management System
 * Implements OTP with 1-2 minute expiration as per panelist requirements
 */

require_once __DIR__ . '/../includes/config.php';

class OTPManager {
    private $db;
    private $otp_validity_minutes = 2; // 1-2 minutes (configurable)
    
    public function __construct() {
        global $pdo;
        $this->db = $pdo;
    }
    
    /**
     * Generate OTP code
     */
    public function generateOTP($length = 6): string {
        return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
    
    /**
     * Create OTP for user
     */
    public function createOTP($user_id): array {
        try {
            $otp_code = $this->generateOTP();
            $expires_at = date('Y-m-d H:i:s', strtotime("+{$this->otp_validity_minutes} minutes"));
            
            $stmt = $this->db->prepare('
                INSERT INTO otp_tokens (user_id, otp_code, expires_at)
                VALUES (?, ?, ?)
            ');
            
            $stmt->execute([$user_id, $otp_code, $expires_at]);
            
            return [
                'success' => true,
                'otp_code' => $otp_code,
                'expires_in_minutes' => $this->otp_validity_minutes,
                'expires_at' => $expires_at
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to generate OTP: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send OTP via email
     */
    public function sendOTPEmail($user_email, $user_name, $otp_code): array {
        try {
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
                        <p>Hello <strong>{$user_name}</strong>,</p>
                        <p>Your One-Time Password (OTP) for IPMS login is:</p>
                        <div class='otp-box'>
                            <div class='otp-code'>{$otp_code}</div>
                        </div>
                        <p><strong>⏱️ Validity:</strong> This OTP expires in {$this->otp_validity_minutes} minute(s)</p>
                        <p><strong>🔒 Security Note:</strong> Never share this OTP with anyone. The IPMS team will never ask you for this code.</p>
                        <p>If you did not request this OTP, please ignore this email or contact support immediately.</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                        <p>&copy; 2026 Infrastructure Project Management System. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            return $this->sendEmail($user_email, $subject, $message);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send OTP email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send email using SMTP (PHP mail or SMTP service)
     */
    private function sendEmail($to, $subject, $message): array {
        try {
            $from = MAIL_FROM_EMAIL;
            $from_name = MAIL_FROM_NAME;
            
            // Email headers
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$from_name} <{$from}>\r\n";
            $headers .= "Reply-To: {$from}\r\n";
            $headers .= "X-Mailer: IPMS System\r\n";
            
            // Send email
            if (mail($to, $subject, $message, $headers)) {
                return [
                    'success' => true,
                    'message' => 'OTP sent successfully to ' . $to
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send email. Please try again later.'
                ];
            }
        } catch (Exception $e) {
            error_log('Email sending error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Email service error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify OTP code
     */
    public function verifyOTP($user_id, $otp_code): array {
        try {
            $stmt = $this->db->prepare('
                SELECT id, verified, attempts, max_attempts, expires_at
                FROM otp_tokens
                WHERE user_id = ? AND otp_code = ?
                ORDER BY created_at DESC
                LIMIT 1
            ');
            
            $stmt->execute([$user_id, $otp_code]);
            $token = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$token) {
                return ['success' => false, 'message' => 'Invalid OTP code'];
            }
            
            // Check if already verified
            if ($token['verified']) {
                return ['success' => false, 'message' => 'OTP already used'];
            }
            
            // Check if expired
            if (strtotime($token['expires_at']) < time()) {
                return ['success' => false, 'message' => 'OTP has expired'];
            }
            
            // Check attempt limit
            if ($token['attempts'] >= $token['max_attempts']) {
                return ['success' => false, 'message' => 'Maximum OTP verification attempts exceeded'];
            }
            
            // Mark as verified
            $update_stmt = $this->db->prepare('
                UPDATE otp_tokens
                SET verified = 1, verified_at = NOW()
                WHERE id = ?
            ');
            $update_stmt->execute([$token['id']]);
            
            return [
                'success' => true,
                'message' => 'OTP verified successfully',
                'token_id' => $token['id']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'OTP verification failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Increment OTP attempt counter
     */
    public function incrementAttempt($user_id, $otp_code): void {
        try {
            $stmt = $this->db->prepare('
                UPDATE otp_tokens
                SET attempts = attempts + 1
                WHERE user_id = ? AND otp_code = ?
                ORDER BY created_at DESC
                LIMIT 1
            ');
            
            $stmt->execute([$user_id, $otp_code]);
        } catch (Exception $e) {
            error_log('OTP attempt increment failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Clean expired OTPs
     */
    public function cleanExpiredOTPs(): int {
        try {
            $stmt = $this->db->prepare('
                DELETE FROM otp_tokens
                WHERE expires_at < NOW()
            ');
            
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log('Failed to clean expired OTPs: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get OTP validity period in minutes
     */
    public function getValidityMinutes(): int {
        return $this->otp_validity_minutes;
    }
    
    /**
     * Set OTP validity period
     */
    public function setValidityMinutes(int $minutes): void {
        if ($minutes >= 1 && $minutes <= 5) {
            $this->otp_validity_minutes = $minutes;
        }
    }
}

// Cleanup expired OTPs on every instantiation
$cleaner = new OTPManager();
$cleaner->cleanExpiredOTPs();
