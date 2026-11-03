CREATE TABLE IF NOT EXISTS tbl_saved_companies (
  student_id BIGINT UNSIGNED NOT NULL,
  company_id BIGINT UNSIGNED NOT NULL,
  saved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id, company_id),
  KEY idx_saved_company (company_id),
  CONSTRAINT fk_saved_company_student FOREIGN KEY (student_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_saved_company_profile FOREIGN KEY (company_id) REFERENCES tbl_companies(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
