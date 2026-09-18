-- Scopes otp_tokens rows to the flow that created them so citizen email
-- verification, staff 2FA login, and password reset — three concurrent
-- consumers of one user_id-keyed "latest row wins" table — can no longer
-- silently invalidate each other's in-flight OTP.
-- Note: otp_tokens is also self-healed by OTPManager::ensureTable() on every
-- request (this repo has no migration runner), so that method carries the
-- same idempotent ALTER as a safety net independent of this file being run.

USE lgu_infrastructure;

SET @purpose_exists = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'otp_tokens'
    AND column_name = 'purpose'
);
SET @purpose_sql = IF(
  @purpose_exists = 0,
  'ALTER TABLE otp_tokens ADD COLUMN purpose VARCHAR(30) NOT NULL DEFAULT ''general'' AFTER user_id',
  'SELECT 1'
);
PREPARE purpose_stmt FROM @purpose_sql;
EXECUTE purpose_stmt;
DEALLOCATE PREPARE purpose_stmt;

SET @purpose_index_exists = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'otp_tokens'
    AND index_name = 'idx_otp_user_purpose'
);
SET @purpose_index_sql = IF(
  @purpose_index_exists = 0,
  'ALTER TABLE otp_tokens ADD INDEX idx_otp_user_purpose (user_id, purpose)',
  'SELECT 1'
);
PREPARE purpose_index_stmt FROM @purpose_index_sql;
EXECUTE purpose_index_stmt;
DEALLOCATE PREPARE purpose_index_stmt;
