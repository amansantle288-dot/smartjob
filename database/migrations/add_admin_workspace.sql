SET time_zone = '+08:00';

ALTER TABLE tbl_audit_logs
  ADD COLUMN actor_role VARCHAR(30) NULL AFTER user_id,
  ADD COLUMN result ENUM('success','failure') NOT NULL DEFAULT 'success' AFTER entity_id,
  ADD COLUMN description VARCHAR(500) NULL AFTER result;

UPDATE tbl_audit_logs a
LEFT JOIN tbl_users u ON u.id=a.user_id
SET a.actor_role=u.role,
    a.description=CONCAT('Recorded event: ', a.action)
WHERE a.actor_role IS NULL OR a.description IS NULL;

ALTER TABLE tbl_audit_logs
  MODIFY COLUMN description VARCHAR(500) NOT NULL DEFAULT 'Recorded event';

CREATE TABLE IF NOT EXISTS tbl_admin_email_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id BIGINT UNSIGNED NULL,
  company_name VARCHAR(180) NOT NULL,
  recipient_email VARCHAR(190) NOT NULL,
  template_key VARCHAR(60) NOT NULL,
  subject VARCHAR(180) NOT NULL,
  message_body TEXT NOT NULL,
  delivery_status ENUM('sent','failed') NOT NULL,
  provider_name VARCHAR(40) NOT NULL,
  http_status_code SMALLINT UNSIGNED NULL,
  error_code VARCHAR(80) NULL,
  sent_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_admin_email_company (company_id, created_at),
  KEY idx_admin_email_status (delivery_status, created_at),
  CONSTRAINT fk_admin_email_company FOREIGN KEY (company_id) REFERENCES tbl_companies(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_admin_email_sender FOREIGN KEY (sent_by) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
