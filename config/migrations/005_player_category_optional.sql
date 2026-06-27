ALTER TABLE registrations
  MODIFY COLUMN player_category VARCHAR(20) NOT NULL DEFAULT 'N/A';

ALTER TABLE registrations_non_golfer
  MODIFY COLUMN player_category VARCHAR(20) NOT NULL DEFAULT 'N/A';
