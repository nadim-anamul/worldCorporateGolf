-- paid_at was stored in UTC via MySQL NOW() while submitted_at is BD time
UPDATE registrations
SET paid_at = DATE_ADD(paid_at, INTERVAL 6 HOUR)
WHERE paid_at IS NOT NULL
  AND submitted_at IS NOT NULL
  AND paid_at < submitted_at
  AND TIMESTAMPDIFF(HOUR, paid_at, submitted_at) BETWEEN 4 AND 8;

UPDATE registrations_non_golfer
SET paid_at = DATE_ADD(paid_at, INTERVAL 6 HOUR)
WHERE paid_at IS NOT NULL
  AND submitted_at IS NOT NULL
  AND paid_at < submitted_at
  AND TIMESTAMPDIFF(HOUR, paid_at, submitted_at) BETWEEN 4 AND 8;
