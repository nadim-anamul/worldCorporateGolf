ALTER TABLE tournaments
  ADD COLUMN logo_path VARCHAR(255) NULL AFTER format;

ALTER TABLE tournaments
  ADD COLUMN deadline_at DATETIME NULL AFTER deadline;

UPDATE tournaments
SET deadline_at = STR_TO_DATE(deadline, '%W, %d %M %Y')
WHERE deadline IS NOT NULL AND deadline_at IS NULL;

UPDATE tournaments
SET deadline_at = STR_TO_DATE(deadline, '%M %d, %Y')
WHERE deadline IS NOT NULL AND deadline_at IS NULL;

UPDATE tournaments
SET deadline_at = COALESCE(deadline_at, '2026-07-30 23:59:59')
WHERE deadline_at IS NULL;

ALTER TABLE tournaments
  DROP COLUMN deadline;

ALTER TABLE tournaments
  CHANGE COLUMN deadline_at deadline DATETIME NOT NULL;
