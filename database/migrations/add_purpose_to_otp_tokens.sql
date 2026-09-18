-- Scopes otp_tokens rows to the flow that created them so citizen email
-- verification, staff 2FA login, and password reset — three concurrent
-- consumers of one user_id-keyed "latest row wins" table — can no longer
-- silently invalidate each other's in-flight OTP.
-- Note: otp_tokens is also self-healed by OTPManager::ensureTable() on every
-- request (this repo has no migration runner), so that method carries the
-- same idempotent ALTER as a safety net independent of this file being run.

USE lgu_infrastructure;

ALTER TABLE otp_tokens
  ADD COLUMN IF NOT EXISTS purpose VARCHAR(30) NOT NULL DEFAULT 'general' AFTER user_id;

ALTER TABLE otp_tokens
  ADD INDEX IF NOT EXISTS idx_otp_user_purpose (user_id, purpose);
