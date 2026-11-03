ALTER TABLE tbl_companies
  ADD COLUMN mission_statement TEXT NULL AFTER description,
  ADD COLUMN vision_statement TEXT NULL AFTER mission_statement;

ALTER TABLE tbl_student_skills
  ADD COLUMN description TEXT NULL AFTER proficiency;

ALTER TABLE tbl_applications
  ADD COLUMN cover_letter TEXT NULL AFTER student_id;

CREATE TABLE IF NOT EXISTS tbl_ai_writing_usage (
  user_id BIGINT UNSIGNED NOT NULL,
  usage_day DATE NOT NULL,
  request_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, usage_day),
  CONSTRAINT fk_ai_writing_usage_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
