ALTER TABLE npcs
  ADD COLUMN organization VARCHAR(120) NULL AFTER role,
  ADD COLUMN affiliation VARCHAR(80) NULL AFTER organization,
  ADD COLUMN current_activity VARCHAR(160) NULL AFTER affiliation,
  ADD COLUMN source_event VARCHAR(120) NULL AFTER current_activity,
  ADD COLUMN met_player_at DATETIME NULL AFTER source_event,
  ADD COLUMN last_seen_at DATETIME NULL AFTER met_player_at,
  ADD COLUMN death_category VARCHAR(80) NULL AFTER alive,
  ADD COLUMN death_game_date VARCHAR(40) NULL AFTER death_category,
  ADD COLUMN death_notes TEXT NULL AFTER death_game_date,
  ADD COLUMN is_contact TINYINT(1) NOT NULL DEFAULT 0 AFTER death_notes,
  ADD COLUMN is_recruitable TINYINT(1) NOT NULL DEFAULT 0 AFTER is_contact,
  ADD COLUMN is_witness TINYINT(1) NOT NULL DEFAULT 0 AFTER is_recruitable,
  ADD COLUMN is_informant TINYINT(1) NOT NULL DEFAULT 0 AFTER is_witness,
  ADD COLUMN is_police TINYINT(1) NOT NULL DEFAULT 0 AFTER is_informant,
  ADD COLUMN is_rival TINYINT(1) NOT NULL DEFAULT 0 AFTER is_police,
  ADD COLUMN strength INT NOT NULL DEFAULT 40 AFTER is_rival,
  ADD COLUMN shooting INT NOT NULL DEFAULT 30 AFTER strength,
  ADD COLUMN driving INT NOT NULL DEFAULT 35 AFTER shooting,
  ADD COLUMN intelligence INT NOT NULL DEFAULT 45 AFTER driving,
  ADD COLUMN stealth INT NOT NULL DEFAULT 40 AFTER intelligence,
  ADD COLUMN intimidation INT NOT NULL DEFAULT 35 AFTER stealth,
  ADD COLUMN discipline INT NOT NULL DEFAULT 45 AFTER intimidation,
  ADD COLUMN street_knowledge INT NOT NULL DEFAULT 45 AFTER discipline,
  ADD COLUMN endurance INT NOT NULL DEFAULT 45 AFTER street_knowledge,
  ADD COLUMN reliability INT NOT NULL DEFAULT 50 AFTER endurance,
  ADD COLUMN courage INT NOT NULL DEFAULT 50 AFTER reliability,
  ADD COLUMN greed INT NOT NULL DEFAULT 40 AFTER courage,
  ADD COLUMN notes TEXT NULL AFTER greed,
  ADD INDEX npcs_v04_status_index (alive, status),
  ADD INDEX npcs_v04_role_index (role, affiliation),
  ADD INDEX npcs_v04_flags_index (is_contact, is_witness, is_rival, is_police);

CREATE TABLE IF NOT EXISTS crime_discovery_locations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  energy_cost INT NOT NULL DEFAULT 4,
  cash_cost BIGINT UNSIGNED NOT NULL DEFAULT 0,
  min_level INT NOT NULL DEFAULT 1,
  risk_level VARCHAR(30) NOT NULL DEFAULT 'low',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX crime_discovery_locations_active_index (active, min_level)
);

CREATE TABLE IF NOT EXISTS crime_v04_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(100) NOT NULL UNIQUE,
  category VARCHAR(80) NOT NULL,
  title VARCHAR(160) NOT NULL,
  briefing TEXT NOT NULL,
  tier INT NOT NULL DEFAULT 1,
  energy_cost INT NOT NULL DEFAULT 6,
  base_reward_min BIGINT UNSIGNED NOT NULL,
  base_reward_max BIGINT UNSIGNED NOT NULL,
  base_heat_min INT NOT NULL DEFAULT 1,
  base_heat_max INT NOT NULL DEFAULT 5,
  base_success_rate INT NOT NULL DEFAULT 55,
  base_disaster_chance INT NOT NULL DEFAULT 5,
  min_crew INT NOT NULL DEFAULT 0,
  max_crew INT NOT NULL DEFAULT 3,
  recommended_roles JSON NOT NULL,
  required_items JSON NOT NULL,
  relevant_stats JSON NOT NULL,
  possible_events JSON NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX crime_v04_templates_active_index (active, tier),
  INDEX crime_v04_templates_category_index (category)
);

CREATE TABLE IF NOT EXISTS crime_opportunities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  template_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  territory_id BIGINT UNSIGNED NULL,
  source_npc_id BIGINT UNSIGNED NULL,
  target_npc_id BIGINT UNSIGNED NULL,
  target_business_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  target_name VARCHAR(180) NULL,
  information_level ENUM('rumor','lead','confirmed','trap') NOT NULL DEFAULT 'rumor',
  status ENUM('known','investigating','prepared','active','completed','abandoned','expired') NOT NULL DEFAULT 'known',
  source_type VARCHAR(80) NOT NULL DEFAULT 'rumor',
  source_description TEXT NULL,
  quality ENUM('weak','normal','strong','suspicious','trap') NOT NULL DEFAULT 'normal',
  reliability INT NOT NULL DEFAULT 50,
  estimated_reward_min BIGINT UNSIGNED NOT NULL DEFAULT 0,
  estimated_reward_max BIGINT UNSIGNED NOT NULL DEFAULT 0,
  estimated_heat_min INT NOT NULL DEFAULT 0,
  estimated_heat_max INT NOT NULL DEFAULT 0,
  expires_at DATETIME NULL,
  discovered_at DATETIME NOT NULL,
  investigated_at DATETIME NULL,
  prepared_at DATETIME NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX crime_opportunities_user_status_index (user_id, status, expires_at),
  INDEX crime_opportunities_template_index (template_id),
  INDEX crime_opportunities_source_npc_index (source_npc_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (template_id) REFERENCES crime_v04_templates(id),
  FOREIGN KEY (location_id) REFERENCES crime_discovery_locations(id),
  FOREIGN KEY (territory_id) REFERENCES territories(id) ON DELETE SET NULL,
  FOREIGN KEY (source_npc_id) REFERENCES npcs(id) ON DELETE SET NULL,
  FOREIGN KEY (target_npc_id) REFERENCES npcs(id) ON DELETE SET NULL,
  FOREIGN KEY (target_business_id) REFERENCES businesses(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS crime_preparations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(80) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  cash_cost BIGINT UNSIGNED NOT NULL DEFAULT 0,
  energy_cost INT NOT NULL DEFAULT 0,
  effects JSON NOT NULL,
  applied_at DATETIME NOT NULL,
  UNIQUE KEY unique_crime_preparation (opportunity_id, code),
  INDEX crime_preparations_user_index (user_id),
  FOREIGN KEY (opportunity_id) REFERENCES crime_opportunities(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS crime_opportunity_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  gang_member_id BIGINT UNSIGNED NOT NULL,
  role_code VARCHAR(80) NOT NULL,
  assigned_at DATETIME NOT NULL,
  UNIQUE KEY unique_crime_crew_assignment (opportunity_id, gang_member_id),
  INDEX crime_opportunity_assignments_user_index (user_id),
  FOREIGN KEY (opportunity_id) REFERENCES crime_opportunities(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (gang_member_id) REFERENCES player_gang_members(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS crime_opportunity_equipment (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  asset_type ENUM('item','weapon') NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  selected_at DATETIME NOT NULL,
  UNIQUE KEY unique_crime_equipment (opportunity_id, asset_type, asset_id),
  INDEX crime_opportunity_equipment_user_index (user_id),
  FOREIGN KEY (opportunity_id) REFERENCES crime_opportunities(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS crime_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','event_pending','resolved','abandoned') NOT NULL DEFAULT 'active',
  idempotency_key VARCHAR(80) NULL,
  started_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  outcome VARCHAR(80) NULL,
  success_chance INT NOT NULL DEFAULT 0,
  disaster_chance INT NOT NULL DEFAULT 0,
  police_chance INT NOT NULL DEFAULT 0,
  reward_cash BIGINT UNSIGNED NOT NULL DEFAULT 0,
  reward_dirty_cash BIGINT UNSIGNED NOT NULL DEFAULT 0,
  heat_gained INT NOT NULL DEFAULT 0,
  experience_gained INT NOT NULL DEFAULT 0,
  reputation_gained INT NOT NULL DEFAULT 0,
  event_code VARCHAR(100) NULL,
  selected_decision_code VARCHAR(100) NULL,
  result JSON NULL,
  resolved TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_active_opportunity_run (user_id, opportunity_id),
  UNIQUE KEY unique_crime_idempotency (user_id, idempotency_key),
  INDEX crime_runs_user_status_index (user_id, status),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (opportunity_id) REFERENCES crime_opportunities(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS crime_run_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  gang_member_id BIGINT UNSIGNED NOT NULL,
  role_code VARCHAR(80) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY unique_crime_run_assignment (run_id, gang_member_id),
  FOREIGN KEY (run_id) REFERENCES crime_runs(id) ON DELETE CASCADE,
  FOREIGN KEY (gang_member_id) REFERENCES player_gang_members(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS crime_run_equipment (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  asset_type ENUM('item','weapon') NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  effects JSON NULL,
  created_at DATETIME NOT NULL,
  INDEX crime_run_equipment_asset_index (asset_type, asset_id),
  FOREIGN KEY (run_id) REFERENCES crime_runs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS crime_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  event_code VARCHAR(100) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  status ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
  choices JSON NOT NULL,
  created_at DATETIME NOT NULL,
  resolved_at DATETIME NULL,
  INDEX crime_events_run_status_index (run_id, status),
  FOREIGN KEY (run_id) REFERENCES crime_runs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS crime_npc_involvement (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  npc_id BIGINT UNSIGNED NOT NULL,
  involvement_type VARCHAR(80) NOT NULL,
  relationship_change INT NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  INDEX crime_npc_involvement_npc_index (npc_id),
  FOREIGN KEY (run_id) REFERENCES crime_runs(id) ON DELETE CASCADE,
  FOREIGN KEY (npc_id) REFERENCES npcs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS npc_relationships (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  npc_id BIGINT UNSIGNED NOT NULL,
  relationship_type VARCHAR(80) NOT NULL DEFAULT 'unknown',
  trust INT NOT NULL DEFAULT 0,
  fear INT NOT NULL DEFAULT 0,
  respect INT NOT NULL DEFAULT 0,
  suspicion INT NOT NULL DEFAULT 0,
  debt INT NOT NULL DEFAULT 0,
  loyalty INT NOT NULL DEFAULT 0,
  gratitude INT NOT NULL DEFAULT 0,
  blackmail_leverage INT NOT NULL DEFAULT 0,
  known_player_identity TINYINT(1) NOT NULL DEFAULT 0,
  betrayal_risk INT NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_user_npc_relationship (user_id, npc_id),
  INDEX npc_relationships_type_index (relationship_type),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (npc_id) REFERENCES npcs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS npc_timeline_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  npc_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  crime_run_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  INDEX npc_timeline_events_npc_index (npc_id, created_at),
  FOREIGN KEY (npc_id) REFERENCES npcs(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (crime_run_id) REFERENCES crime_runs(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS npc_status_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  npc_id BIGINT UNSIGNED NOT NULL,
  old_status VARCHAR(40) NULL,
  new_status VARCHAR(40) NOT NULL,
  reason VARCHAR(180) NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  INDEX npc_status_logs_npc_index (npc_id, created_at),
  FOREIGN KEY (npc_id) REFERENCES npcs(id) ON DELETE CASCADE
);
