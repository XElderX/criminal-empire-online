-- Criminal Empire Online v0.6.1 — Map Gameplay Integration

CREATE TABLE IF NOT EXISTS quick_crime_location_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quick_crime_template_id BIGINT UNSIGNED NOT NULL,
  world_region_id BIGINT UNSIGNED NULL,
  world_location_id BIGINT UNSIGNED NULL,
  location_type VARCHAR(80) NULL,
  is_allowed TINYINT(1) NOT NULL DEFAULT 1,
  requires_current_location TINYINT(1) NOT NULL DEFAULT 1,
  reward_multiplier DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  heat_multiplier DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  police_risk_multiplier DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  danger_multiplier DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  min_level_override INT NULL,
  cooldown_seconds_override INT NULL,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_quick_crime_location_rule (quick_crime_template_id, world_region_id, world_location_id, location_type),
  INDEX quick_crime_location_rules_region (world_region_id, is_allowed),
  INDEX quick_crime_location_rules_location (world_location_id, is_allowed),
  FOREIGN KEY (quick_crime_template_id) REFERENCES quick_crime_templates(id) ON DELETE CASCADE,
  FOREIGN KEY (world_region_id) REFERENCES world_regions(id) ON DELETE CASCADE,
  FOREIGN KEY (world_location_id) REFERENCES world_locations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS dirty_job_location_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dirty_job_template_id BIGINT UNSIGNED NOT NULL,
  world_region_id BIGINT UNSIGNED NULL,
  world_location_id BIGINT UNSIGNED NULL,
  target_region_id BIGINT UNSIGNED NULL,
  target_location_id BIGINT UNSIGNED NULL,
  requires_current_location TINYINT(1) NOT NULL DEFAULT 0,
  reward_multiplier DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  heat_multiplier DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  police_risk_multiplier DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  danger_multiplier DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_dirty_job_location_rule (dirty_job_template_id, world_region_id, world_location_id),
  INDEX dirty_job_location_rules_region (world_region_id),
  INDEX dirty_job_location_rules_location (world_location_id),
  FOREIGN KEY (dirty_job_template_id) REFERENCES dirty_job_templates(id) ON DELETE CASCADE,
  FOREIGN KEY (world_region_id) REFERENCES world_regions(id) ON DELETE CASCADE,
  FOREIGN KEY (world_location_id) REFERENCES world_locations(id) ON DELETE CASCADE,
  FOREIGN KEY (target_region_id) REFERENCES world_regions(id) ON DELETE SET NULL,
  FOREIGN KEY (target_location_id) REFERENCES world_locations(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS local_opportunities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  world_region_id BIGINT UNSIGNED NOT NULL,
  world_location_id BIGINT UNSIGNED NOT NULL,
  opportunity_type VARCHAR(80) NOT NULL,
  source_type VARCHAR(80) NULL,
  source_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  status ENUM('available','used','expired') NOT NULL DEFAULT 'available',
  expires_at DATETIME NULL,
  discovered_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX local_opportunities_user_location (user_id, world_location_id, status),
  INDEX local_opportunities_region (world_region_id, status),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (world_region_id) REFERENCES world_regions(id) ON DELETE CASCADE,
  FOREIGN KEY (world_location_id) REFERENCES world_locations(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS location_exploration_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  world_region_id BIGINT UNSIGNED NOT NULL,
  world_location_id BIGINT UNSIGNED NOT NULL,
  result_type VARCHAR(80) NOT NULL,
  result_id BIGINT UNSIGNED NULL,
  energy_cost INT NOT NULL DEFAULT 3,
  created_at DATETIME NOT NULL,
  INDEX location_exploration_logs_user_location (user_id, world_location_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (world_region_id) REFERENCES world_regions(id) ON DELETE CASCADE,
  FOREIGN KEY (world_location_id) REFERENCES world_locations(id) ON DELETE CASCADE
);
