USE smartjob_db;

CREATE TABLE IF NOT EXISTS tbl_verification_delivery_logs (
  verification_delivery_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_verification_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  delivery_channel ENUM('email','sms') NOT NULL,
  provider_name VARCHAR(40) NOT NULL,
  recipient_masked VARCHAR(190) NOT NULL,
  provider_message_id VARCHAR(190) NULL,
  delivery_status ENUM('submitted','failed') NOT NULL,
  error_code VARCHAR(80) NULL,
  error_message VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (verification_delivery_id),
  KEY idx_verification_delivery_user (user_id, created_at),
  KEY idx_verification_delivery_status (delivery_status, created_at),
  CONSTRAINT fk_verification_delivery_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_verification_delivery_verification FOREIGN KEY (account_verification_id) REFERENCES tbl_account_verifications(account_verification_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
