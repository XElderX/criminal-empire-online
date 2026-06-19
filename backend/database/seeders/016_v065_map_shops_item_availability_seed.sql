-- Criminal Empire Online v0.6.5 — Map Shops & Item Availability Expansion seed

INSERT INTO item_definitions (
  code, name, category, equipment_slot, description, price, storage_units,
  max_durability, illegal, stackable, effects, requirements, active, created_at, updated_at
) VALUES
('flashlight', 'Flashlight', 'utility', 'utility', 'A plain flashlight for abstract night-work visibility checks.', 35, 1, 100, 0, 1, JSON_OBJECT('planning_bonus', 1, 'search_bonus', 2), JSON_OBJECT(), 1, NOW(), NOW()),
('backpack', 'Backpack', 'utility', 'bag', 'A generic backpack for extra carry capacity during small jobs.', 45, 1, 100, 0, 1, JSON_OBJECT('loot_capacity', 3), JSON_OBJECT(), 1, NOW(), NOW()),
('face_covering', 'Face Covering', 'clothing', 'clothing', 'Generic face covering that lowers witness recognition in abstract game checks.', 40, 1, 80, 0, 1, JSON_OBJECT('witness_risk', -2, 'heat_modifier', -1), JSON_OBJECT(), 1, NOW(), NOW()),
('burner_phone', 'Burner Phone', 'utility', 'utility', 'A disposable fictional phone used by contact and planning systems.', 90, 1, 100, 1, 1, JSON_OBJECT('communication_bonus', 3, 'contact_safety', 2), JSON_OBJECT(), 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  category = VALUES(category),
  equipment_slot = VALUES(equipment_slot),
  description = VALUES(description),
  price = VALUES(price),
  storage_units = VALUES(storage_units),
  max_durability = VALUES(max_durability),
  illegal = VALUES(illegal),
  stackable = VALUES(stackable),
  effects = VALUES(effects),
  requirements = VALUES(requirements),
  active = VALUES(active),
  updated_at = NOW();

INSERT INTO shops (
  slug, name, description, shop_type, world_region_id, world_location_id,
  requires_local_presence, can_view_remotely, is_black_market, is_legal, is_known,
  heat_risk, min_level, min_reputation, buys_categories_json, is_active, created_at, updated_at
) VALUES
('pawn_row_fence', 'Pawn Row Fence', 'A cramped second-hand counter that buys small loot and sells used starter tools.', 'pawn_fence', (SELECT region_id FROM world_locations WHERE slug='pawn-row' LIMIT 1), (SELECT id FROM world_locations WHERE slug='pawn-row' LIMIT 1), 1, 1, 0, 0, 1, 2, 1, 0, JSON_ARRAY('stolen_good','vehicle_part','tool','utility'), 1, NOW(), NOW()),
('market_tool_shop', 'Market Tool Shop', 'A legal market stall for basic tools, bags, and practical street supplies.', 'tool_shop', (SELECT region_id FROM world_locations WHERE slug='market-district' LIMIT 1), (SELECT id FROM world_locations WHERE slug='market-district' LIMIT 1), 1, 1, 0, 1, 1, 0, 1, 0, JSON_ARRAY('tool','utility'), 1, NOW(), NOW()),
('market_workwear_store', 'Workwear Store', 'A plain clothing shop for gloves, work uniforms, and low-profile clothing.', 'workwear', (SELECT region_id FROM world_locations WHERE slug='market-district' LIMIT 1), (SELECT id FROM world_locations WHERE slug='market-district' LIMIT 1), 1, 1, 0, 1, 1, 0, 1, 0, JSON_ARRAY('clothing'), 1, NOW(), NOW()),
('suburban_garage', 'Suburban Garage Counter', 'A local garage counter with vehicle tools, repair basics, and parts buyers.', 'auto_parts', (SELECT region_id FROM world_locations WHERE slug='suburban-garage' LIMIT 1), (SELECT id FROM world_locations WHERE slug='suburban-garage' LIMIT 1), 1, 1, 0, 1, 1, 1, 1, 0, JSON_ARRAY('vehicle_part','tool'), 1, NOW(), NOW()),
('rural_scrapyard_buyer', 'Rural Scrapyard Buyer', 'A muddy yard that buys vehicle parts and sometimes sells used tools.', 'scrapyard', (SELECT region_id FROM world_locations WHERE slug='junkyard-entrance' LIMIT 1), (SELECT id FROM world_locations WHERE slug='junkyard-entrance' LIMIT 1), 1, 1, 0, 0, 1, 2, 1, 0, JSON_ARRAY('vehicle_part','stolen_good','tool'), 1, NOW(), NOW()),
('medical_supply_counter', 'Medical Supply Counter', 'A clean counter for bandages and basic first-aid supplies.', 'medical', (SELECT region_id FROM world_locations WHERE slug='shopping-plaza' LIMIT 1), (SELECT id FROM world_locations WHERE slug='shopping-plaza' LIMIT 1), 1, 1, 0, 1, 1, 0, 1, 0, JSON_ARRAY('utility'), 1, NOW(), NOW()),
('smuggler_pier_dealer', 'Smuggler Pier Dealer', 'A risky pier contact. Most powerful inventory is intentionally disabled until a future dark-market update.', 'black_market', (SELECT region_id FROM world_locations WHERE slug='smuggler-pier' LIMIT 1), (SELECT id FROM world_locations WHERE slug='smuggler-pier' LIMIT 1), 1, 0, 1, 0, 0, 8, 1, 6, JSON_ARRAY('stolen_good','vehicle_part'), 1, NOW(), NOW()),
('basement_bar_contact', 'Basement Bar Contact', 'A social contact for future dealer introductions. Current stock is intentionally limited.', 'black_market_contact', (SELECT region_id FROM world_locations WHERE slug='basement-bars' LIMIT 1), (SELECT id FROM world_locations WHERE slug='basement-bars' LIMIT 1), 1, 0, 1, 0, 0, 5, 1, 3, JSON_ARRAY('stolen_good'), 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  shop_type = VALUES(shop_type),
  world_region_id = VALUES(world_region_id),
  world_location_id = VALUES(world_location_id),
  requires_local_presence = VALUES(requires_local_presence),
  can_view_remotely = VALUES(can_view_remotely),
  is_black_market = VALUES(is_black_market),
  is_legal = VALUES(is_legal),
  is_known = VALUES(is_known),
  heat_risk = VALUES(heat_risk),
  min_level = VALUES(min_level),
  min_reputation = VALUES(min_reputation),
  buys_categories_json = VALUES(buys_categories_json),
  is_active = VALUES(is_active),
  updated_at = NOW();

INSERT INTO shop_items (
  shop_id, item_key, asset_type, item_name, item_category, description, can_buy, can_sell,
  buy_price, sell_price_multiplier, stock_quantity, max_stock, restock_interval_minutes,
  min_level, min_reputation, heat_risk, is_enabled, availability_status, disabled_reason, created_at, updated_at
) VALUES
((SELECT id FROM shops WHERE slug='market_tool_shop'), 'work_gloves', 'item', 'Work Gloves', 'clothing', 'Basic gloves for evidence-risk reduction.', 1, 1, 25, 0.45, 8, 8, 720, 1, 0, 0, 1, 'legal', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='market_tool_shop'), 'screwdriver_set', 'item', 'Screwdriver Set', 'tool', 'Starter generic tool set.', 1, 1, 95, 0.45, 6, 6, 720, 1, 0, 0, 1, 'legal', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='market_tool_shop'), 'crowbar', 'item', 'Crowbar', 'tool', 'Noisy but useful generic pry tool.', 1, 1, 95, 0.45, 4, 4, 720, 1, 0, 1, 1, 'starter_shady', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='market_tool_shop'), 'flashlight', 'item', 'Flashlight', 'utility', 'Plain flashlight for night work.', 1, 1, 35, 0.45, 8, 8, 720, 1, 0, 0, 1, 'legal', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='market_tool_shop'), 'duffel_bag', 'item', 'Duffel Bag', 'utility', 'Plain bag for small haul capacity.', 1, 1, 120, 0.45, 5, 5, 720, 1, 0, 0, 1, 'legal', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='market_tool_shop'), 'backpack', 'item', 'Backpack', 'utility', 'Generic backpack.', 1, 1, 45, 0.45, 8, 8, 720, 1, 0, 0, 1, 'legal', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='market_workwear_store'), 'work_gloves', 'item', 'Work Gloves', 'clothing', 'Basic gloves.', 1, 1, 25, 0.45, 8, 8, 720, 1, 0, 0, 1, 'legal', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='market_workwear_store'), 'dark_clothing', 'item', 'Dark Clothing', 'clothing', 'Low-profile clothing.', 1, 1, 90, 0.45, 6, 6, 720, 1, 0, 0, 1, 'legal', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='market_workwear_store'), 'work_uniform', 'item', 'Work Uniform', 'clothing', 'Plain work uniform.', 1, 1, 180, 0.45, 4, 4, 1440, 1, 1, 0, 1, 'legal', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='market_workwear_store'), 'face_covering', 'item', 'Face Covering', 'clothing', 'Generic face covering.', 1, 1, 40, 0.45, 6, 6, 720, 1, 0, 0, 1, 'legal', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='pawn_row_fence'), 'lockpick_set', 'item', 'Lockpick Set', 'tool', 'Shady starter tool.', 1, 1, 140, 0.45, 3, 3, 1440, 1, 0, 1, 1, 'starter_shady', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='pawn_row_fence'), 'glass_breaker', 'item', 'Glass Breaker', 'tool', 'Generic emergency tool with suspicious use.', 1, 1, 130, 0.45, 2, 2, 1440, 1, 0, 1, 1, 'starter_shady', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='pawn_row_fence'), 'burner_phone', 'item', 'Burner Phone', 'utility', 'Disposable fictional phone.', 1, 1, 90, 0.45, 3, 3, 1440, 1, 0, 1, 1, 'starter_shady', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='pawn_row_fence'), 'cheap_knife', 'item', 'Street Knife', 'melee_weapon', 'Low-tier fictional melee item.', 1, 1, 80, 0.45, 2, 2, 1440, 1, 0, 1, 1, 'starter_shady', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='suburban_garage'), 'vehicle_tools', 'item', 'Vehicle Tools', 'tool', 'Generic vehicle tools.', 1, 1, 260, 0.45, 3, 3, 1440, 1, 1, 1, 1, 'restricted', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='suburban_garage'), 'toolbox', 'item', 'Toolbox', 'tool', 'General-purpose tool case.', 1, 1, 140, 0.45, 4, 4, 720, 1, 0, 0, 1, 'legal', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='rural_scrapyard_buyer'), 'vehicle_tools', 'item', 'Vehicle Tools', 'tool', 'Used vehicle tools.', 1, 1, 240, 0.45, 2, 2, 1440, 1, 1, 1, 1, 'restricted', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='rural_scrapyard_buyer'), 'screwdriver_set', 'item', 'Screwdriver Set', 'tool', 'Used screwdriver set.', 1, 1, 75, 0.45, 2, 2, 1440, 1, 0, 0, 1, 'legal', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='medical_supply_counter'), 'first_aid_kit', 'item', 'First-Aid Kit', 'utility', 'Generic emergency kit.', 1, 1, 140, 0.45, 5, 5, 720, 1, 0, 0, 1, 'legal', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='medical_supply_counter'), 'bandages', 'item', 'Bandages', 'utility', 'Basic field bandages.', 1, 1, 20, 0.45, 12, 12, 720, 1, 0, 0, 1, 'legal', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='basement_bar_contact'), 'basic_vest', 'item', 'Basic Protective Vest', 'armor', 'Restricted protective gear.', 1, 1, 450, 0.45, 1, 1, 2880, 1, 5, 2, 1, 'restricted', NULL, NOW(), NOW()),
((SELECT id FROM shops WHERE slug='smuggler_pier_dealer'), 'basic_pistol', 'weapon', 'Basic Pistol', 'weapon', 'Disabled by config; future dealer-only firearm stock.', 0, 0, 1200, 0.30, 0, 0, 4320, 1, 8, 6, 0, 'black_market_only', 'black_market_only', NOW(), NOW()),
((SELECT id FROM shops WHERE slug='smuggler_pier_dealer'), 'pump_shotgun', 'weapon', 'Pump Shotgun', 'weapon', 'Disabled until future dark-market expansion.', 0, 0, 12000, 0.30, 0, 0, 10080, 1, 12, 10, 0, 'future_only', 'future_dark_market_expansion', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  asset_type = VALUES(asset_type),
  item_name = VALUES(item_name),
  item_category = VALUES(item_category),
  description = VALUES(description),
  can_buy = VALUES(can_buy),
  can_sell = VALUES(can_sell),
  buy_price = VALUES(buy_price),
  sell_price_multiplier = VALUES(sell_price_multiplier),
  max_stock = VALUES(max_stock),
  restock_interval_minutes = VALUES(restock_interval_minutes),
  min_level = VALUES(min_level),
  min_reputation = VALUES(min_reputation),
  heat_risk = VALUES(heat_risk),
  is_enabled = VALUES(is_enabled),
  availability_status = VALUES(availability_status),
  disabled_reason = VALUES(disabled_reason),
  updated_at = NOW();

INSERT INTO map_activity_links (world_location_id, feature_type, feature_key, label, route_hint, min_level, is_active, sort_order, created_at, updated_at) VALUES
((SELECT id FROM world_locations WHERE slug='pawn-row'), 'shops', 'pawn_row_fence', 'Pawn Row Fence', 'shops', 1, 1, 212, NOW(), NOW()),
((SELECT id FROM world_locations WHERE slug='market-district'), 'shops', 'market_tool_shop', 'Market Tool Shop', 'shops', 1, 1, 91, NOW(), NOW()),
((SELECT id FROM world_locations WHERE slug='market-district'), 'shops', 'market_workwear_store', 'Workwear Store', 'shops', 1, 1, 92, NOW(), NOW()),
((SELECT id FROM world_locations WHERE slug='suburban-garage'), 'shops', 'suburban_garage', 'Suburban Garage Counter', 'shops', 1, 1, 991, NOW(), NOW()),
((SELECT id FROM world_locations WHERE slug='junkyard-entrance'), 'shops', 'rural_scrapyard_buyer', 'Rural Scrapyard Buyer', 'shops', 1, 1, 1271, NOW(), NOW()),
((SELECT id FROM world_locations WHERE slug='shopping-plaza'), 'shops', 'medical_supply_counter', 'Medical Supply Counter', 'shops', 1, 1, 932, NOW(), NOW()),
((SELECT id FROM world_locations WHERE slug='smuggler-pier'), 'shops', 'smuggler_pier_dealer', 'Smuggler Pier Dealer', 'shops', 1, 1, 592, NOW(), NOW()),
((SELECT id FROM world_locations WHERE slug='basement-bars'), 'shops', 'basement_bar_contact', 'Basement Bar Contact', 'shops', 1, 1, 1132, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  label = VALUES(label),
  route_hint = VALUES(route_hint),
  is_active = VALUES(is_active),
  updated_at = NOW();

INSERT INTO guide_sections (section_key, title, body, sort_order, is_active, created_at, updated_at) VALUES
('shops', 'Map Shops & Item Availability', 'Inventory is for owned items and crew loadouts. Travel to tool shops, workwear stores, garages, medical counters, pawn fences, and future black-market contacts to buy or sell. Shop configuration controls whether items are legal, restricted, black-market-only, future-only, or disabled.', 75, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body), sort_order=VALUES(sort_order), is_active=VALUES(is_active), updated_at=NOW();

INSERT INTO help_tips (tip_key, page_key, title, body, guide_section_key, sort_order, is_active, created_at, updated_at) VALUES
('shops', 'shops', 'Map Shops', 'Browse known shops remotely, then travel to the hotspot to buy or sell. Inventory no longer acts as a global shop.', 'shops', 10, 1, NOW(), NOW()),
('equipment_shops', 'equipment', 'Finding Gear', 'Need gear? Open Shops or the World Map. Items are bought from local shops, fences, garages, medical counters, or future black-market contacts.', 'shops', 15, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE page_key=VALUES(page_key), title=VALUES(title), body=VALUES(body), guide_section_key=VALUES(guide_section_key), sort_order=VALUES(sort_order), is_active=VALUES(is_active), updated_at=NOW();
