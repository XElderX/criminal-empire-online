-- Criminal Empire Online v0.7.3 — Loadout UX & Carry Inventory Polish

ALTER TABLE item_definitions
  ADD COLUMN item_role VARCHAR(60) NULL AFTER is_storage_only,
  ADD COLUMN carry_role VARCHAR(60) NULL AFTER item_role,
  ADD COLUMN is_consumable TINYINT(1) NOT NULL DEFAULT 0 AFTER carry_role,
  ADD COLUMN is_task_item TINYINT(1) NOT NULL DEFAULT 0 AFTER is_consumable,
  ADD COLUMN is_quest_item TINYINT(1) NOT NULL DEFAULT 0 AFTER is_task_item,
  ADD COLUMN can_satisfy_task_requirement TINYINT(1) NOT NULL DEFAULT 1 AFTER is_quest_item,
  ADD COLUMN preferred_equipment_slot VARCHAR(60) NULL AFTER can_satisfy_task_requirement,
  ADD COLUMN carry_behavior VARCHAR(60) NULL AFTER preferred_equipment_slot,
  ADD COLUMN use_behavior VARCHAR(60) NULL AFTER carry_behavior;

ALTER TABLE user_items
  ADD COLUMN reserved_for_task_id BIGINT UNSIGNED NULL AFTER is_reserved,
  ADD COLUMN reserved_for_task_type VARCHAR(80) NULL AFTER reserved_for_task_id,
  ADD COLUMN carried_purpose VARCHAR(80) NULL AFTER reserved_for_task_type,
  ADD COLUMN task_item_state VARCHAR(80) NULL AFTER carried_purpose;
