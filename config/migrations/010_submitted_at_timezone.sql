-- submitted_at was stored via PHP date() in UTC while paid_at used MySQL NOW() in BD time
UPDATE registrations SET submitted_at = DATE_ADD(submitted_at, INTERVAL 6 HOUR);
UPDATE registrations_non_golfer SET submitted_at = DATE_ADD(submitted_at, INTERVAL 6 HOUR);
