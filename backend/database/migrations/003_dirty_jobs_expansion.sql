ALTER TABLE weapons
  ADD COLUMN equipment_slot VARCHAR(40) NOT NULL DEFAULT 'weapon' AFTER class,
  ADD COLUMN effects JSON NULL AFTER concealment,
  ADD COLUMN base_durability INT NOT NULL DEFAULT 100 AFTER effects,
  ADD COLUMN storage_units INT NOT NULL DEFAULT 4 AFTER base_durability,
  ADD COLUMN illegal TINYINT(1) NOT NULL DEFAULT 1 AFTER storage_units;

ALTER TABLE player_gang_members
  MODIFY COLUMN status ENUM(
    'active',
    'busy',
    'injured',
    'recovering',
    'arrested',
    'missing',
    'dismissed',
    'dead'
  ) NOT NULL DEFAULT 'active',
  ADD COLUMN jobs_completed INT UNSIGNED NOT NULL DEFAULT 0 AFTER experience,
  ADD COLUMN jobs_failed INT UNSIGNED NOT NULL DEFAULT 0 AFTER jobs_completed,
  ADD COLUMN arrests INT UNSIGNED NOT NULL DEFAULT 0 AFTER jobs_failed,
  ADD COLUMN injuries INT UNSIGNED NOT NULL DEFAULT 0 AFTER arrests,
  ADD COLUMN total_earnings BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER injuries,
  ADD COLUMN recovering_until DATETIME NULL AFTER current_assignment_id,
  ADD COLUMN arrested_until DATETIME NULL AFTER recovering_until,
  ADD COLUMN dismissed_at DATETIME NULL AFTER last_salary_at,
  ADD COLUMN dismissal_reason VARCHAR(255) NULL AFTER dismissed_at;

CREATE TABLE user_tutorial_progress (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  status ENUM('active', 'completed', 'skipped') NOT NULL DEFAULT 'active',
  current_step_code VARCHAR(80) NOT NULL DEFAULT 'welcome',
  completed_steps JSON NOT NULL,
  rewards_claimed JSON NOT NULL,
  started_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  skipped_at DATETIME NULL,
  updated_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE tutorial_step_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  step_code VARCHAR(80) NOT NULL,
  event_type ENUM('completed', 'reward', 'skipped', 'reopened') NOT NULL,
  details JSON NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY unique_tutorial_event (user_id, step_code, event_type),
  INDEX tutorial_logs_user_created (user_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO user_tutorial_progress (
  user_id,
  status,
  current_step_code,
  completed_steps,
  rewards_claimed,
  started_at,
  completed_at,
  updated_at
)
SELECT
  id,
  'completed',
  'completed',
  JSON_ARRAY(
    'welcome',
    'first_money',
    'first_illegal_job',
    'first_recruit',
    'crew_overview',
    'basic_equipment',
    'prepare_dirty_job',
    'execute_dirty_job',
    'heat_consequences',
    'warehouse_intro'
  ),
  JSON_ARRAY(),
  NOW(),
  NOW(),
  NOW()
FROM users
WHERE NOT EXISTS (
  SELECT 1
  FROM user_tutorial_progress progress
  WHERE progress.user_id = users.id
);

CREATE TABLE item_definitions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  category ENUM(
    'tool',
    'melee_weapon',
    'armor',
    'clothing',
    'utility',
    'stolen_good',
    'vehicle_part',
    'production_supply',
    'general'
  ) NOT NULL,
  equipment_slot VARCHAR(40) NULL,
  description TEXT NULL,
  price BIGINT UNSIGNED NOT NULL DEFAULT 0,
  storage_units INT UNSIGNED NOT NULL DEFAULT 1,
  max_durability INT UNSIGNED NOT NULL DEFAULT 100,
  illegal TINYINT(1) NOT NULL DEFAULT 0,
  stackable TINYINT(1) NOT NULL DEFAULT 1,
  effects JSON NOT NULL,
  requirements JSON NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX item_definitions_category (category, active)
);

CREATE TABLE user_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  item_definition_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_user_item (user_id, item_definition_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (item_definition_id) REFERENCES item_definitions(id)
);

CREATE TABLE crew_equipment (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  gang_member_id BIGINT UNSIGNED NOT NULL,
  asset_type ENUM('item', 'weapon') NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  equipment_slot VARCHAR(40) NOT NULL,
  durability INT UNSIGNED NOT NULL DEFAULT 100,
  equipped_at DATETIME NOT NULL,
  UNIQUE KEY unique_member_slot (gang_member_id, equipment_slot),
  UNIQUE KEY unique_member_asset (gang_member_id, asset_type, asset_id),
  INDEX crew_equipment_owner_asset (user_id, asset_type, asset_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (gang_member_id) REFERENCES player_gang_members(id) ON DELETE CASCADE
);

CREATE TABLE crew_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gang_member_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(60) NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  related_dirty_job_run_id BIGINT UNSIGNED NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  INDEX crew_history_member_created (gang_member_id, created_at),
  INDEX crew_history_user_created (user_id, created_at),
  FOREIGN KEY (gang_member_id) REFERENCES player_gang_members(id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE npc_contacts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  npc_id BIGINT UNSIGNED NOT NULL UNIQUE,
  contact_type VARCHAR(60) NOT NULL,
  job_categories JSON NOT NULL,
  payment_reliability INT NOT NULL DEFAULT 70,
  criminal_connections INT NOT NULL DEFAULT 20,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  FOREIGN KEY (npc_id) REFERENCES npcs(id) ON DELETE CASCADE
);

CREATE TABLE contact_relationships (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  contact_id BIGINT UNSIGNED NOT NULL,
  trust INT NOT NULL DEFAULT 0,
  jobs_completed INT UNSIGNED NOT NULL DEFAULT 0,
  jobs_failed INT UNSIGNED NOT NULL DEFAULT 0,
  unavailable_until DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_user_contact (user_id, contact_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (contact_id) REFERENCES npc_contacts(id) ON DELETE CASCADE
);

CREATE TABLE dirty_job_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(100) NOT NULL UNIQUE,
  category ENUM(
    'theft',
    'burglary',
    'vehicle_crime',
    'smuggling',
    'production',
    'intimidation',
    'robbery',
    'sabotage',
    'delivery',
    'collection'
  ) NOT NULL,
  tier TINYINT UNSIGNED NOT NULL DEFAULT 1,
  title VARCHAR(160) NOT NULL,
  short_description VARCHAR(255) NOT NULL,
  introduction TEXT NOT NULL,
  briefing TEXT NOT NULL,
  preparation_text TEXT NOT NULL,
  execution_text TEXT NOT NULL,
  success_text TEXT NOT NULL,
  partial_success_text TEXT NOT NULL,
  failure_text TEXT NOT NULL,
  critical_failure_text TEXT NOT NULL,
  duration_seconds INT UNSIGNED NOT NULL,
  energy_cost INT UNSIGNED NOT NULL DEFAULT 0,
  reward_min BIGINT UNSIGNED NOT NULL,
  reward_max BIGINT UNSIGNED NOT NULL,
  dirty_money_percent INT UNSIGNED NOT NULL DEFAULT 0,
  experience_gain INT UNSIGNED NOT NULL DEFAULT 0,
  reputation_gain INT NOT NULL DEFAULT 0,
  base_success_rate INT NOT NULL DEFAULT 60,
  difficulty INT NOT NULL DEFAULT 20,
  heat_min INT NOT NULL DEFAULT 1,
  heat_max INT NOT NULL DEFAULT 5,
  min_level INT NOT NULL DEFAULT 1,
  min_reputation INT NOT NULL DEFAULT 0,
  min_crew_size INT NOT NULL DEFAULT 0,
  required_roles JSON NOT NULL,
  required_items JSON NOT NULL,
  preparation_options JSON NOT NULL,
  event_definition JSON NULL,
  reward_definition JSON NOT NULL,
  requires_warehouse TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX dirty_job_templates_unlock (active, tier, min_level, min_reputation)
);

CREATE TABLE dirty_job_opportunities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  template_id BIGINT UNSIGNED NOT NULL,
  contact_id BIGINT UNSIGNED NULL,
  territory_id BIGINT UNSIGNED NOT NULL,
  target_npc_id BIGINT UNSIGNED NULL,
  target_business_id BIGINT UNSIGNED NULL,
  title_override VARCHAR(160) NULL,
  narrative_variables JSON NOT NULL,
  reward_multiplier DECIMAL(6,3) NOT NULL DEFAULT 1.000,
  risk_modifier INT NOT NULL DEFAULT 0,
  status ENUM('available', 'accepted', 'completed', 'failed', 'expired', 'cancelled') NOT NULL DEFAULT 'available',
  available_from DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  accepted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX dirty_job_opportunities_user_status (user_id, status, expires_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (template_id) REFERENCES dirty_job_templates(id),
  FOREIGN KEY (contact_id) REFERENCES npc_contacts(id) ON DELETE SET NULL,
  FOREIGN KEY (territory_id) REFERENCES territories(id),
  FOREIGN KEY (target_npc_id) REFERENCES npcs(id) ON DELETE SET NULL
);

CREATE TABLE dirty_job_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  idempotency_key CHAR(36) NOT NULL,
  status ENUM(
    'accepted',
    'preparing',
    'ready',
    'executing',
    'awaiting_decision',
    'completed',
    'partially_completed',
    'failed',
    'cancelled',
    'expired'
  ) NOT NULL DEFAULT 'accepted',
  accepted_at DATETIME NOT NULL,
  execution_started_at DATETIME NULL,
  completes_at DATETIME NULL,
  resolved_at DATETIME NULL,
  selected_decision_code VARCHAR(80) NULL,
  calculated_success_chance INT NULL,
  outcome VARCHAR(40) NULL,
  cash_reward BIGINT UNSIGNED NOT NULL DEFAULT 0,
  dirty_cash_reward BIGINT UNSIGNED NOT NULL DEFAULT 0,
  heat_gained INT NOT NULL DEFAULT 0,
  experience_gained INT UNSIGNED NOT NULL DEFAULT 0,
  reputation_gained INT NOT NULL DEFAULT 0,
  result JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_dirty_job_request (user_id, idempotency_key),
  UNIQUE KEY unique_dirty_job_opportunity (user_id, opportunity_id),
  INDEX dirty_job_runs_user_status (user_id, status),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (opportunity_id) REFERENCES dirty_job_opportunities(id)
);

CREATE TABLE dirty_job_preparations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dirty_job_run_id BIGINT UNSIGNED NOT NULL,
  action_code VARCHAR(80) NOT NULL,
  cash_cost BIGINT UNSIGNED NOT NULL DEFAULT 0,
  energy_cost INT UNSIGNED NOT NULL DEFAULT 0,
  success_bonus INT NOT NULL DEFAULT 0,
  heat_modifier INT NOT NULL DEFAULT 0,
  injury_modifier INT NOT NULL DEFAULT 0,
  reward_modifier DECIMAL(6,3) NOT NULL DEFAULT 1.000,
  details JSON NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY unique_dirty_job_preparation (dirty_job_run_id, action_code),
  FOREIGN KEY (dirty_job_run_id) REFERENCES dirty_job_runs(id) ON DELETE CASCADE
);

CREATE TABLE dirty_job_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dirty_job_run_id BIGINT UNSIGNED NOT NULL,
  gang_member_id BIGINT UNSIGNED NOT NULL,
  role_code VARCHAR(60) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY unique_dirty_job_member (dirty_job_run_id, gang_member_id),
  UNIQUE KEY unique_dirty_job_role (dirty_job_run_id, role_code),
  FOREIGN KEY (dirty_job_run_id) REFERENCES dirty_job_runs(id) ON DELETE CASCADE,
  FOREIGN KEY (gang_member_id) REFERENCES player_gang_members(id)
);

CREATE TABLE dirty_job_equipment (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dirty_job_run_id BIGINT UNSIGNED NOT NULL,
  crew_equipment_id BIGINT UNSIGNED NULL,
  gang_member_id BIGINT UNSIGNED NOT NULL,
  effects_snapshot JSON NOT NULL,
  durability_before INT UNSIGNED NOT NULL,
  durability_after INT UNSIGNED NULL,
  lost TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  UNIQUE KEY unique_dirty_job_equipment (dirty_job_run_id, crew_equipment_id),
  FOREIGN KEY (dirty_job_run_id) REFERENCES dirty_job_runs(id) ON DELETE CASCADE,
  FOREIGN KEY (crew_equipment_id) REFERENCES crew_equipment(id) ON DELETE SET NULL,
  FOREIGN KEY (gang_member_id) REFERENCES player_gang_members(id)
);

CREATE TABLE building_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE property_listings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  building_type_id BIGINT UNSIGNED NOT NULL,
  seller_npc_id BIGINT UNSIGNED NULL,
  territory_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  purchase_price BIGINT UNSIGNED NOT NULL,
  storage_capacity INT UNSIGNED NOT NULL DEFAULT 0,
  vehicle_capacity INT UNSIGNED NOT NULL DEFAULT 0,
  security_rating INT NOT NULL DEFAULT 20,
  condition_rating INT NOT NULL DEFAULT 50,
  weekly_operating_cost BIGINT UNSIGNED NOT NULL DEFAULT 0,
  heat_visibility INT NOT NULL DEFAULT 10,
  status ENUM('available', 'inactive') NOT NULL DEFAULT 'available',
  repeatable TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX property_listings_type_status (building_type_id, status),
  FOREIGN KEY (building_type_id) REFERENCES building_types(id),
  FOREIGN KEY (seller_npc_id) REFERENCES npcs(id) ON DELETE SET NULL,
  FOREIGN KEY (territory_id) REFERENCES territories(id)
);

CREATE TABLE player_buildings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  building_type_id BIGINT UNSIGNED NOT NULL,
  source_listing_id BIGINT UNSIGNED NOT NULL,
  territory_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  storage_capacity INT UNSIGNED NOT NULL DEFAULT 0,
  vehicle_capacity INT UNSIGNED NOT NULL DEFAULT 0,
  security_rating INT NOT NULL DEFAULT 20,
  condition_rating INT NOT NULL DEFAULT 50,
  weekly_operating_cost BIGINT UNSIGNED NOT NULL DEFAULT 0,
  operating_debt BIGINT UNSIGNED NOT NULL DEFAULT 0,
  heat_visibility INT NOT NULL DEFAULT 10,
  status ENUM('active', 'restricted', 'closed') NOT NULL DEFAULT 'active',
  purchased_at DATETIME NOT NULL,
  last_cost_processed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_user_listing_purchase (user_id, source_listing_id),
  INDEX player_buildings_user_type (user_id, building_type_id, status),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (building_type_id) REFERENCES building_types(id),
  FOREIGN KEY (source_listing_id) REFERENCES property_listings(id),
  FOREIGN KEY (territory_id) REFERENCES territories(id)
);

CREATE TABLE warehouse_storage (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  asset_type ENUM('item', 'weapon', 'drug') NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  quantity BIGINT UNSIGNED NOT NULL DEFAULT 0,
  reserved_quantity BIGINT UNSIGNED NOT NULL DEFAULT 0,
  storage_units_each DECIMAL(10,3) NOT NULL DEFAULT 1.000,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_warehouse_asset (warehouse_id, asset_type, asset_id),
  FOREIGN KEY (warehouse_id) REFERENCES player_buildings(id) ON DELETE CASCADE
);

CREATE TABLE vehicles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  warehouse_id BIGINT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  vehicle_type VARCHAR(60) NOT NULL,
  condition_rating INT NOT NULL DEFAULT 50,
  estimated_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
  stolen TINYINT(1) NOT NULL DEFAULT 0,
  evidence_level INT NOT NULL DEFAULT 0,
  status ENUM('unsecured', 'stored', 'assigned', 'sold', 'lost') NOT NULL DEFAULT 'unsecured',
  acquired_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX vehicles_user_status (user_id, status),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (warehouse_id) REFERENCES player_buildings(id) ON DELETE SET NULL
);

CREATE TABLE building_upgrades (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  price BIGINT UNSIGNED NOT NULL,
  effects JSON NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

CREATE TABLE player_building_upgrades (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  player_building_id BIGINT UNSIGNED NOT NULL,
  building_upgrade_id BIGINT UNSIGNED NOT NULL,
  installed_at DATETIME NOT NULL,
  UNIQUE KEY unique_building_upgrade (player_building_id, building_upgrade_id),
  FOREIGN KEY (player_building_id) REFERENCES player_buildings(id) ON DELETE CASCADE,
  FOREIGN KEY (building_upgrade_id) REFERENCES building_upgrades(id)
);

CREATE TABLE storage_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  warehouse_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  action ENUM('deposit', 'withdraw', 'vehicle_store', 'vehicle_remove', 'reward_deposit') NOT NULL,
  asset_type VARCHAR(40) NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  quantity BIGINT UNSIGNED NOT NULL DEFAULT 1,
  description VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX storage_logs_warehouse_created (warehouse_id, created_at),
  FOREIGN KEY (warehouse_id) REFERENCES player_buildings(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE heat_actions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  action_code VARCHAR(60) NOT NULL,
  heat_reduced INT NOT NULL DEFAULT 0,
  energy_cost INT NOT NULL DEFAULT 0,
  cash_cost BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX heat_actions_user_created (user_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
