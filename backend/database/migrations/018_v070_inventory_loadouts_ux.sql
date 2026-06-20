-- Criminal Empire Online v0.7 — UX Navigation & Inventory Loadout Expansion

ALTER TABLE item_definitions
  ADD COLUMN size_class VARCHAR(20) NOT NULL DEFAULT 'small' AFTER storage_units,
  ADD COLUMN carry_units DECIMAL(6,2) NOT NULL DEFAULT 1.00 AFTER size_class,
  ADD COLUMN stack_limit INT UNSIGNED NOT NULL DEFAULT 1 AFTER carry_units,
  ADD COLUMN allowed_slots JSON NULL AFTER stack_limit,
  ADD COLUMN item_tags JSON NULL AFTER allowed_slots,
  ADD COLUMN item_effects JSON NULL AFTER item_tags,
  ADD COLUMN legality VARCHAR(40) NOT NULL DEFAULT 'legal' AFTER item_effects,
  ADD COLUMN visible_illegal TINYINT(1) NOT NULL DEFAULT 0 AFTER legality,
  ADD COLUMN concealment_rating INT NOT NULL DEFAULT 50 AFTER visible_illegal,
  ADD COLUMN is_equippable TINYINT(1) NOT NULL DEFAULT 1 AFTER concealment_rating,
  ADD COLUMN is_carryable TINYINT(1) NOT NULL DEFAULT 1 AFTER is_equippable,
  ADD COLUMN is_storage_only TINYINT(1) NOT NULL DEFAULT 0 AFTER is_carryable;

ALTER TABLE user_items
  ADD COLUMN current_location_type ENUM('owned','carried','equipped','warehouse','shop','lost') NOT NULL DEFAULT 'owned' AFTER quantity,
  ADD COLUMN holder_type ENUM('user','boss','crew','warehouse') NOT NULL DEFAULT 'user' AFTER current_location_type,
  ADD COLUMN holder_id BIGINT UNSIGNED NULL AFTER holder_type,
  ADD COLUMN equipped_slot VARCHAR(60) NULL AFTER holder_id,
  ADD COLUMN carried_slot VARCHAR(60) NULL AFTER equipped_slot,
  ADD COLUMN durability INT UNSIGNED NULL AFTER carried_slot,
  ADD COLUMN is_reserved TINYINT(1) NOT NULL DEFAULT 0 AFTER durability;

CREATE TABLE IF NOT EXISTS character_loadout_summaries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  character_type ENUM('boss','crew','npc') NOT NULL,
  character_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  loadout_summary_json JSON NOT NULL,
  carry_capacity_units DECIMAL(6,2) NOT NULL DEFAULT 5.00,
  used_carry_units DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_character_loadout (user_id, character_type, character_id),
  INDEX character_loadouts_user (user_id, character_type),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS character_carry_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  character_type ENUM('boss','crew','npc') NOT NULL,
  character_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  asset_type ENUM('item','weapon') NOT NULL DEFAULT 'item',
  asset_id BIGINT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  carry_units_each DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  carried_slot VARCHAR(60) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_character_carry_asset (user_id, character_type, character_id, asset_type, asset_id),
  INDEX character_carry_user (user_id, character_type, character_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS inventory_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  character_type ENUM('boss','crew','npc') NULL,
  character_id BIGINT UNSIGNED NULL,
  item_key VARCHAR(120) NULL,
  asset_type VARCHAR(20) NULL,
  asset_id BIGINT UNSIGNED NULL,
  action_type VARCHAR(80) NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  from_holder VARCHAR(80) NULL,
  to_holder VARCHAR(80) NULL,
  description TEXT NOT NULL,
  metadata JSON NULL,
  created_at DATETIME NOT NULL,
  INDEX inventory_logs_user_created (user_id, created_at),
  INDEX inventory_logs_action (action_type, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE shop_transactions
  ADD COLUMN payment_type ENUM('cash','bank','dirty_money') NOT NULL DEFAULT 'cash' AFTER total_price,
  ADD COLUMN clean_cash_delta BIGINT NOT NULL DEFAULT 0 AFTER payment_type,
  ADD COLUMN dirty_money_delta BIGINT NOT NULL DEFAULT 0 AFTER clean_cash_delta,
  ADD COLUMN bank_delta BIGINT NOT NULL DEFAULT 0 AFTER dirty_money_delta,
  ADD COLUMN heat_delta INT NOT NULL DEFAULT 0 AFTER bank_delta,
  ADD COLUMN transaction_visibility VARCHAR(40) NOT NULL DEFAULT 'normal' AFTER heat_delta;

ALTER TABLE shops
  ADD COLUMN accepted_payment_types_json JSON NULL AFTER buys_categories_json,
  ADD COLUMN accepts_dirty_money TINYINT(1) NOT NULL DEFAULT 0 AFTER accepted_payment_types_json,
  ADD COLUMN accepts_clean_cash TINYINT(1) NOT NULL DEFAULT 1 AFTER accepts_dirty_money,
  ADD COLUMN accepts_bank TINYINT(1) NOT NULL DEFAULT 0 AFTER accepts_clean_cash,
  ADD COLUMN dirty_money_markup DECIMAL(5,2) NOT NULL DEFAULT 1.00 AFTER accepts_bank,
  ADD COLUMN clean_cash_markup DECIMAL(5,2) NOT NULL DEFAULT 1.00 AFTER dirty_money_markup,
  ADD COLUMN sale_visibility VARCHAR(40) NOT NULL DEFAULT 'normal' AFTER clean_cash_markup;
