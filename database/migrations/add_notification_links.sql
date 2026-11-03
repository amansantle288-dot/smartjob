ALTER TABLE tbl_notifications
  ADD COLUMN IF NOT EXISTS related_type VARCHAR(40) NULL AFTER message,
  ADD COLUMN IF NOT EXISTS related_id BIGINT UNSIGNED NULL AFTER related_type;

CREATE INDEX IF NOT EXISTS idx_notifications_related ON tbl_notifications (related_type, related_id);
