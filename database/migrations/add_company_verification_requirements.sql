ALTER TABLE tbl_companies
  ADD COLUMN IF NOT EXISTS company_type VARCHAR(40) NOT NULL DEFAULT 'other' AFTER name;

CREATE TABLE IF NOT EXISTS tbl_company_verification_requirements (
  company_type VARCHAR(40) NOT NULL,
  document_type VARCHAR(80) NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (company_type, document_type),
  KEY idx_company_requirement_type (company_type, is_required),
  CONSTRAINT fk_company_requirement_admin FOREIGN KEY (updated_by) REFERENCES tbl_users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
