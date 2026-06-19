-- Criminal Empire Online v0.6.4 — World Tutorial & Player Guidance Update

ALTER TABLE user_tutorial_progress
  ADD COLUMN tutorial_key VARCHAR(80) NOT NULL DEFAULT 'new_player_world_guide' AFTER user_id,
  ADD COLUMN tutorial_version VARCHAR(20) NOT NULL DEFAULT '0.6.4' AFTER tutorial_key,
  ADD COLUMN completed_tutorial_version VARCHAR(20) NULL AFTER tutorial_version,
  ADD COLUMN completed_update_tutorial_versions JSON NULL AFTER completed_tutorial_version,
  ADD COLUMN dismissed_update_tutorial_versions JSON NULL AFTER completed_update_tutorial_versions,
  ADD COLUMN reopened_at DATETIME NULL AFTER skipped_at,
  ADD COLUMN last_seen_at DATETIME NULL AFTER reopened_at;

CREATE TABLE IF NOT EXISTS tutorial_definitions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tutorial_key VARCHAR(80) NOT NULL,
  version VARCHAR(20) NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_tutorial_definition (tutorial_key, version)
);

CREATE TABLE IF NOT EXISTS tutorial_steps (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tutorial_key VARCHAR(80) NOT NULL,
  tutorial_version VARCHAR(20) NOT NULL,
  step_key VARCHAR(100) NOT NULL,
  module_key VARCHAR(80) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  objective_type VARCHAR(60) NOT NULL,
  objective_payload JSON NULL,
  route_hint VARCHAR(160) NULL,
  reward_payload JSON NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_optional TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_tutorial_step (tutorial_key, tutorial_version, step_key),
  INDEX tutorial_steps_order (tutorial_key, tutorial_version, sort_order)
);

CREATE TABLE IF NOT EXISTS user_tutorial_step_progress (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  tutorial_key VARCHAR(80) NOT NULL,
  tutorial_version VARCHAR(20) NOT NULL,
  step_key VARCHAR(100) NOT NULL,
  status ENUM('active','completed','skipped') NOT NULL DEFAULT 'active',
  completed_at DATETIME NULL,
  skipped_at DATETIME NULL,
  reward_claimed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_user_tutorial_step (user_id, tutorial_key, tutorial_version, step_key),
  INDEX user_tutorial_step_status (user_id, tutorial_key, tutorial_version, status),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tutorial_objective_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  action_type VARCHAR(80) NOT NULL,
  page_key VARCHAR(80) NULL,
  related_type VARCHAR(80) NULL,
  related_id BIGINT UNSIGNED NULL,
  payload JSON NULL,
  created_at DATETIME NOT NULL,
  INDEX tutorial_objective_user_action (user_id, action_type, created_at),
  INDEX tutorial_objective_user_page (user_id, page_key, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS help_tips (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tip_key VARCHAR(100) NOT NULL UNIQUE,
  page_key VARCHAR(80) NOT NULL,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  guide_section_key VARCHAR(100) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX help_tips_page_active (page_key, is_active, sort_order)
);

CREATE TABLE IF NOT EXISTS user_help_tip_state (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  tip_key VARCHAR(100) NOT NULL,
  page_key VARCHAR(80) NOT NULL,
  status ENUM('active','dismissed') NOT NULL DEFAULT 'active',
  dismissed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY unique_user_help_tip (user_id, tip_key),
  INDEX user_help_tip_page (user_id, page_key, status),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS guide_sections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_key VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
);

UPDATE user_tutorial_progress
SET
  tutorial_key = CASE
    WHEN status = 'completed' AND COALESCE(completed_tutorial_version, '') <> '0.6.4' THEN 'world_systems_update'
    ELSE 'new_player_world_guide'
  END,
  tutorial_version = '0.6.4',
  completed_tutorial_version = CASE
    WHEN status = 'completed' AND completed_tutorial_version IS NULL THEN '0.6.3.1'
    ELSE completed_tutorial_version
  END,
  completed_update_tutorial_versions = COALESCE(completed_update_tutorial_versions, JSON_ARRAY()),
  dismissed_update_tutorial_versions = COALESCE(dismissed_update_tutorial_versions, JSON_ARRAY()),
  current_step_code = CASE
    WHEN status = 'completed' AND COALESCE(completed_tutorial_version, '') <> '0.6.4' THEN 'update_open_world_map'
    ELSE current_step_code
  END,
  completed_steps = CASE
    WHEN status = 'completed' AND COALESCE(completed_tutorial_version, '') <> '0.6.4' THEN JSON_ARRAY()
    ELSE completed_steps
  END,
  status = CASE
    WHEN status = 'completed' AND COALESCE(completed_tutorial_version, '') <> '0.6.4' THEN 'active'
    ELSE status
  END,
  completed_at = CASE
    WHEN status = 'completed' AND COALESCE(completed_tutorial_version, '') <> '0.6.4' THEN NULL
    ELSE completed_at
  END,
  updated_at = NOW();
