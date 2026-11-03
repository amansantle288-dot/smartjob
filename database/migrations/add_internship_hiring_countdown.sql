SET time_zone = '+08:00';

ALTER TABLE tbl_internship_opportunities
  ADD COLUMN published_at DATETIME NULL AFTER application_deadline,
  ADD COLUMN hiring_duration_days SMALLINT UNSIGNED NULL AFTER published_at;

UPDATE tbl_internship_opportunities
SET published_at = created_at
WHERE published_at IS NULL;

UPDATE tbl_internship_opportunities
SET hiring_duration_days = GREATEST(1, DATEDIFF(application_deadline, DATE(published_at)))
WHERE hiring_duration_days IS NULL;

ALTER TABLE tbl_internship_opportunities
  MODIFY COLUMN published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY COLUMN hiring_duration_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  ADD KEY idx_internship_published (status, published_at, id);
