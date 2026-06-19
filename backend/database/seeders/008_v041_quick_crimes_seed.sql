-- Criminal Empire Online v0.4.1 — Fallback Street Actions & Quick Crimes seed data

INSERT INTO quick_crime_templates (
  code, title, category, description, tier, min_level, energy_cost, max_heat,
  cooldown_seconds, district_cooldown_seconds, base_success_rate, base_event_chance, base_disaster_chance,
  reward_min, reward_max, heat_min, heat_max, xp_min, xp_max,
  required_all_item_tags, required_any_item_tags, recommended_item_tags,
  required_crew_count, recommended_crew_roles, relevant_stats, preparation_options, event_pool, loot_table,
  active, created_at, updated_at
) VALUES
('ask_around_rumors', 'Ask Around for Rumors', 'street_action', 'Spend time listening for weak leads and small openings around the city.', 1, 1, 3, NULL, 120, 0, 78, 12, 1, 0, 35, 0, 1, 3, 8, JSON_ARRAY(), JSON_ARRAY(), JSON_ARRAY('communication','street_knowledge'), 0, JSON_ARRAY(), JSON_ARRAY('intelligence','street_knowledge'), JSON_ARRAY(
  JSON_OBJECT('code','buy_coffee','name','Buy a cheap round','description','Spend a little cash to keep people talking.', 'cash_cost', 10, 'energy_cost', 0, 'effects', JSON_OBJECT('success_bonus', 4, 'event_modifier', -2)),
  JSON_OBJECT('code','stay_quiet','name','Stay quiet and listen','description','Avoid attention and focus on overheard details.', 'cash_cost', 0, 'energy_cost', 1, 'effects', JSON_OBJECT('heat_modifier', -1, 'success_bonus', 2))
), JSON_ARRAY('useful_rumor','empty_talk','watchful_stranger'), JSON_ARRAY(JSON_OBJECT('item_code','stolen_wallet','chance', 15, 'min_quantity', 1, 'max_quantity', 1)), 1, NOW(), NOW()),
('watch_target_street', 'Watch Target Street', 'street_action', 'Observe a block and learn when small targets are careless.', 1, 1, 4, NULL, 180, 0, 74, 15, 1, 0, 50, 0, 1, 4, 10, JSON_ARRAY(), JSON_ARRAY(), JSON_ARRAY('surveillance','dark_clothing'), 0, JSON_ARRAY('scout'), JSON_ARRAY('intelligence','stealth','street_knowledge'), JSON_ARRAY(
  JSON_OBJECT('code','keep_distance','name','Keep distance','description','Lower attention by watching from further away.', 'cash_cost', 0, 'energy_cost', 1, 'effects', JSON_OBJECT('event_modifier', -3, 'heat_modifier', -1)),
  JSON_OBJECT('code','take_notes','name','Take careful notes','description','Improve the value of anything learned.', 'cash_cost', 0, 'energy_cost', 1, 'effects', JSON_OBJECT('success_bonus', 3, 'xp_bonus', 2))
), JSON_ARRAY('clean_observation','patrol_passes','witness_notices'), JSON_ARRAY(JSON_OBJECT('item_code','stolen_documents', 'chance', 10, 'min_quantity', 1, 'max_quantity', 1)), 1, NOW(), NOW()),
('pickpocket_pedestrian', 'Pickpocket Pedestrian', 'petty_theft', 'A fast street theft with low reward, low cost, and witness risk.', 1, 1, 5, 80, 300, 180, 66, 28, 3, 18, 95, 1, 4, 8, 22, JSON_ARRAY(), JSON_ARRAY(), JSON_ARRAY('gloves','stealth_clothing'), 0, JSON_ARRAY('thief','lookout'), JSON_ARRAY('stealth','street_knowledge'), JSON_ARRAY(
  JSON_OBJECT('code','watch_hands','name','Watch the mark first','description','Spend a little energy observing the target.', 'cash_cost', 0, 'energy_cost', 1, 'effects', JSON_OBJECT('success_bonus', 5, 'event_modifier', -3)),
  JSON_OBJECT('code','use_crowd','name','Use the crowd','description','Blend into the block before moving.', 'cash_cost', 0, 'energy_cost', 1, 'effects', JSON_OBJECT('heat_modifier', -1, 'success_bonus', 2))
), JSON_ARRAY('victim_notices','crowd_helps','undercover_nearby','empty_wallet','bonus_cash'), JSON_ARRAY(JSON_OBJECT('item_code','stolen_wallet','chance', 45, 'min_quantity', 1, 'max_quantity', 1)), 1, NOW(), NOW()),
('shoplift_small_goods', 'Shoplift Small Goods', 'petty_theft', 'Lift a low-value item from a shop and hope the clerk stays distracted.', 1, 1, 6, 75, 420, 240, 61, 32, 4, 25, 130, 1, 5, 10, 25, JSON_ARRAY(), JSON_ARRAY(), JSON_ARRAY('dark_clothing','carrying_bag','gloves'), 0, JSON_ARRAY('lookout'), JSON_ARRAY('stealth','discipline'), JSON_ARRAY(
  JSON_OBJECT('code','wait_for_aisle','name','Wait for an empty aisle','description','Lower the chance of a witness.', 'cash_cost', 0, 'energy_cost', 1, 'effects', JSON_OBJECT('event_modifier', -4, 'heat_modifier', -1)),
  JSON_OBJECT('code','bring_bag','name','Bring a bag','description','Use a bag if owned to improve loot.', 'cash_cost', 0, 'energy_cost', 0, 'effects', JSON_OBJECT('loot_bonus', 6, 'success_bonus', 2))
), JSON_ARRAY('clerk_notices','camera_catches_face','clean_exit','security_blocks_door','bonus_goods'), JSON_ARRAY(JSON_OBJECT('item_code','cheap_phone','chance', 30, 'min_quantity', 1, 'max_quantity', 1), JSON_OBJECT('item_code','small_electronics','chance', 25, 'min_quantity', 1, 'max_quantity', 1)), 1, NOW(), NOW()),
('steal_bicycle_parts', 'Steal Bicycle Parts', 'vehicle_part_theft', 'Strip small parts from a careless target. Tools help, but attention can build quickly.', 1, 1, 6, 75, 600, 300, 58, 35, 4, 30, 160, 1, 5, 12, 28, JSON_ARRAY(), JSON_ARRAY(), JSON_ARRAY('forced_entry_tool','gloves'), 0, JSON_ARRAY('lookout'), JSON_ARRAY('stealth','discipline'), JSON_ARRAY(
  JSON_OBJECT('code','check_owner','name','Check for owner nearby','description','Spend energy to lower surprise risk.', 'cash_cost', 0, 'energy_cost', 1, 'effects', JSON_OBJECT('event_modifier', -5, 'success_bonus', 2)),
  JSON_OBJECT('code','use_better_tool','name','Use better tool','description','A proper tool reduces noise if owned.', 'cash_cost', 0, 'energy_cost', 0, 'effects', JSON_OBJECT('success_bonus', 4))
), JSON_ARRAY('owner_returns','patrol_nearby','tool_slips','clean_removal'), JSON_ARRAY(JSON_OBJECT('item_code','bicycle_parts','chance', 60, 'min_quantity', 1, 'max_quantity', 2)), 1, NOW(), NOW()),
('steal_car_lamps', 'Steal Car Lamps', 'vehicle_part_theft', 'Remove valuable lamps from a parked car. Requires a screwdriver set or vehicle tools.', 2, 2, 7, 75, 900, 600, 54, 38, 5, 55, 260, 2, 7, 16, 36, JSON_ARRAY(), JSON_ARRAY('vehicle_tool','forced_entry_tool'), JSON_ARRAY('gloves','lookout','dark_clothing'), 0, JSON_ARRAY('lookout'), JSON_ARRAY('stealth','discipline','street_knowledge'), JSON_ARRAY(
  JSON_OBJECT('code','scout_witnesses','name','Scout for witnesses','description','Look for windows and people nearby.', 'cash_cost', 0, 'energy_cost', 2, 'effects', JSON_OBJECT('event_modifier', -6, 'heat_modifier', -1)),
  JSON_OBJECT('code','wait_quiet','name','Wait for a quieter moment','description','Delay until the block settles.', 'cash_cost', 0, 'energy_cost', 1, 'effects', JSON_OBJECT('success_bonus', 4, 'event_modifier', -3))
), JSON_ARRAY('neighbor_sees','tool_slips','patrol_turns_corner','rival_thief','unexpected_parts'), JSON_ARRAY(JSON_OBJECT('item_code','car_lamps','chance', 70, 'min_quantity', 1, 'max_quantity', 2)), 1, NOW(), NOW()),
('break_parked_car', 'Break Into Parked Car', 'burglary', 'Search a parked car for valuables. Requires a lockpick-style or generic tool.', 2, 3, 8, 70, 1200, 900, 50, 42, 7, 80, 420, 3, 10, 20, 50, JSON_ARRAY(), JSON_ARRAY('lockpick','forced_entry_tool','vehicle_tool'), JSON_ARRAY('gloves','carrying_bag','dark_clothing'), 0, JSON_ARRAY('thief','lookout'), JSON_ARRAY('stealth','intelligence','discipline'), JSON_ARRAY(
  JSON_OBJECT('code','scout_cameras','name','Scout for cameras','description','Lower witness and evidence risk.', 'cash_cost', 0, 'energy_cost', 2, 'effects', JSON_OBJECT('event_modifier', -6, 'heat_modifier', -1)),
  JSON_OBJECT('code','choose_quiet_car','name','Choose a quiet target','description','Lower reward but improve odds.', 'cash_cost', 0, 'energy_cost', 1, 'effects', JSON_OBJECT('success_bonus', 5, 'loot_bonus', -5))
), JSON_ARRAY('alarm_triggers','owner_appears','police_patrol','documents_found','bonus_cash'), JSON_ARRAY(JSON_OBJECT('item_code','small_electronics','chance', 40, 'min_quantity', 1, 'max_quantity', 2), JSON_OBJECT('item_code','stolen_documents','chance', 25, 'min_quantity', 1, 'max_quantity', 1), JSON_OBJECT('item_code','vehicle_parts','chance', 35, 'min_quantity', 1, 'max_quantity', 2)), 1, NOW(), NOW()),
('small_warehouse_sneak_in', 'Small Warehouse Sneak-In', 'burglary', 'Slip into a small warehouse area for tools and supplies. Requires gloves and a tool.', 3, 4, 10, 65, 1800, 1200, 47, 45, 8, 150, 800, 4, 13, 35, 90, JSON_ARRAY('gloves'), JSON_ARRAY('lockpick','forced_entry_tool'), JSON_ARRAY('surveillance','carrying_bag','dark_clothing'), 0, JSON_ARRAY('lookout','driver','infiltrator'), JSON_ARRAY('stealth','intelligence','discipline'), JSON_ARRAY(
  JSON_OBJECT('code','check_guard_route','name','Check guard route','description','Reduce patrol and dog-bark events.', 'cash_cost', 0, 'energy_cost', 2, 'effects', JSON_OBJECT('event_modifier', -7, 'success_bonus', 4)),
  JSON_OBJECT('code','bring_bag','name','Bring a bag','description','Improve carried loot if a bag is owned.', 'cash_cost', 0, 'energy_cost', 0, 'effects', JSON_OBJECT('loot_bonus', 12))
), JSON_ARRAY('guard_route_changes','camera_blind_spot','locked_cage','dog_barking','bonus_tools'), JSON_ARRAY(JSON_OBJECT('item_code','stolen_goods_bundle','chance', 55, 'min_quantity', 1, 'max_quantity', 3), JSON_OBJECT('item_code','warehouse_supplies','chance', 45, 'min_quantity', 1, 'max_quantity', 2)), 1, NOW(), NOW()),
('store_robbery', 'Store Robbery', 'robbery', 'A risky small-store robbery. Requires a mask and either a generic blade weapon or basic firearm.', 3, 5, 12, 60, 3600, 2400, 42, 55, 12, 250, 1200, 8, 22, 45, 120, JSON_ARRAY('mask'), JSON_ARRAY('blade_weapon','firearm'), JSON_ARRAY('gloves','carrying_bag','first_aid','driver'), 0, JSON_ARRAY('enforcer','driver','lookout'), JSON_ARRAY('intimidation','discipline','stealth'), JSON_ARRAY(
  JSON_OBJECT('code','prepare_exit','name','Prepare exit route','description','Lower the chance of a patrol or delayed escape.', 'cash_cost', 0, 'energy_cost', 2, 'effects', JSON_OBJECT('event_modifier', -7, 'success_bonus', 3)),
  JSON_OBJECT('code','take_smaller_target','name','Take smaller target','description','Reduce reward and heat risk.', 'cash_cost', 0, 'energy_cost', 1, 'effects', JSON_OBJECT('heat_modifier', -3, 'event_modifier', -4, 'loot_bonus', -20))
), JSON_ARRAY('clerk_panics','silent_alarm','customer_witness','police_patrol','rival_interruption'), JSON_ARRAY(JSON_OBJECT('item_code','cash_drawer_bundle','chance', 60, 'min_quantity', 1, 'max_quantity', 1), JSON_OBJECT('item_code','small_jewelry','chance', 15, 'min_quantity', 1, 'max_quantity', 1)), 1, NOW(), NOW()),
('low_value_vehicle_theft', 'Low-Value Vehicle Theft', 'vehicle_theft', 'Take a low-value vehicle or strip it for parts. Requires gloves and a vehicle/lockpick tool.', 4, 6, 14, 55, 5400, 3600, 39, 58, 14, 350, 1600, 9, 25, 65, 160, JSON_ARRAY('gloves'), JSON_ARRAY('vehicle_tool','lockpick'), JSON_ARRAY('driver','dark_clothing','communication'), 0, JSON_ARRAY('driver','lookout'), JSON_ARRAY('driving','stealth','discipline'), JSON_ARRAY(
  JSON_OBJECT('code','check_tracker','name','Check for tracker','description','Spend energy reducing dangerous surprises.', 'cash_cost', 0, 'energy_cost', 2, 'effects', JSON_OBJECT('event_modifier', -8, 'heat_modifier', -2)),
  JSON_OBJECT('code','line_up_buyer','name','Line up a buyer','description','Improve cash result but spend money upfront.', 'cash_cost', 35, 'energy_cost', 0, 'effects', JSON_OBJECT('loot_bonus', 15, 'success_bonus', 2))
), JSON_ARRAY('engine_fails','owner_appears','tracker_found','police_patrol','rival_recognizes_car'), JSON_ARRAY(JSON_OBJECT('item_code','vehicle_parts','chance', 65, 'min_quantity', 2, 'max_quantity', 4), JSON_OBJECT('item_code','low_value_vehicle', 'chance', 20, 'min_quantity', 1, 'max_quantity', 1)), 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  category = VALUES(category),
  description = VALUES(description),
  tier = VALUES(tier),
  min_level = VALUES(min_level),
  energy_cost = VALUES(energy_cost),
  max_heat = VALUES(max_heat),
  cooldown_seconds = VALUES(cooldown_seconds),
  district_cooldown_seconds = VALUES(district_cooldown_seconds),
  base_success_rate = VALUES(base_success_rate),
  base_event_chance = VALUES(base_event_chance),
  base_disaster_chance = VALUES(base_disaster_chance),
  reward_min = VALUES(reward_min),
  reward_max = VALUES(reward_max),
  heat_min = VALUES(heat_min),
  heat_max = VALUES(heat_max),
  xp_min = VALUES(xp_min),
  xp_max = VALUES(xp_max),
  required_all_item_tags = VALUES(required_all_item_tags),
  required_any_item_tags = VALUES(required_any_item_tags),
  recommended_item_tags = VALUES(recommended_item_tags),
  required_crew_count = VALUES(required_crew_count),
  recommended_crew_roles = VALUES(recommended_crew_roles),
  relevant_stats = VALUES(relevant_stats),
  preparation_options = VALUES(preparation_options),
  event_pool = VALUES(event_pool),
  loot_table = VALUES(loot_table),
  active = VALUES(active),
  updated_at = NOW();

INSERT INTO item_definitions (
  code, name, category, equipment_slot, description, price, storage_units, max_durability,
  illegal, stackable, effects, requirements, active, created_at, updated_at
) VALUES
('stolen_wallet', 'Stolen Wallet', 'stolen_good', NULL, 'A low-value stolen wallet that can be fenced or logged as petty loot.', 0, 1, 1, 1, 1, JSON_OBJECT('fence_value', 20), JSON_OBJECT(), 1, NOW(), NOW()),
('cheap_phone', 'Cheap Phone', 'stolen_good', NULL, 'A cheap phone taken during petty crime.', 0, 1, 1, 1, 1, JSON_OBJECT('fence_value', 45), JSON_OBJECT(), 1, NOW(), NOW()),
('small_electronics', 'Small Electronics', 'stolen_good', NULL, 'Small electronics that can be sold through contacts.', 0, 1, 1, 1, 1, JSON_OBJECT('fence_value', 80), JSON_OBJECT(), 1, NOW(), NOW()),
('bicycle_parts', 'Bicycle Parts', 'vehicle_part', NULL, 'Low-value parts removed from a bicycle.', 0, 1, 1, 1, 1, JSON_OBJECT('fence_value', 35), JSON_OBJECT(), 1, NOW(), NOW()),
('car_lamps', 'Car Lamps', 'vehicle_part', NULL, 'Vehicle lamps that a fence may buy.', 0, 2, 1, 1, 1, JSON_OBJECT('fence_value', 110), JSON_OBJECT(), 1, NOW(), NOW()),
('vehicle_parts', 'Vehicle Parts', 'vehicle_part', NULL, 'Generic vehicle parts from a risky street action.', 0, 3, 1, 1, 1, JSON_OBJECT('fence_value', 180), JSON_OBJECT(), 1, NOW(), NOW()),
('stolen_documents', 'Stolen Documents', 'stolen_good', NULL, 'Documents that may unlock a future lead or fence payout.', 0, 1, 1, 1, 1, JSON_OBJECT('lead_value', 1), JSON_OBJECT(), 1, NOW(), NOW()),
('stolen_goods_bundle', 'Stolen Goods Bundle', 'stolen_good', NULL, 'A mixed bundle of stolen low-tier goods.', 0, 3, 1, 1, 1, JSON_OBJECT('fence_value', 220), JSON_OBJECT(), 1, NOW(), NOW()),
('warehouse_supplies', 'Warehouse Supplies', 'production_supply', NULL, 'Loose supplies taken from a small warehouse.', 0, 3, 1, 1, 1, JSON_OBJECT('supply_value', 160), JSON_OBJECT(), 1, NOW(), NOW()),
('cash_drawer_bundle', 'Cash Drawer Bundle', 'stolen_good', NULL, 'A cash-heavy bundle from a small fictional store target.', 0, 1, 1, 1, 1, JSON_OBJECT('fence_value', 320), JSON_OBJECT(), 1, NOW(), NOW()),
('small_jewelry', 'Small Jewelry', 'stolen_good', NULL, 'A small jewelry item of uncertain value.', 0, 1, 1, 1, 1, JSON_OBJECT('fence_value', 260), JSON_OBJECT(), 1, NOW(), NOW()),
('low_value_vehicle', 'Low-Value Vehicle', 'general', NULL, 'A low-value fictional vehicle record represented as inventory loot.', 0, 8, 1, 1, 0, JSON_OBJECT('vehicle_value', 700), JSON_OBJECT(), 1, NOW(), NOW()),
('glass_breaker', 'Glass Breaker', 'tool', 'tool', 'A generic emergency tool with risky forced-entry use in game systems.', 130, 1, 100, 1, 1, JSON_OBJECT('forced_entry_bonus', 3), JSON_OBJECT(), 1, NOW(), NOW())
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

INSERT INTO item_tags (asset_type, asset_code, tag, created_at) VALUES
('item','screwdriver_set','forced_entry_tool',NOW()),
('item','screwdriver_set','vehicle_tool',NOW()),
('item','lockpick_kit','lockpick',NOW()),
('item','crowbar','forced_entry_tool',NOW()),
('item','gloves','gloves',NOW()),
('item','leather_gloves','gloves',NOW()),
('item','mask','mask',NOW()),
('item','dark_clothing','dark_clothing',NOW()),
('item','dark_clothing','stealth_clothing',NOW()),
('item','dark_hoodie','dark_clothing',NOW()),
('item','work_uniform','disguise',NOW()),
('item','duffel_bag','carrying_bag',NOW()),
('item','messenger_bag','carrying_bag',NOW()),
('item','backpack','carrying_bag',NOW()),
('item','burner_phone','communication',NOW()),
('item','smartphone','communication',NOW()),
('item','first_aid_kit','first_aid',NOW()),
('item','flashlight','surveillance',NOW()),
('item','surveillance_kit','surveillance',NOW()),
('item','vehicle_tools','vehicle_tool',NOW()),
('item','toolbox','vehicle_tool',NOW()),
('item','glass_breaker','forced_entry_tool',NOW()),
('weapon','Knife','blade_weapon',NOW()),
('weapon','Basic Pistol','firearm',NOW()),
('weapon','Pistol','firearm',NOW()),
('weapon','Baseball Bat','melee_weapon',NOW())
ON DUPLICATE KEY UPDATE tag = VALUES(tag);

INSERT INTO item_effects (asset_type, asset_code, effect_code, effect_value, created_at) VALUES
('item','gloves','evidence_modifier',-5,NOW()),
('item','leather_gloves','evidence_modifier',-4,NOW()),
('item','mask','heat_modifier',-2,NOW()),
('item','dark_clothing','success_bonus',4,NOW()),
('item','duffel_bag','loot_bonus',10,NOW()),
('item','first_aid_kit','injury_modifier',-8,NOW()),
('item','vehicle_tools','success_bonus',6,NOW()),
('item','screwdriver_set','success_bonus',3,NOW()),
('item','lockpick_kit','success_bonus',5,NOW()),
('item','crowbar','success_bonus',4,NOW()),
('item','surveillance_kit','event_modifier',-5,NOW()),
('weapon','Basic Pistol','intimidation_bonus',8,NOW()),
('weapon','Knife','intimidation_bonus',4,NOW())
ON DUPLICATE KEY UPDATE effect_value = VALUES(effect_value);
