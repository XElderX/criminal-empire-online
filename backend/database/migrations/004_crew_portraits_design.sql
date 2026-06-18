ALTER TABLE npcs
  ADD COLUMN portrait_set_key VARCHAR(64) NULL AFTER gender,
  ADD COLUMN portrait_stage_cache VARCHAR(30) NULL AFTER portrait_set_key,
  ADD COLUMN portrait_focal_x TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER portrait_stage_cache,
  ADD COLUMN portrait_focal_y TINYINT UNSIGNED NOT NULL DEFAULT 50 AFTER portrait_focal_x,
  ADD COLUMN birth_game_year INT NULL AFTER portrait_focal_y,
  ADD COLUMN birth_game_day SMALLINT UNSIGNED NULL AFTER birth_game_year,
  ADD COLUMN last_age_processed_game_year INT NULL AFTER birth_game_day,
  ADD INDEX npcs_portrait_set_key_index (portrait_set_key),
  ADD INDEX npcs_gender_portrait_index (gender, portrait_set_key);

CREATE TABLE world_state (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  current_game_year INT NOT NULL DEFAULT 1,
  current_game_day SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  last_age_processing_at DATETIME NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);

INSERT INTO world_state (
  id,
  current_game_year,
  current_game_day,
  created_at,
  updated_at
) VALUES (
  1,
  1,
  1,
  NOW(),
  NOW()
);

UPDATE npcs
SET
  birth_game_year = 1 - age,
  birth_game_day = 1,
  last_age_processed_game_year = 1,
  portrait_stage_cache = CASE
    WHEN age <= 24 THEN 'very_young'
    WHEN age <= 31 THEN 'young'
    WHEN age <= 40 THEN 'adult'
    WHEN age <= 55 THEN 'mature'
    ELSE 'elder'
  END
WHERE birth_game_year IS NULL;
