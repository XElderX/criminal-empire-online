-- v0.7.4.2 Recruitment Identity Diversity Hotfix
-- Refreshes still-available generated recruit identities so the recruitment page
-- no longer shows repeated names/portraits from the original tiny fallback pool.

UPDATE npcs npc
JOIN recruitment_candidates candidate
  ON candidate.npc_id = npc.id
 AND candidate.status = 'available'
LEFT JOIN player_gang_members member
  ON member.npc_id = npc.id
 AND member.status <> 'dismissed'
JOIN (
  SELECT ranked.id, ranked.rn
  FROM (
    SELECT
      npc2.id,
      (@recruit_diversity_row := @recruit_diversity_row + 1) AS rn
    FROM (SELECT @recruit_diversity_row := 0) vars
    CROSS JOIN npcs npc2
    JOIN recruitment_candidates candidate2
      ON candidate2.npc_id = npc2.id
     AND candidate2.status = 'available'
    LEFT JOIN player_gang_members member2
      ON member2.npc_id = npc2.id
     AND member2.status <> 'dismissed'
    WHERE npc2.source_event = 'world_recruit_refresh'
      AND npc2.role = 'recruit'
      AND npc2.alive = 1
      AND member2.id IS NULL
    ORDER BY npc2.id
  ) ranked
) numbered
  ON numbered.id = npc.id
JOIN (
  SELECT 1 AS profile_index, 'Darius' AS first_name, 'Venn' AS last_name, 'Axle' AS nickname, 'male' AS gender, 'Garage hand' AS occupation
  UNION ALL SELECT 2, 'Lena', 'Cross', 'Quiet Hand', 'female', 'Warehouse temp'
  UNION ALL SELECT 3, 'Marcus', 'Rook', 'Lockjaw', 'male', 'Dock runner'
  UNION ALL SELECT 4, 'Mara', 'Vale', 'Red Line', 'female', 'Market courier'
  UNION ALL SELECT 5, 'Viktor', 'Marlow', 'Cold Deck', 'male', 'Bar lookout'
  UNION ALL SELECT 6, 'Irena', 'Dusk', 'Soft Step', 'female', 'Night-shift porter'
  UNION ALL SELECT 7, 'Tomas', 'Kade', 'Sparks', 'male', 'Street mechanic'
  UNION ALL SELECT 8, 'Sofia', 'Harlow', 'Blue Note', 'female', 'Club door watcher'
  UNION ALL SELECT 9, 'Rafi', 'Sable', 'Patch', 'male', 'Scrapyard helper'
  UNION ALL SELECT 10, 'Dana', 'Locke', 'Moth', 'female', 'Unemployed local'
  UNION ALL SELECT 11, 'Owen', 'Mercer', 'Roadsign', 'male', 'Garage hand'
  UNION ALL SELECT 12, 'Vera', 'Dray', 'Needle', 'female', 'Dock runner'
  UNION ALL SELECT 13, 'Caleb', 'Volk', 'Northside', 'male', 'Warehouse temp'
  UNION ALL SELECT 14, 'Amara', 'Stone', 'Switch', 'female', 'Market courier'
  UNION ALL SELECT 15, 'Roman', 'Wex', 'Gravel', 'male', 'Scrapyard helper'
  UNION ALL SELECT 16, 'Kira', 'Calder', 'Ghost Light', 'female', 'Night-shift porter'
  UNION ALL SELECT 17, 'Elias', 'Frost', 'Latch', 'male', 'Bar lookout'
  UNION ALL SELECT 18, 'Nadia', 'Quinn', 'Iron Luck', 'female', 'Club door watcher'
  UNION ALL SELECT 19, 'Milo', 'Bishop', 'Noon Smoke', 'male', 'Unemployed local'
  UNION ALL SELECT 20, 'Zara', 'Arden', 'Side Street', 'female', 'Street mechanic'
  UNION ALL SELECT 21, 'Jonas', 'Moss', 'Quickstep', 'male', 'Dock runner'
  UNION ALL SELECT 22, 'Talia', 'Ridge', 'Night Shift', 'female', 'Warehouse temp'
  UNION ALL SELECT 23, 'Niko', 'Holt', 'Crowbar', 'male', 'Scrapyard helper'
  UNION ALL SELECT 24, 'Petra', 'Slate', 'Back Alley', 'female', 'Market courier'
) profile
  ON profile.profile_index = MOD(numbered.rn - 1, 24) + 1
SET
  npc.first_name = profile.first_name,
  npc.last_name = profile.last_name,
  npc.nickname = profile.nickname,
  npc.gender = profile.gender,
  npc.occupation = profile.occupation,
  npc.biography = 'A locally generated recruitable NPC refreshed by v0.7.4.2 identity diversity balancing.',
  npc.portrait_set_key = NULL,
  npc.portrait_stage_cache = NULL,
  npc.portrait_focal_x = 50,
  npc.portrait_focal_y = 42,
  npc.updated_at = NOW()
WHERE npc.source_event = 'world_recruit_refresh'
  AND npc.role = 'recruit'
  AND npc.alive = 1
  AND member.id IS NULL;
