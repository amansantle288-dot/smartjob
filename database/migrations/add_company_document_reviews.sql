USE smartjob_db;

ALTER TABLE tbl_company_documents
  MODIFY review_status ENUM('pending','accepted','rejected','under_review','approved','revision_requested') NOT NULL DEFAULT 'pending';

UPDATE tbl_company_documents SET review_status='approved' WHERE review_status='accepted';
UPDATE tbl_company_documents SET review_status='revision_requested' WHERE review_status='rejected';

ALTER TABLE tbl_company_documents
  MODIFY review_status ENUM('pending','under_review','approved','revision_requested') NOT NULL DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS reviewer_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS reviewer_notes VARCHAR(1000) NULL,
  ADD COLUMN IF NOT EXISTS reviewed_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS previous_document_id BIGINT UNSIGNED NULL;

SET @company_document_reviewer_fk = IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tbl_company_documents' AND CONSTRAINT_NAME='fk_company_document_reviewer')=0,
  'ALTER TABLE tbl_company_documents ADD CONSTRAINT fk_company_document_reviewer FOREIGN KEY (reviewer_id) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE add_company_document_reviewer_fk FROM @company_document_reviewer_fk;
EXECUTE add_company_document_reviewer_fk;
DEALLOCATE PREPARE add_company_document_reviewer_fk;

SET @company_document_previous_fk = IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tbl_company_documents' AND CONSTRAINT_NAME='fk_company_document_previous')=0,
  'ALTER TABLE tbl_company_documents ADD CONSTRAINT fk_company_document_previous FOREIGN KEY (previous_document_id) REFERENCES tbl_company_documents(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE add_company_document_previous_fk FROM @company_document_previous_fk;
EXECUTE add_company_document_previous_fk;
DEALLOCATE PREPARE add_company_document_previous_fk;

CREATE TABLE IF NOT EXISTS tbl_company_document_review_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  document_id BIGINT UNSIGNED NOT NULL,
  reviewer_id BIGINT UNSIGNED NULL,
  action ENUM('uploaded','under_review','approved','revision_requested') NOT NULL,
  notes VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_company_document_history (document_id, created_at),
  CONSTRAINT fk_company_document_history_document FOREIGN KEY (document_id) REFERENCES tbl_company_documents(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_company_document_history_reviewer FOREIGN KEY (reviewer_id) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
