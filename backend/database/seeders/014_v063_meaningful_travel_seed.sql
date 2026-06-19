-- Criminal Empire Online v0.6.3 — Meaningful Travel & Local Presence seed data

UPDATE world_locations
SET
  travel_requires_level = min_level,
  travel_requires_reputation = 0,
  travel_risk_level = GREATEST(heat_level, police_pressure, danger_level),
  travel_event_profile = CASE
    WHEN location_type = 'police' THEN 'police_heavy'
    WHEN location_type IN ('warehouse','docks','black_market') THEN 'cargo_underworld'
    WHEN location_type IN ('garage','travel') THEN 'vehicle_routes'
    WHEN location_type IN ('recruitment','nightlife') THEN 'contacts'
    ELSE 'street'
  END,
  local_presence_required_default = 1,
  exploration_energy_cost = 3,
  exploration_cooldown_seconds = 600,
  updated_at = NOW();

INSERT INTO travel_event_templates (slug, title, description, event_type, min_danger, min_police_pressure, min_heat, route_type, weight, effects_json, is_active, created_at, updated_at) VALUES
('overheard-local-rumor', 'Overheard Local Rumor', 'A passing conversation gives the boss a useful piece of local context.', 'rumor', 0, 0, 0, NULL, 40, JSON_OBJECT('opportunity_type','rumor'), 1, NOW(), NOW()),
('spotted-police-patrol', 'Patrol Pattern Noticed', 'You spot a patrol rhythm near the destination. It is not a reward by itself, but it makes the hotspot feel readable.', 'patrol_pattern', 0, 40, 0, NULL, 25, JSON_OBJECT('heat_delta',0), 1, NOW(), NOW()),
('met-nervous-contact', 'Nervous Contact', 'A local contact recognizes the boss but waits for lower pressure before talking openly.', 'recruitment_lead', 20, 20, 0, NULL, 18, JSON_OBJECT('opportunity_type','recruitment_lead'), 1, NOW(), NOW()),
('route-delay', 'Route Delay', 'Traffic and watchful streets slow the route. Nothing serious happens, but the city pushes back.', 'route_delay', 20, 20, 0, 'cheap', 16, JSON_OBJECT('delay',true), 1, NOW(), NOW()),
('police-checkpoint-warning', 'Checkpoint Delay', 'A police checkpoint slows the route and adds a little attention.', 'police_checkpoint', 0, 65, 30, NULL, 25, JSON_OBJECT('heat_delta',2), 1, NOW(), NOW()),
('rival-gang-presence', 'Rival Presence', 'A rival crew is visible near the destination. The boss keeps distance for now.', 'rival_presence', 50, 0, 25, NULL, 18, JSON_OBJECT('danger_warning',true), 1, NOW(), NOW()),
('found-quick-crime-target', 'Small Local Opening', 'You notice a small fictional target nearby. Local quick actions may be worth checking.', 'quick_crime_target', 0, 0, 0, NULL, 30, JSON_OBJECT('opportunity_type','quick_crime_target'), 1, NOW(), NOW()),
('found-dirty-job-lead', 'Local Work Rumor', 'Someone mentions quiet work around this hotspot. A local dirty job lead is added.', 'dirty_job_lead', 20, 0, 0, NULL, 22, JSON_OBJECT('opportunity_type','dirty_job_lead'), 1, NOW(), NOW()),
('found-recruitment-lead', 'Recruitment Lead', 'A name comes up in conversation. This place may help find useful crew.', 'recruitment_lead', 0, 0, 0, NULL, 22, JSON_OBJECT('opportunity_type','recruitment_lead'), 1, NOW(), NOW()),
('discovered-shortcut', 'Shortcut Discovered', 'You learn a quieter way through nearby streets. Familiarity with this hotspot improves.', 'shortcut', 0, 0, 0, NULL, 12, JSON_OBJECT('familiarity',1), 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  title=VALUES(title), description=VALUES(description), event_type=VALUES(event_type), min_danger=VALUES(min_danger),
  min_police_pressure=VALUES(min_police_pressure), min_heat=VALUES(min_heat), route_type=VALUES(route_type), weight=VALUES(weight),
  effects_json=VALUES(effects_json), is_active=VALUES(is_active), updated_at=NOW();

UPDATE quick_crime_location_rules rule
JOIN world_locations location ON location.id = rule.world_location_id
JOIN world_regions region ON region.id = location.region_id
SET
  rule.requires_current_location = 1,
  rule.local_presence_required_message = CONCAT('Travel to ', region.name, ' / ', location.name, ' before starting this local quick crime.'),
  rule.remote_preview_allowed = 1,
  rule.travel_hint = CONCAT('Travel here to use ', location.name, ' risk and reward modifiers.'),
  rule.updated_at = NOW()
WHERE location.slug IN ('container-yard','smuggler-pier','parking-lots','scrapyard','basement-bars','workers-bar','police-district','warehouses','loading-bays','suburban-garage','highway-rest-stop','gas-station');

UPDATE dirty_job_location_rules rule
JOIN world_locations location ON location.id = rule.world_location_id
JOIN world_regions region ON region.id = location.region_id
SET
  rule.requires_current_location = 1,
  rule.requires_presence_at_source = 1,
  rule.requires_presence_at_target = 0,
  rule.travel_required_before_accept = 1,
  rule.travel_required_before_execute = 1,
  rule.local_presence_required_message = CONCAT('Travel to ', region.name, ' / ', location.name, ' before accepting or executing this local Dirty Job.'),
  rule.updated_at = NOW()
WHERE location.slug IN ('container-yard','smuggler-pier','parking-lots','scrapyard','basement-bars','workers-bar','warehouses','loading-bays','suburban-garage','highway-rest-stop','gas-station');

INSERT INTO map_activity_links (world_location_id, feature_type, feature_key, label, route_hint, min_level, is_active, sort_order, created_at, updated_at)
SELECT
  location.id,
  seeded.feature_type,
  seeded.feature_key,
  seeded.label,
  CASE
    WHEN seeded.route_base LIKE '%?%' THEN CONCAT(seeded.route_base, '&region=', region.slug, '&location=', location.slug)
    WHEN seeded.route_base IN ('world map') THEN seeded.route_base
    ELSE CONCAT(seeded.route_base, '?region=', region.slug, '&location=', location.slug)
  END AS route_hint,
  seeded.min_level,
  1,
  seeded.sort_order,
  NOW(),
  NOW()
FROM world_locations location
JOIN world_regions region ON region.id = location.region_id
JOIN (
  SELECT 'container-yard' AS location_slug, 'quick_crimes' AS feature_type, 'quick_crimes' AS feature_key, 'Local Cargo Quick Crimes' AS label, 'crimes?tab=quick_crimes' AS route_base, 1 AS min_level, 10 AS sort_order UNION ALL
  SELECT 'container-yard','dirty_jobs','dirty_jobs','Dockside Dirty Jobs','dirty jobs',2,20 UNION ALL
  SELECT 'workers-bar','recruitment','recruitment','Meet Local Workers','recruitment',1,10 UNION ALL
  SELECT 'workers-bar','dirty_jobs','dirty_jobs','Back-Room Work Rumors','dirty jobs',1,20 UNION ALL
  SELECT 'parking-lots','quick_crimes','quick_crimes','Parking Lot Quick Crimes','crimes?tab=quick_crimes',1,10 UNION ALL
  SELECT 'scrapyard','quick_crimes','quick_crimes','Vehicle Parts Quick Crimes','crimes?tab=quick_crimes',1,10 UNION ALL
  SELECT 'scrapyard','recruitment','recruitment','Mechanic Recruit Leads','recruitment',1,20 UNION ALL
  SELECT 'basement-bars','recruitment','recruitment','Basement Bar Contacts','recruitment',1,10 UNION ALL
  SELECT 'police-district','heat','heat','Legal / Heat Pressure Hooks','heat',1,10 UNION ALL
  SELECT 'warehouses','dirty_jobs','dirty_jobs','Warehouse Pickup Jobs','dirty jobs',2,10
) seeded ON seeded.location_slug = location.slug
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  route_hint = VALUES(route_hint),
  min_level = VALUES(min_level),
  is_active = 1,
  sort_order = VALUES(sort_order),
  updated_at = NOW();

UPDATE map_activity_links link
JOIN world_locations location ON location.id = link.world_location_id
JOIN world_regions region ON region.id = location.region_id
SET link.route_hint = CASE
  WHEN link.feature_type IN ('quick_crimes','quick_crime','crimes') THEN CONCAT('crimes?tab=quick_crimes&region=', region.slug, '&location=', location.slug)
  WHEN link.feature_type = 'dirty_jobs' THEN CONCAT('dirty jobs?region=', region.slug, '&location=', location.slug)
  WHEN link.feature_type = 'recruitment' THEN CONCAT('recruitment?region=', region.slug, '&location=', location.slug)
  WHEN link.feature_type IN ('businesses','territories') THEN CONCAT('territories?region=', region.slug, '&location=', location.slug)
  ELSE link.route_hint
END
WHERE link.is_active = 1;

INSERT INTO user_location_presence (user_id, world_region_id, world_location_id, visits_count, last_visited_at, familiarity_score, created_at, updated_at)
SELECT users.id, state.current_region_id, state.current_location_id, 1, COALESCE(state.arrived_at, state.updated_at, NOW()), 1, NOW(), NOW()
FROM users
JOIN user_location_state state ON state.user_id = users.id
WHERE state.current_region_id IS NOT NULL
  AND state.current_location_id IS NOT NULL
ON DUPLICATE KEY UPDATE updated_at = NOW();

INSERT INTO update_notices (version, title, body, active, created_at, updated_at)
SELECT '0.6.3', 'v0.6.3 — Meaningful Travel & Local Presence', 'Travel now unlocks local actions, creates arrival events, records travel history, and enforces local presence for selected Quick Crimes and Dirty Jobs.', 1, NOW(), NOW()
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'update_notices')
  AND NOT EXISTS (SELECT 1 FROM update_notices WHERE version = '0.6.3');
