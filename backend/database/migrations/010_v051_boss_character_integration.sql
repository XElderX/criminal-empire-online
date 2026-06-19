-- Criminal Empire Online v0.5.1 — Boss Character Integration

ALTER TABLE users
  ADD COLUMN boss_shooting INT NOT NULL DEFAULT 30 AFTER combat,
  ADD COLUMN boss_driving INT NOT NULL DEFAULT 35 AFTER boss_shooting,
  ADD COLUMN boss_stealth INT NOT NULL DEFAULT 35 AFTER boss_driving,
  ADD COLUMN boss_intimidation INT NOT NULL DEFAULT 35 AFTER boss_stealth,
  ADD COLUMN boss_discipline INT NOT NULL DEFAULT 45 AFTER boss_intimidation,
  ADD COLUMN boss_street_knowledge INT NOT NULL DEFAULT 45 AFTER boss_discipline,
  ADD COLUMN boss_endurance INT NOT NULL DEFAULT 45 AFTER boss_street_knowledge,
  ADD COLUMN boss_age INT NOT NULL DEFAULT 33 AFTER boss_endurance,
  ADD COLUMN boss_gender VARCHAR(20) NULL AFTER boss_age,
  ADD COLUMN boss_portrait_set_key VARCHAR(80) NULL AFTER boss_gender,
  ADD COLUMN boss_role_code VARCHAR(80) NOT NULL DEFAULT 'leader' AFTER boss_portrait_set_key,
  ADD INDEX users_boss_character_stats_index (boss_role_code, boss_status, boss_alive);

UPDATE users
SET
  boss_shooting = GREATEST(boss_shooting, combat * 10),
  boss_driving = GREATEST(boss_driving, intelligence * 8),
  boss_stealth = GREATEST(boss_stealth, intelligence * 8),
  boss_intimidation = GREATEST(boss_intimidation, charisma * 8),
  boss_discipline = GREATEST(boss_discipline, leadership * 8),
  boss_street_knowledge = GREATEST(boss_street_knowledge, intelligence * 8),
  boss_endurance = GREATEST(boss_endurance, strength * 8),
  boss_role_code = 'leader'
WHERE boss_alive = 1;

ALTER TABLE crime_opportunity_assignments
  MODIFY COLUMN gang_member_id BIGINT UNSIGNED NULL,
  ADD COLUMN actor_type ENUM('boss','crew') NOT NULL DEFAULT 'crew' AFTER user_id,
  ADD COLUMN actor_id BIGINT UNSIGNED NULL AFTER actor_type,
  ADD INDEX crime_opportunity_assignments_actor_index (opportunity_id, actor_type, actor_id);

UPDATE crime_opportunity_assignments
SET actor_type = 'crew', actor_id = gang_member_id
WHERE actor_id IS NULL AND gang_member_id IS NOT NULL;

ALTER TABLE crime_run_assignments
  MODIFY COLUMN gang_member_id BIGINT UNSIGNED NULL,
  ADD COLUMN actor_type ENUM('boss','crew') NOT NULL DEFAULT 'crew' AFTER run_id,
  ADD COLUMN actor_id BIGINT UNSIGNED NULL AFTER actor_type,
  ADD INDEX crime_run_assignments_actor_index (run_id, actor_type, actor_id);

UPDATE crime_run_assignments
SET actor_type = 'crew', actor_id = gang_member_id
WHERE actor_id IS NULL AND gang_member_id IS NOT NULL;

ALTER TABLE quick_crime_run_crew
  MODIFY COLUMN gang_member_id BIGINT UNSIGNED NULL,
  ADD COLUMN actor_type ENUM('boss','crew') NOT NULL DEFAULT 'crew' AFTER user_id,
  ADD COLUMN actor_id BIGINT UNSIGNED NULL AFTER actor_type,
  ADD INDEX quick_crime_run_crew_actor_index (run_id, actor_type, actor_id);

UPDATE quick_crime_run_crew
SET actor_type = 'crew', actor_id = gang_member_id
WHERE actor_id IS NULL AND gang_member_id IS NOT NULL;

INSERT INTO update_notices (version, title, body, active, created_at, updated_at)
VALUES (
  '0.5.1',
  'v0.5.1 — Boss Character Integration',
  'The boss is now a full playable character in the crew system. You can see boss operational stats like driving, stealth, shooting, intimidation, discipline, street knowledge, and endurance from the Crew dossier. The boss can also be selected as an actor for discovered crimes and quick crimes, so personal actions use boss skills and create boss heat correctly.',
  1,
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  body = VALUES(body),
  active = VALUES(active),
  updated_at = NOW();
