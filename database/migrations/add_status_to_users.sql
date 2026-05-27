-- Migration: Add status column to users table if it doesn't exist
-- This fixes the "Unknown column 'status' in 'field list'" error

ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER role;

-- Also add the index on status if it doesn't exist
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_users_status (status);
