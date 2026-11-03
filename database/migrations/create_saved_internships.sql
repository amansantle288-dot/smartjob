USE smartjob_db;

CREATE TABLE IF NOT EXISTS tbl_saved_internships (
  student_id BIGINT UNSIGNED NOT NULL,
  internship_id BIGINT UNSIGNED NOT NULL,
  saved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (student_id, internship_id),
  KEY idx_saved_internship (internship_id),
  CONSTRAINT fk_saved_internship_student FOREIGN KEY (student_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_saved_internship_opportunity FOREIGN KEY (internship_id) REFERENCES tbl_internship_opportunities(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
