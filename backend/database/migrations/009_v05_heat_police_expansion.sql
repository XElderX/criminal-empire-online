-- Criminal Empire Online v0.5 — Heat & Police Pressure Expansion

ALTER TABLE users
  ADD COLUMN boss_display_name VARCHAR(160) NULL AFTER username,
  ADD COLUMN boss_personal_heat INT NOT NULL DEFAULT 0 AFTER heat,
  ADD COLUMN gang_heat INT NOT NULL DEFAULT 0 AFTER boss_personal_heat,
  ADD COLUMN boss_health INT NOT NULL DEFAULT 100 AFTER leadership,
  ADD COLUMN boss_max_health INT NOT NULL DEFAULT 100 AFTER boss_health,
  ADD COLUMN boss_status VARCHAR(40) NOT NULL DEFAULT 'active' AFTER boss_max_health,
  ADD COLUMN boss_injury_status VARCHAR(40) NULL AFTER boss_status,
  ADD COLUMN boss_arrested_until DATETIME NULL AFTER boss_injury_status,
  ADD COLUMN boss_alive TINYINT(1) NOT NULL DEFAULT 1 AFTER boss_arrested_until,
  ADD COLUMN boss_dead_at DATETIME NULL AFTER boss_alive,
  ADD COLUMN boss_successor_member_id BIGINT UNSIGNED NULL AFTER boss_dead_at,
  ADD COLUMN boss_rank VARCHAR(80) NOT NULL DEFAULT 'Nobody' AFTER boss_successor_member_id,
  ADD COLUMN last_heat_generating_action_at DATETIME NULL AFTER boss_rank,
  ADD COLUMN idle_days_count INT NOT NULL DEFAULT 0 AFTER last_heat_generating_action_at,
  ADD COLUMN last_idle_decay_processed_date DATE NULL AFTER idle_days_count,
  ADD COLUMN weekly_quiet_bonus_last_week VARCHAR(20) NULL AFTER last_idle_decay_processed_date,
  ADD INDEX users_heat_status_index (boss_personal_heat, gang_heat, boss_status);

ALTER TABLE player_gang_members
  ADD COLUMN personal_heat INT NOT NULL DEFAULT 0 AFTER loyalty,
  ADD COLUMN under_investigation TINYINT(1) NOT NULL DEFAULT 0 AFTER personal_heat,
  ADD COLUMN sent_away_until DATETIME NULL AFTER under_investigation,
  ADD COLUMN revenge_risk INT NOT NULL DEFAULT 0 AFTER sent_away_until,
  ADD COLUMN revenge_status VARCHAR(40) NOT NULL DEFAULT 'none' AFTER revenge_risk,
  ADD COLUMN dismissed_heat_relief INT NOT NULL DEFAULT 0 AFTER revenge_status,
  ADD INDEX player_gang_members_heat_status_index (user_id, personal_heat, status);

ALTER TABLE npcs
  ADD COLUMN personal_heat INT NOT NULL DEFAULT 0 AFTER reputation,
  ADD COLUMN under_investigation TINYINT(1) NOT NULL DEFAULT 0 AFTER personal_heat,
  ADD COLUMN police_attention_level VARCHAR(40) NOT NULL DEFAULT 'clean' AFTER under_investigation,
  ADD INDEX npcs_heat_attention_index (personal_heat, under_investigation, police_attention_level);

ALTER TABLE territories
  ADD COLUMN district_heat INT NOT NULL DEFAULT 0 AFTER police_presence,
  ADD INDEX territories_district_heat_index (district_heat, police_presence);

CREATE TABLE IF NOT EXISTS boss_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  gang_member_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  INDEX boss_history_user_time (user_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (gang_member_id) REFERENCES player_gang_members(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS heat_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  target_type ENUM('boss','crew','npc','gang','district','business','warehouse') NOT NULL,
  target_id BIGINT UNSIGNED NULL,
  amount INT NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'personal_heat',
  source_type VARCHAR(80) NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  description TEXT NOT NULL,
  can_spillover TINYINT(1) NOT NULL DEFAULT 1,
  evidence_linked TINYINT(1) NOT NULL DEFAULT 0,
  game_date VARCHAR(40) NULL,
  created_at DATETIME NOT NULL,
  INDEX heat_logs_user_time (user_id, created_at),
  INDEX heat_logs_target (target_type, target_id, created_at),
  INDEX heat_logs_source (source_type, source_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS police_investigations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  target_type ENUM('boss','crew','npc','gang','district','business','warehouse') NOT NULL,
  target_id BIGINT UNSIGNED NULL,
  status ENUM('open','monitoring','active','escalated','raid_pending','arrest_pending','resolved','cold','closed') NOT NULL DEFAULT 'open',
  suspicion INT NOT NULL DEFAULT 0,
  evidence_strength INT NOT NULL DEFAULT 0,
  investigation_level INT NOT NULL DEFAULT 1,
  known_associates JSON NULL,
  lead_officer VARCHAR(120) NULL,
  notes TEXT NULL,
  opened_at DATETIME NOT NULL,
  last_advanced_at DATETIME NULL,
  resolved_at DATETIME NULL,
  outcome VARCHAR(120) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX police_investigations_user_status (user_id, status, suspicion),
  INDEX police_investigations_target_status (target_type, target_id, status),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS police_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  investigation_id BIGINT UNSIGNED NULL,
  event_code VARCHAR(100) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  severity VARCHAR(40) NOT NULL DEFAULT 'low',
  target_type VARCHAR(40) NULL,
  target_id BIGINT UNSIGNED NULL,
  result JSON NULL,
  acknowledged_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX police_events_user_time (user_id, created_at),
  INDEX police_events_investigation (investigation_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (investigation_id) REFERENCES police_investigations(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS heat_reduction_actions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(140) NOT NULL,
  description TEXT NOT NULL,
  target_type VARCHAR(40) NOT NULL DEFAULT 'gang',
  heat_reduction_min INT NOT NULL DEFAULT 0,
  heat_reduction_max INT NOT NULL DEFAULT 0,
  investigation_reduction_min INT NOT NULL DEFAULT 0,
  investigation_reduction_max INT NOT NULL DEFAULT 0,
  cash_cost BIGINT UNSIGNED NOT NULL DEFAULT 0,
  energy_cost INT NOT NULL DEFAULT 0,
  cooldown_seconds INT NOT NULL DEFAULT 3600,
  risk_percent INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS crew_revenge_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  gang_member_id BIGINT UNSIGNED NOT NULL,
  npc_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  severity VARCHAR(40) NOT NULL DEFAULT 'medium',
  status ENUM('pending','resolved','cancelled') NOT NULL DEFAULT 'pending',
  heat_at_dismissal INT NOT NULL DEFAULT 0,
  revenge_risk INT NOT NULL DEFAULT 0,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  scheduled_at DATETIME NOT NULL,
  resolved_at DATETIME NULL,
  result JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX crew_revenge_user_status (user_id, status, scheduled_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (gang_member_id) REFERENCES player_gang_members(id) ON DELETE CASCADE,
  FOREIGN KEY (npc_id) REFERENCES npcs(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS heat_processing_state (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  last_processed_date DATE NULL,
  idle_days_count INT NOT NULL DEFAULT 0,
  last_weekly_bonus_key VARCHAR(20) NULL,
  last_heat_generating_action_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS update_notices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(180) NOT NULL,
  body TEXT NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS user_update_notice_acknowledgements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  notice_id BIGINT UNSIGNED NOT NULL,
  acknowledged_at DATETIME NOT NULL,
  UNIQUE KEY unique_user_update_notice (user_id, notice_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (notice_id) REFERENCES update_notices(id) ON DELETE CASCADE
);
