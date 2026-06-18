ALTER TABLE registrations
  ADD INDEX idx_reg_tournament_status (tournament_id, payment_status),
  ADD INDEX idx_reg_slot_capacity (schedule_group, tournament_id, payment_status);

ALTER TABLE registrations_non_golfer
  ADD INDEX idx_ng_tournament_status (tournament_id, payment_status),
  ADD INDEX idx_ng_window_capacity (arrival_window, tournament_id, payment_status);

ALTER TABLE tournaments
  ADD INDEX idx_tournaments_active (is_active);
