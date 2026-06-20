-- Criminal Empire Online v0.7.2.1 — Boss Loadout Schema Fix

ALTER TABLE crew_equipment
  MODIFY COLUMN gang_member_id BIGINT UNSIGNED NULL;
