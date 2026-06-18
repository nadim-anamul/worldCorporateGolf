ALTER TABLE registrations ADD COLUMN sms_sent_at DATETIME DEFAULT NULL AFTER paid_at;
ALTER TABLE registrations_non_golfer ADD COLUMN sms_sent_at DATETIME DEFAULT NULL AFTER paid_at;
