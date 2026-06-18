-- Map legacy numeric tee_time IDs stored in arrival_window to arrival window string IDs
UPDATE registrations_non_golfer SET arrival_window = 'window1' WHERE arrival_window = '1';
UPDATE registrations_non_golfer SET arrival_window = 'window2' WHERE arrival_window = '2';
