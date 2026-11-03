USE smartjob_db;

ALTER TABLE tbl_student_profiles
  ADD COLUMN IF NOT EXISTS profile_photo_key CHAR(64) NULL AFTER bio;

ALTER TABLE tbl_companies
  ADD COLUMN IF NOT EXISTS logo_key CHAR(64) NULL AFTER website;

ALTER TABLE tbl_internship_opportunities
  ADD COLUMN IF NOT EXISTS category VARCHAR(100) NULL AFTER title,
  ADD COLUMN IF NOT EXISTS requirements TEXT NULL AFTER description;

CREATE TABLE IF NOT EXISTS tbl_student_educational_attainment (
  user_id BIGINT UNSIGNED NOT NULL,
  elementary_school VARCHAR(190) NULL,
  elementary_location VARCHAR(255) NULL,
  elementary_year_started YEAR NULL,
  elementary_year_graduated YEAR NULL,
  junior_high_school VARCHAR(190) NULL,
  junior_high_location VARCHAR(255) NULL,
  junior_high_year_started YEAR NULL,
  junior_high_year_completed YEAR NULL,
  senior_high_school VARCHAR(190) NULL,
  senior_high_location VARCHAR(255) NULL,
  senior_high_strand VARCHAR(120) NULL,
  senior_high_year_started YEAR NULL,
  senior_high_year_graduated YEAR NULL,
  tertiary_school VARCHAR(190) NULL,
  tertiary_course VARCHAR(160) NULL,
  tertiary_year_level VARCHAR(40) NULL,
  tertiary_location VARCHAR(255) NULL,
  tertiary_year_started YEAR NULL,
  tertiary_year_graduated YEAR NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_educational_attainment_user FOREIGN KEY (user_id) REFERENCES tbl_users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
