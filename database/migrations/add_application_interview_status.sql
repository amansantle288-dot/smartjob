ALTER TABLE tbl_applications
  MODIFY COLUMN status ENUM('submitted','reviewing','shortlisted','interview_scheduled','accepted','rejected','withdrawn') NOT NULL DEFAULT 'submitted',
  ADD COLUMN IF NOT EXISTS interview_at DATETIME NULL AFTER reviewed_at,
  ADD COLUMN IF NOT EXISTS interview_location VARCHAR(255) NULL AFTER interview_at;

ALTER TABLE tbl_application_status_history
  MODIFY COLUMN status ENUM('submitted','reviewing','shortlisted','interview_scheduled','accepted','rejected','withdrawn') NOT NULL;
