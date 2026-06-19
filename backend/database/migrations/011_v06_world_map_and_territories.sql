-- Criminal Empire Online v0.6 — Game Map & Territories

CREATE TABLE IF NOT EXISTS world_regions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(140) NOT NULL,
  description TEXT NOT NULL,
  map_asset VARCHAR(255) NOT NULL,
  overlay_asset VARCHAR(255) NULL,
  region_type VARCHAR(80) NOT NULL DEFAULT 'district',
  travel_cost_cash BIGINT UNSIGNED NOT NULL DEFAULT 0,
  travel_cost_energy INT NOT NULL DEFAULT 0,
  base_heat INT NOT NULL DEFAULT 0,
  police_pressure INT NOT NULL DEFAULT 0,
  danger_level INT NOT NULL DEFAULT 0,
  recommended_level INT NOT NULL DEFAULT 1,
  unlock_key VARCHAR(100) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX world_regions_active_sort (is_active, sort_order),
  INDEX world_regions_type (region_type)
);

CREATE TABLE IF NOT EXISTS world_locations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  region_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(120) NOT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  location_type VARCHAR(80) NOT NULL DEFAULT 'district',
  x_percent DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  y_percent DECIMAL(5,2) NOT NULL DEFAULT 50.00,
  width_percent DECIMAL(5,2) NULL,
  height_percent DECIMAL(5,2) NULL,
  heat_level INT NOT NULL DEFAULT 0,
  police_pressure INT NOT NULL DEFAULT 0,
  danger_level INT NOT NULL DEFAULT 0,
  min_level INT NOT NULL DEFAULT 1,
  unlock_key VARCHAR(100) NULL,
  linked_territory_id BIGINT UNSIGNED NULL,
  linked_business_id BIGINT UNSIGNED NULL,
  linked_drug_region VARCHAR(100) NULL,
  linked_feature_key VARCHAR(100) NULL,
  available_actions_json JSON NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_world_location_slug (slug),
  INDEX world_locations_region_active (region_id, is_active, sort_order),
  INDEX world_locations_territory (linked_territory_id),
  FOREIGN KEY (region_id) REFERENCES world_regions(id) ON DELETE CASCADE,
  FOREIGN KEY (linked_territory_id) REFERENCES territories(id) ON DELETE SET NULL,
  FOREIGN KEY (linked_business_id) REFERENCES businesses(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS user_location_state (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  current_region_id BIGINT UNSIGNED NULL,
  current_location_id BIGINT UNSIGNED NULL,
  last_travel_at DATETIME NULL,
  travel_cooldown_until DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_user_location_state (user_id),
  INDEX user_location_region (current_region_id),
  INDEX user_location_location (current_location_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (current_region_id) REFERENCES world_regions(id) ON DELETE SET NULL,
  FOREIGN KEY (current_location_id) REFERENCES world_locations(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS territory_map_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  territory_id BIGINT UNSIGNED NOT NULL,
  world_region_id BIGINT UNSIGNED NOT NULL,
  world_location_id BIGINT UNSIGNED NULL,
  control_display_mode VARCHAR(80) NOT NULL DEFAULT 'normal',
  is_primary TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_territory_map_link (territory_id, world_region_id, world_location_id),
  INDEX territory_map_links_region (world_region_id),
  INDEX territory_map_links_location (world_location_id),
  FOREIGN KEY (territory_id) REFERENCES territories(id) ON DELETE CASCADE,
  FOREIGN KEY (world_region_id) REFERENCES world_regions(id) ON DELETE CASCADE,
  FOREIGN KEY (world_location_id) REFERENCES world_locations(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS map_activity_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  world_location_id BIGINT UNSIGNED NOT NULL,
  feature_type VARCHAR(80) NOT NULL,
  feature_key VARCHAR(120) NOT NULL,
  label VARCHAR(140) NOT NULL,
  route_hint VARCHAR(140) NULL,
  min_level INT NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX map_activity_links_location (world_location_id, is_active, sort_order),
  INDEX map_activity_links_feature (feature_type, feature_key),
  FOREIGN KEY (world_location_id) REFERENCES world_locations(id) ON DELETE CASCADE
);
