CREATE TABLE IF NOT EXISTS tbl_application_document_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  credential_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_key CHAR(64) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_application_document_snapshot (application_id, credential_id),
  UNIQUE KEY uq_application_snapshot_storage_key (storage_key),
  KEY idx_application_snapshot_application (application_id, created_at),
  CONSTRAINT fk_application_snapshot_application FOREIGN KEY (application_id) REFERENCES tbl_applications(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_application_snapshot_credential FOREIGN KEY (credential_id) REFERENCES tbl_student_credentials(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
