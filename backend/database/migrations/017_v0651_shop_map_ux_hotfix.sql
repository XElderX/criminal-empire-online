-- Criminal Empire Online v0.6.5.1 — Map Shop UX & Navigation Hotfix
-- Patch-only releases should not reopen the world tutorial for players who already completed the v0.6.4 guide/update.

UPDATE user_tutorial_progress
SET
  tutorial_key = 'new_player_world_guide',
  tutorial_version = '0.6.5',
  status = 'completed',
  current_step_code = 'completed',
  completed_update_tutorial_versions = JSON_ARRAY('0.6.4', '0.6.5'),
  completed_at = COALESCE(completed_at, NOW()),
  updated_at = NOW()
WHERE tutorial_key = 'world_systems_update'
  AND status = 'active'
  AND completed_tutorial_version IN ('0.6.4', '0.6.5');
