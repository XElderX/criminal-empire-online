-- Criminal Empire Online v0.4.1 — Fallback Street Actions & Quick Crimes

CREATE TABLE IF NOT EXISTS quick_crime_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  category VARCHAR(80) NOT NULL,
  description TEXT NOT NULL,
  tier INT NOT NULL DEFAULT 1,
  min_level INT NOT NULL DEFAULT 1,
  energy_cost INT NOT NULL DEFAULT 4,
  max_heat INT NULL,
  cooldown_seconds INT NOT NULL DEFAULT 300,
  district_cooldown_seconds INT NOT NULL DEFAULT 0,
  base_success_rate INT NOT NULL DEFAULT 60,
  base_event_chance INT NOT NULL DEFAULT 20,
  base_disaster_chance INT NOT NULL DEFAULT 2,
  reward_min BIGINT UNSIGNED NOT NULL DEFAULT 0,
  reward_max BIGINT UNSIGNED NOT NULL DEFAULT 0,
  heat_min INT NOT NULL DEFAULT 0,
  heat_max INT NOT NULL DEFAULT 1,
  xp_min INT NOT NULL DEFAULT 2,
  xp_max INT NOT NULL DEFAULT 10,
  required_all_item_tags JSON NOT NULL,
  required_any_item_tags JSON NOT NULL,
  recommended_item_tags JSON NOT NULL,
  required_crew_count INT NOT NULL DEFAULT 0,
  recommended_crew_roles JSON NOT NULL,
  relevant_stats JSON NOT NULL,
  preparation_options JSON NOT NULL,
  event_pool JSON NOT NULL,
  loot_table JSON NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX quick_crime_templates_active_level (active, min_level, tier),
  INDEX quick_crime_templates_category (category)
);

CREATE TABLE IF NOT EXISTS item_tags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_type ENUM('item','weapon') NOT NULL,
  asset_code VARCHAR(120) NOT NULL,
  tag VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY unique_item_tag (asset_type, asset_code, tag),
  INDEX item_tags_lookup (asset_type, tag)
);

CREATE TABLE IF NOT EXISTS item_effects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_type ENUM('item','weapon') NOT NULL,
  asset_code VARCHAR(120) NOT NULL,
  effect_code VARCHAR(80) NOT NULL,
  effect_value INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY unique_item_effect (asset_type, asset_code, effect_code),
  INDEX item_effects_lookup (asset_type, effect_code)
);

CREATE TABLE IF NOT EXISTS quick_crime_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  template_id BIGINT UNSIGNED NOT NULL,
  idempotency_key VARCHAR(80) NOT NULL,
  status ENUM('preparing','active','awaiting_decision','resolved','abandoned','failed') NOT NULL DEFAULT 'active',
  district_code VARCHAR(80) NULL,
  target_key VARCHAR(120) NULL,
  started_at DATETIME NOT NULL,
  resolved_at DATETIME NULL,
  outcome VARCHAR(80) NULL,
  success_chance INT NOT NULL DEFAULT 0,
  event_chance INT NOT NULL DEFAULT 0,
  disaster_chance INT NOT NULL DEFAULT 0,
  reward_cash BIGINT UNSIGNED NOT NULL DEFAULT 0,
  reward_dirty_cash BIGINT UNSIGNED NOT NULL DEFAULT 0,
  heat_gained INT NOT NULL DEFAULT 0,
  experience_gained INT NOT NULL DEFAULT 0,
  result JSON NULL,
  resolved TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_quick_crime_idempotency (user_id, idempotency_key),
  INDEX quick_crime_runs_user_status (user_id, status, started_at),
  INDEX quick_crime_runs_template (template_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (template_id) REFERENCES quick_crime_templates(id)
);

CREATE TABLE IF NOT EXISTS quick_crime_preparations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  template_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(80) NOT NULL,
  name VARCHAR(140) NOT NULL,
  description TEXT NOT NULL,
  cash_cost BIGINT UNSIGNED NOT NULL DEFAULT 0,
  energy_cost INT NOT NULL DEFAULT 0,
  effects JSON NOT NULL,
  expires_at DATETIME NULL,
  applied_at DATETIME NOT NULL,
  UNIQUE KEY unique_quick_crime_preparation (user_id, template_id, code),
  INDEX quick_crime_preparations_user (user_id, template_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (template_id) REFERENCES quick_crime_templates(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS quick_crime_run_crew (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  gang_member_id BIGINT UNSIGNED NOT NULL,
  role_code VARCHAR(80) NOT NULL DEFAULT 'helper',
  created_at DATETIME NOT NULL,
  UNIQUE KEY unique_quick_crime_run_crew (run_id, gang_member_id),
  INDEX quick_crime_run_crew_user (user_id),
  FOREIGN KEY (run_id) REFERENCES quick_crime_runs(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (gang_member_id) REFERENCES player_gang_members(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quick_crime_run_equipment (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  asset_type ENUM('item','weapon') NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  durability_before INT UNSIGNED NULL,
  durability_after INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY unique_quick_crime_run_equipment (run_id, asset_type, asset_id),
  INDEX quick_crime_run_equipment_user (user_id),
  FOREIGN KEY (run_id) REFERENCES quick_crime_runs(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quick_crime_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  event_code VARCHAR(100) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  status ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
  choices JSON NOT NULL,
  selected_choice_code VARCHAR(100) NULL,
  created_at DATETIME NOT NULL,
  resolved_at DATETIME NULL,
  INDEX quick_crime_events_run_status (run_id, status),
  FOREIGN KEY (run_id) REFERENCES quick_crime_runs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quick_crime_cooldowns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  template_id BIGINT UNSIGNED NOT NULL,
  cooldown_type ENUM('action','district','category','target','player') NOT NULL DEFAULT 'action',
  district_code VARCHAR(80) NULL,
  target_key VARCHAR(120) NULL,
  category VARCHAR(80) NULL,
  available_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_quick_crime_cooldown (
    user_id,
    template_id,
    cooldown_type,
    district_code,
    target_key,
    category
  ),
  INDEX quick_crime_cooldowns_user_available (user_id, available_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (template_id) REFERENCES quick_crime_templates(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS player_action_cooldowns (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  action_type VARCHAR(80) NOT NULL,
  action_code VARCHAR(120) NOT NULL,
  available_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_player_action_cooldown (user_id, action_type, action_code),
  INDEX player_action_cooldowns_available (user_id, available_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS player_recent_actions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  action_type VARCHAR(80) NOT NULL,
  action_code VARCHAR(120) NOT NULL,
  category VARCHAR(80) NULL,
  district_code VARCHAR(80) NULL,
  target_key VARCHAR(120) NULL,
  outcome VARCHAR(80) NULL,
  heat_gained INT NOT NULL DEFAULT 0,
  reward_cash BIGINT UNSIGNED NOT NULL DEFAULT 0,
  experience_gained INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX player_recent_actions_user_time (user_id, created_at),
  INDEX player_recent_actions_repeat (user_id, action_type, action_code, district_code),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS experience_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  gang_member_id BIGINT UNSIGNED NULL,
  source_type VARCHAR(80) NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  amount INT NOT NULL,
  level_before INT NOT NULL,
  level_after INT NOT NULL,
  experience_before BIGINT NOT NULL,
  experience_after BIGINT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX experience_logs_user_time (user_id, created_at),
  INDEX experience_logs_member_time (gang_member_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (gang_member_id) REFERENCES player_gang_members(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS skill_progression_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  gang_member_id BIGINT UNSIGNED NULL,
  source_type VARCHAR(80) NOT NULL,
  source_id BIGINT UNSIGNED NULL,
  skill_code VARCHAR(80) NOT NULL,
  amount INT NOT NULL DEFAULT 1,
  value_before INT NOT NULL,
  value_after INT NOT NULL,
  reason VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX skill_progression_logs_user_time (user_id, created_at),
  INDEX skill_progression_logs_member_time (gang_member_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (gang_member_id) REFERENCES player_gang_members(id) ON DELETE CASCADE
);
