-- Criminal Empire Online v0.6.1.2 — Dirty Job Boss Support

ALTER TABLE dirty_job_assignments
  MODIFY COLUMN gang_member_id BIGINT UNSIGNED NULL,
  ADD COLUMN actor_type ENUM('boss','crew') NOT NULL DEFAULT 'crew' AFTER dirty_job_run_id,
  ADD COLUMN actor_id BIGINT UNSIGNED NULL AFTER actor_type,
  ADD INDEX dirty_job_assignments_actor_index (dirty_job_run_id, actor_type, actor_id);

UPDATE dirty_job_assignments
SET actor_type = 'crew', actor_id = gang_member_id
WHERE actor_id IS NULL AND gang_member_id IS NOT NULL;

INSERT INTO update_notices (version, title, body, active, created_at, updated_at)
VALUES (
  '0.6.1.2',
  'v0.6.1.2 — Dirty Job Boss Support',
  'The boss can now be assigned into Dirty Jobs alongside crew members. Dirty Job role assignment, validation, and outcome handling now recognize the boss as a real actor.',
  1,
  NOW(),
  NOW()
)
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  body = VALUES(body),
  active = VALUES(active),
  updated_at = NOW();
