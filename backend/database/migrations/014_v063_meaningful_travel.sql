-- Criminal Empire Online v0.6.3 — Meaningful Travel & Local Presence

ALTER TABLE user_location_state
  ADD COLUMN last_region_id BIGINT UNSIGNED NULL AFTER current_location_id,
  ADD COLUMN last_location_id BIGINT UNSIGNED NULL AFTER last_region_id,
  ADD COLUMN travel_route_type VARCHAR(60) NULL AFTER travel_cooldown_until,
  ADD COLUMN travel_status ENUM('stationary','traveling','arrived','delayed','blocked','ambushed','stopped_by_police') NOT NULL DEFAULT 'stationary' AFTER travel_route_type,
  ADD COLUMN arrived_at DATETIME NULL AFTER travel_status,
  ADD COLUMN last_local_action_at DATETIME NULL AFTER arrived_at,
  ADD INDEX user_location_last_region (last_region_id),
  ADD INDEX user_location_last_location (last_location_id);

ALTER TABLE world_locations
  ADD COLUMN travel_requires_level INT NOT NULL DEFAULT 1 AFTER min_level,
  ADD COLUMN travel_requires_reputation INT NOT NULL DEFAULT 0 AFTER travel_requires_level,
  ADD COLUMN travel_risk_level INT NOT NULL DEFAULT 0 AFTER travel_requires_reputation,
  ADD COLUMN travel_event_profile VARCHAR(80) NULL AFTER travel_risk_level,
  ADD COLUMN local_presence_required_default TINYINT(1) NOT NULL DEFAULT 1 AFTER travel_event_profile,
  ADD COLUMN exploration_energy_cost INT NOT NULL DEFAULT 3 AFTER local_presence_required_default,
  ADD COLUMN exploration_cooldown_seconds INT NOT NULL DEFAULT 600 AFTER exploration_energy_cost;

ALTER TABLE quick_crime_location_rules
  ADD COLUMN local_presence_required_message VARCHAR(255) NULL AFTER requires_current_location,
  ADD COLUMN remote_preview_allowed TINYINT(1) NOT NULL DEFAULT 1 AFTER local_presence_required_message,
  ADD COLUMN travel_hint VARCHAR(255) NULL AFTER remote_preview_allowed;

ALTER TABLE dirty_job_location_rules
  ADD COLUMN requires_presence_at_source TINYINT(1) NOT NULL DEFAULT 0 AFTER requires_current_location,
  ADD COLUMN requires_presence_at_target TINYINT(1) NOT NULL DEFAULT 0 AFTER requires_presence_at_source,
  ADD COLUMN travel_required_before_accept TINYINT(1) NOT NULL DEFAULT 0 AFTER requires_presence_at_target,
  ADD COLUMN travel_required_before_execute TINYINT(1) NOT NULL DEFAULT 0 AFTER travel_required_before_accept,
  ADD COLUMN local_presence_required_message VARCHAR(255) NULL AFTER travel_required_before_execute;

CREATE TABLE IF NOT EXISTS user_travel_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  from_region_id BIGINT UNSIGNED NULL,
  from_location_id BIGINT UNSIGNED NULL,
  to_region_id BIGINT UNSIGNED NOT NULL,
  to_location_id BIGINT UNSIGNED NULL,
  route_type VARCHAR(60) NOT NULL DEFAULT 'cheap',
  status ENUM('stationary','traveling','arrived','delayed','blocked','ambushed','stopped_by_police') NOT NULL DEFAULT 'arrived',
  cash_cost BIGINT UNSIGNED NOT NULL DEFAULT 0,
  energy_cost INT NOT NULL DEFAULT 0,
  heat_delta INT NOT NULL DEFAULT 0,
  event_type VARCHAR(80) NULL,
  event_payload JSON NULL,
  discovered_type VARCHAR(80) NULL,
  discovered_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  INDEX user_travel_logs_user_created (user_id, created_at),
  INDEX user_travel_logs_to_location (to_location_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (from_region_id) REFERENCES world_regions(id) ON DELETE SET NULL,
  FOREIGN KEY (from_location_id) REFERENCES world_locations(id) ON DELETE SET NULL,
  FOREIGN KEY (to_region_id) REFERENCES world_regions(id) ON DELETE CASCADE,
  FOREIGN KEY (to_location_id) REFERENCES world_locations(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS user_location_presence (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  world_region_id BIGINT UNSIGNED NOT NULL,
  world_location_id BIGINT UNSIGNED NOT NULL,
  visits_count INT NOT NULL DEFAULT 0,
  last_visited_at DATETIME NULL,
  last_explored_at DATETIME NULL,
  familiarity_score INT NOT NULL DEFAULT 0,
  exploration_cooldown_until DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_user_location_presence (user_id, world_location_id),
  INDEX user_location_presence_region (world_region_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (world_region_id) REFERENCES world_regions(id) ON DELETE CASCADE,
  FOREIGN KEY (world_location_id) REFERENCES world_locations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS travel_event_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  min_danger INT NULL,
  min_police_pressure INT NULL,
  min_heat INT NULL,
  route_type VARCHAR(60) NULL,
  weight INT NOT NULL DEFAULT 10,
  effects_json JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX travel_event_templates_type (event_type, is_active, weight)
);
