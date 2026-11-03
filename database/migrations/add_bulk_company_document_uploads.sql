USE smartjob_db;

ALTER TABLE tbl_company_verification_requirements
  ADD COLUMN IF NOT EXISTS applicability ENUM('required','optional','not_applicable') NOT NULL DEFAULT 'optional' AFTER is_required;

UPDATE tbl_company_verification_requirements
SET applicability='required'
WHERE is_required=1 AND applicability='optional';

CREATE TABLE IF NOT EXISTS tbl_company_document_batches (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  batch_key CHAR(32) NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  document_count INT UNSIGNED NOT NULL,
  total_bytes BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_company_document_batch_key (batch_key),
  KEY idx_company_document_batches (company_id, created_at),
  CONSTRAINT fk_company_document_batch_company FOREIGN KEY (company_id) REFERENCES tbl_companies(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_company_document_batch_user FOREIGN KEY (uploaded_by) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tbl_company_documents
  ADD COLUMN IF NOT EXISTS batch_id BIGINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS version_number INT UNSIGNED NOT NULL DEFAULT 1,
  ADD KEY IF NOT EXISTS idx_company_documents_batch (batch_id);

SET @company_document_batch_fk = IF(
  (SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='tbl_company_documents' AND CONSTRAINT_NAME='fk_company_document_batch')=0,
  'ALTER TABLE tbl_company_documents ADD CONSTRAINT fk_company_document_batch FOREIGN KEY (batch_id) REFERENCES tbl_company_document_batches(id) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE add_company_document_batch_fk FROM @company_document_batch_fk;
EXECUTE add_company_document_batch_fk;
DEALLOCATE PREPARE add_company_document_batch_fk;

INSERT IGNORE INTO tbl_company_verification_requirements (company_type, document_type, is_required, applicability)
SELECT company_types.company_type, document_types.document_type, 0, 'optional'
FROM (
  SELECT 'business' AS company_type UNION ALL SELECT 'nonprofit' UNION ALL
  SELECT 'educational' UNION ALL SELECT 'government' UNION ALL SELECT 'other'
) AS company_types
CROSS JOIN (
  SELECT 'bir_registration' AS document_type UNION ALL
  SELECT 'other_business_registration' UNION ALL
  SELECT 'representative_information' UNION ALL
  SELECT 'internship_program' UNION ALL
  SELECT 'supervisor_information' UNION ALL
  SELECT 'additional_certificate' UNION ALL
  SELECT 'accreditation' UNION ALL
  SELECT 'supporting_letter'
) AS document_types;

INSERT INTO tbl_company_verification_requirements (company_type, document_type, is_required, applicability)
SELECT company_types.company_type, document_types.document_type, 1, 'required'
FROM (
  SELECT 'business' AS company_type UNION ALL SELECT 'nonprofit' UNION ALL
  SELECT 'educational' UNION ALL SELECT 'government' UNION ALL SELECT 'other'
) AS company_types
CROSS JOIN (
  SELECT 'business_registration' AS document_type UNION ALL
  SELECT 'business_permit' UNION ALL
  SELECT 'bir_registration' UNION ALL
  SELECT 'other_business_registration'
) AS document_types
WHERE 1=1
ON DUPLICATE KEY UPDATE is_required=1, applicability='required';
