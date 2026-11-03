USE smartjob_db;

ALTER TABLE tbl_users
  MODIFY COLUMN status ENUM('pending_verification','active','inactive','suspended') NOT NULL DEFAULT 'active',
  ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) NULL AFTER email,
  ADD COLUMN IF NOT EXISTS phone_verified_at DATETIME NULL AFTER email_verified_at,
  ADD COLUMN IF NOT EXISTS verification_required BOOLEAN NOT NULL DEFAULT FALSE AFTER phone_verified_at,
  ADD COLUMN IF NOT EXISTS phone_verification_required BOOLEAN NOT NULL DEFAULT FALSE AFTER verification_required,
  ADD COLUMN IF NOT EXISTS last_verification_at DATETIME NULL AFTER phone_verification_required;

CREATE TABLE IF NOT EXISTS tbl_account_verifications (
  account_verification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  verification_channel ENUM('email','sms') NOT NULL,
  verification_purpose VARCHAR(50) NOT NULL DEFAULT 'account',
  otp_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempt_count INT NOT NULL DEFAULT 0,
  max_attempts INT NOT NULL DEFAULT 5,
  verified_at DATETIME NULL,
  invalidated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (account_verification_id),
  KEY idx_account_verification_active (user_id, verification_channel, verification_purpose, created_at),
  KEY idx_account_verification_expiry (expires_at),
  CONSTRAINT fk_account_verification_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
