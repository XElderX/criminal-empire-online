-- Criminal Empire Online v0.6.1 — Map Gameplay Integration seed data

INSERT INTO quick_crime_location_rules (
  quick_crime_template_id, world_region_id, world_location_id, is_allowed, requires_current_location,
  reward_multiplier, heat_multiplier, police_risk_multiplier, danger_multiplier, sort_order, created_at, updated_at
)
SELECT q.id, r.id, l.id, 1, 1, rule.reward_multiplier, rule.heat_multiplier, rule.police_risk_multiplier, rule.danger_multiplier, rule.sort_order, NOW(), NOW()
FROM (
  SELECT 'ask_around_rumors' AS quick_code, 'main-city' AS region_slug, 'slums' AS location_slug, 1.00 AS reward_multiplier, 0.90 AS heat_multiplier, 1.00 AS police_risk_multiplier, 1.00 AS danger_multiplier, 10 AS sort_order UNION ALL
  SELECT 'pickpocket_pedestrian','main-city','slums',0.95,1.05,1.05,1.05,20 UNION ALL
  SELECT 'shoplift_small_goods','main-city','slums',0.90,1.05,1.05,1.00,30 UNION ALL
  SELECT 'shoplift_small_goods','main-city','market-district',1.12,1.10,1.15,1.00,10 UNION ALL
  SELECT 'pickpocket_pedestrian','main-city','market-district',1.15,1.05,1.10,1.00,20 UNION ALL
  SELECT 'watch_target_street','main-city','market-district',1.05,0.95,1.00,0.95,30 UNION ALL
  SELECT 'pickpocket_pedestrian','main-city','nightlife-district',1.18,1.12,1.10,1.10,10 UNION ALL
  SELECT 'ask_around_rumors','main-city','nightlife-district',1.10,1.00,1.00,1.10,20 UNION ALL
  SELECT 'break_parked_car','main-city','nightlife-district',1.10,1.15,1.10,1.15,30 UNION ALL
  SELECT 'ask_around_rumors','main-city','police-district',0.75,1.35,1.60,0.90,10 UNION ALL
  SELECT 'watch_target_street','main-city','police-district',0.80,1.30,1.65,0.90,20 UNION ALL
  SELECT 'small_warehouse_sneak_in','docks','container-yard',1.18,1.20,1.25,1.20,10 UNION ALL
  SELECT 'watch_target_street','docks','container-yard',1.05,1.10,1.15,1.10,20 UNION ALL
  SELECT 'ask_around_rumors','docks','smuggler-pier',1.10,1.15,1.20,1.25,10 UNION ALL
  SELECT 'shoplift_small_goods','docks','smuggler-pier',1.20,1.20,1.15,1.25,20 UNION ALL
  SELECT 'small_warehouse_sneak_in','industrial-zone','warehouses',1.25,1.15,1.10,1.15,10 UNION ALL
  SELECT 'steal_bicycle_parts','industrial-zone','warehouses',1.15,1.05,1.00,1.10,20 UNION ALL
  SELECT 'steal_car_lamps','industrial-zone','rail-yard',1.12,1.20,1.25,1.20,10 UNION ALL
  SELECT 'watch_target_street','industrial-zone','rail-yard',1.05,1.10,1.25,1.10,20 UNION ALL
  SELECT 'break_parked_car','suburbs','parking-lots',1.18,1.12,1.15,0.95,10 UNION ALL
  SELECT 'steal_car_lamps','suburbs','parking-lots',1.20,1.10,1.10,0.95,20 UNION ALL
  SELECT 'watch_target_street','suburbs','private-estates',1.18,1.20,1.30,1.05,10 UNION ALL
  SELECT 'break_parked_car','suburbs','private-estates',1.35,1.25,1.35,1.10,20 UNION ALL
  SELECT 'store_robbery','rural-county','gas-station',1.05,1.10,0.90,1.15,10 UNION ALL
  SELECT 'ask_around_rumors','rural-county','gas-station',1.00,0.85,0.80,1.05,20 UNION ALL
  SELECT 'low_value_vehicle_theft','rural-county','scrapyard',1.20,0.95,0.85,1.15,10 UNION ALL
  SELECT 'steal_bicycle_parts','rural-county','scrapyard',1.12,0.85,0.80,1.10,20 UNION ALL
  SELECT 'ask_around_rumors','forest-hills','abandoned-cabin',1.15,0.75,0.70,1.35,10 UNION ALL
  SELECT 'watch_target_street','forest-hills','abandoned-cabin',1.10,0.75,0.70,1.30,20 UNION ALL
  SELECT 'ask_around_rumors','shore-beach-sea','marina',1.15,1.05,1.10,1.10,10 UNION ALL
  SELECT 'watch_target_street','shore-beach-sea','marina',1.10,1.05,1.10,1.05,20 UNION ALL
  SELECT 'ask_around_rumors','old-town','basement-bars',1.20,0.95,1.00,1.10,10 UNION ALL
  SELECT 'watch_target_street','old-town','basement-bars',1.05,0.95,1.00,1.05,20 UNION ALL
  SELECT 'low_value_vehicle_theft','highway-outskirts','highway-rest-stop',1.15,1.05,0.90,1.20,10 UNION ALL
  SELECT 'watch_target_street','highway-outskirts','highway-rest-stop',1.05,0.90,0.85,1.10,20
) AS rule
JOIN quick_crime_templates q ON q.code = rule.quick_code
JOIN world_regions r ON r.slug = rule.region_slug
JOIN world_locations l ON l.region_id = r.id AND l.slug = rule.location_slug
ON DUPLICATE KEY UPDATE
  reward_multiplier = VALUES(reward_multiplier), heat_multiplier = VALUES(heat_multiplier),
  police_risk_multiplier = VALUES(police_risk_multiplier), danger_multiplier = VALUES(danger_multiplier),
  requires_current_location = VALUES(requires_current_location), sort_order = VALUES(sort_order), updated_at = NOW();

INSERT INTO dirty_job_location_rules (
  dirty_job_template_id, world_region_id, world_location_id, requires_current_location,
  reward_multiplier, heat_multiplier, police_risk_multiplier, danger_multiplier, sort_order, created_at, updated_at
)
SELECT d.id, r.id, l.id, 0, rule.reward_multiplier, rule.heat_multiplier, rule.police_risk_multiplier, rule.danger_multiplier, rule.sort_order, NOW(), NOW()
FROM (
  SELECT 'steal_car_lamps' AS dirty_code, 'suburbs' AS region_slug, 'parking-lots' AS location_slug, 1.20 AS reward_multiplier, 1.10 AS heat_multiplier, 1.10 AS police_risk_multiplier, 0.95 AS danger_multiplier, 10 AS sort_order UNION ALL
  SELECT 'shoplift_electronics','main-city','market-district',1.15,1.10,1.15,1.00,10 UNION ALL
  SELECT 'apartment_burglary','suburbs','private-estates',1.30,1.25,1.35,1.10,10 UNION ALL
  SELECT 'garage_parts_raid','rural-county','scrapyard',1.18,0.95,0.85,1.15,10 UNION ALL
  SELECT 'steal_delivery_van','highway-outskirts','highway-rest-stop',1.15,1.05,0.90,1.20,10 UNION ALL
  SELECT 'collect_rival_payment','old-town','basement-bars',1.08,1.05,1.00,1.20,10 UNION ALL
  SELECT 'warehouse_grow_cycle','industrial-zone','warehouses',1.20,1.10,1.10,1.15,10 UNION ALL
  SELECT 'garage_parts_raid','industrial-zone','warehouses',1.15,1.10,1.10,1.15,20 UNION ALL
  SELECT 'shoplift_electronics','shore-beach-sea','marina',1.12,1.08,1.10,1.05,20
) AS rule
JOIN dirty_job_templates d ON d.code = rule.dirty_code
JOIN world_regions r ON r.slug = rule.region_slug
JOIN world_locations l ON l.region_id = r.id AND l.slug = rule.location_slug
ON DUPLICATE KEY UPDATE
  reward_multiplier = VALUES(reward_multiplier), heat_multiplier = VALUES(heat_multiplier),
  police_risk_multiplier = VALUES(police_risk_multiplier), danger_multiplier = VALUES(danger_multiplier),
  sort_order = VALUES(sort_order), updated_at = NOW();

UPDATE map_activity_links link
JOIN world_locations location ON location.id = link.world_location_id
JOIN world_regions region ON region.id = location.region_id
SET
  link.route_hint = CASE
    WHEN link.feature_type IN ('quick_crimes','quick_crime','crimes') THEN CONCAT('crimes?tab=quick_crimes&region=', region.slug, '&location=', location.slug)
    WHEN link.feature_type = 'dirty_jobs' THEN CONCAT('dirty jobs?region=', region.slug, '&location=', location.slug)
    WHEN link.feature_type = 'recruitment' THEN CONCAT('recruitment?region=', region.slug, '&location=', location.slug)
    WHEN link.feature_type IN ('businesses','territories') THEN CONCAT('territories?region=', region.slug, '&location=', location.slug)
    ELSE link.route_hint
  END
WHERE link.is_active = 1;

INSERT INTO update_notices (version, title, body, active, created_at, updated_at)
SELECT '0.6.1', 'v0.6.1 — Map Gameplay Integration', 'Map hotspots now show real local activity previews, Quick Crimes and Dirty Jobs can filter by region/hotspot, and exploring a hotspot can reveal local opportunities.', 1, NOW(), NOW()
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'update_notices')
  AND NOT EXISTS (SELECT 1 FROM update_notices WHERE version = '0.6.1');
