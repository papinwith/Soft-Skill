-- Adds a registration deadline for activities (issu.md #14): students can no
-- longer apply after this timestamp, and the UI shows a countdown to it.
ALTER TABLE activities ADD COLUMN registration_close_at DATETIME NULL AFTER end_date;
