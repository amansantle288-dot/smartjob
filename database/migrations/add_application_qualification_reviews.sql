CREATE TABLE IF NOT EXISTS tbl_application_qualification_reviews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  reviewer_id BIGINT UNSIGNED NOT NULL,
  assessment ENUM('qualified','needs_improvement','not_qualified') NOT NULL,
  notes VARCHAR(1000) NOT NULL,
  missing_qualifications TEXT NULL,
  reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_qualification_review_application (application_id, id),
  CONSTRAINT fk_qualification_review_application FOREIGN KEY (application_id) REFERENCES tbl_applications(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_qualification_review_reviewer FOREIGN KEY (reviewer_id) REFERENCES tbl_users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
