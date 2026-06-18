INSERT INTO crime_discovery_locations (
  code, name, description, energy_cost, cash_cost, min_level, risk_level, active, created_at, updated_at
) VALUES
('bar', 'Iron Glass Bar', 'Listen for rumors, buy quiet information, and meet low-level contacts without making every crime visible upfront.', 4, 20, 1, 'low', 1, NOW(), NOW()),
('street', 'Street Corners', 'Walk the blocks, watch movement, and look for small openings or worried civilians.', 5, 0, 1, 'low', 1, NOW(), NOW()),
('garage', 'Backstreet Garage', 'Mechanics and drivers trade rumors about vehicles, parts, and delivery mistakes.', 5, 35, 1, 'medium', 1, NOW(), NOW()),
('pawn_shop', 'Pawn Shop Fence', 'A fence can point toward stolen goods, suspicious buyers, or false information if trust is low.', 6, 50, 2, 'medium', 1, NOW(), NOW()),
('warehouse_district', 'Warehouse District', 'Workers, clerks, and night guards sometimes leak storage schedules and dockside activity.', 7, 40, 2, 'medium', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  energy_cost = VALUES(energy_cost),
  cash_cost = VALUES(cash_cost),
  min_level = VALUES(min_level),
  risk_level = VALUES(risk_level),
  active = VALUES(active),
  updated_at = NOW();

INSERT INTO crime_v04_templates (
  code, category, title, briefing, tier, energy_cost,
  base_reward_min, base_reward_max, base_heat_min, base_heat_max,
  base_success_rate, base_disaster_chance, min_crew, max_crew,
  recommended_roles, required_items, relevant_stats, possible_events,
  active, created_at, updated_at
) VALUES
('bar_cash_delivery_tip', 'business-targeted crime', 'Cash-Heavy Delivery Rumor', 'A bar conversation points toward a small delivery with more cash than usual. The details are incomplete until investigated.', 2, 8, 180, 680, 2, 8, 57, 5, 0, 2, JSON_ARRAY('lookout','driver'), JSON_ARRAY('dark_clothing','burner_phone'), JSON_ARRAY('stealth','street_knowledge','driving'), JSON_ARRAY('police_patrol','witness_spotted','extra_loot'), 1, NOW(), NOW()),
('street_wallet_chain', 'pickpocketing', 'Distracted Crowd Opening', 'Several distracted marks are moving through a crowded block. It is fast money, but witnesses are close.', 1, 5, 60, 260, 1, 4, 68, 2, 0, 1, JSON_ARRAY('thief'), JSON_ARRAY('dark_clothing'), JSON_ARRAY('stealth','street_knowledge'), JSON_ARRAY('witness_spotted','equipment_failure'), 1, NOW(), NOW()),
('garage_parts_leak', 'vehicle theft', 'Unclaimed Parts at a Garage', 'A mechanic says a garage has valuable parts nobody has logged properly. The source may be exaggerating.', 2, 8, 220, 900, 3, 9, 54, 6, 1, 3, JSON_ARRAY('driver','lookout'), JSON_ARRAY('vehicle_tools','duffel_bag'), JSON_ARRAY('driving','stealth','discipline'), JSON_ARRAY('police_patrol','equipment_failure','rival_interference'), 1, NOW(), NOW()),
('pawnshop_stolen_electronics', 'fencing stolen goods', 'Electronics Buyer Looking Quiet', 'A fence has a buyer waiting for electronics. Finding the goods is the risky part, and the buyer may refuse if heat is high.', 2, 7, 160, 720, 2, 7, 58, 5, 0, 2, JSON_ARRAY('negotiator','thief'), JSON_ARRAY('duffel_bag','burner_phone'), JSON_ARRAY('intelligence','street_knowledge'), JSON_ARRAY('buyer_refuses','witness_spotted'), 1, NOW(), NOW()),
('warehouse_open_gate', 'burglary', 'Loose Warehouse Gate', 'A warehouse clerk hints that an east gate gets left open at night. The target is better than street crime but needs preparation.', 3, 10, 500, 1600, 5, 14, 48, 9, 1, 4, JSON_ARRAY('lookout','driver','infiltrator'), JSON_ARRAY('lockpick_kit','duffel_bag','surveillance_kit'), JSON_ARRAY('stealth','discipline','driving'), JSON_ARRAY('police_patrol','rival_interference','extra_loot','equipment_failure'), 1, NOW(), NOW()),
('dockside_manifest_leak', 'smuggling', 'Dockside Manifest Leak', 'A dock worker says a manifest was left exposed. It could reveal a valuable opening or a trap set by a rival.', 3, 10, 420, 1400, 4, 13, 50, 10, 1, 4, JSON_ARRAY('scout','driver','negotiator'), JSON_ARRAY('burner_phone','work_uniform','surveillance_kit'), JSON_ARRAY('intelligence','street_knowledge','driving'), JSON_ARRAY('police_patrol','rival_interference','witness_spotted'), 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  category = VALUES(category),
  title = VALUES(title),
  briefing = VALUES(briefing),
  tier = VALUES(tier),
  energy_cost = VALUES(energy_cost),
  base_reward_min = VALUES(base_reward_min),
  base_reward_max = VALUES(base_reward_max),
  base_heat_min = VALUES(base_heat_min),
  base_heat_max = VALUES(base_heat_max),
  base_success_rate = VALUES(base_success_rate),
  base_disaster_chance = VALUES(base_disaster_chance),
  min_crew = VALUES(min_crew),
  max_crew = VALUES(max_crew),
  recommended_roles = VALUES(recommended_roles),
  required_items = VALUES(required_items),
  relevant_stats = VALUES(relevant_stats),
  possible_events = VALUES(possible_events),
  active = VALUES(active),
  updated_at = NOW();

INSERT INTO item_definitions (
  code, name, category, equipment_slot, description, price, storage_units,
  max_durability, illegal, stackable, effects, requirements, active, created_at, updated_at
) VALUES
('screwdriver_set', 'Screwdriver Set', 'tool', 'tool', 'A compact generic screwdriver set used for preparation checks and equipment-failure backup options.', 95, 1, 100, 0, 1, JSON_OBJECT('stealth_entry', 3, 'equipment_backup', 2), JSON_OBJECT('min_reputation', 0), 1, NOW(), NOW()),
('duffel_bag', 'Duffel Bag', 'utility', 'bag', 'A plain dark bag that improves loot carry capacity and helps organized crime runs.', 120, 2, 100, 0, 1, JSON_OBJECT('loot_capacity', 8, 'escape_penalty', -1), JSON_OBJECT('min_reputation', 0), 1, NOW(), NOW()),
('first_aid_kit', 'First-Aid Kit', 'utility', 'support', 'A generic emergency kit that can soften injury aftermath in crime events.', 140, 1, 100, 0, 1, JSON_OBJECT('injury_reduction', 5, 'crew_recovery', 4), JSON_OBJECT('min_reputation', 0), 1, NOW(), NOW()),
('surveillance_kit', 'Surveillance Kit', 'tool', 'tool', 'A generic observation kit for scouting and risk estimates. It improves preparation without giving real-world instructions.', 360, 2, 100, 1, 1, JSON_OBJECT('scouting', 8, 'police_risk', -3, 'witness_risk', -2), JSON_OBJECT('min_reputation', 2), 1, NOW(), NOW()),
('dark_clothing', 'Dark Clothing', 'clothing', 'clothing', 'Generic dark clothing for abstract stealth and evidence-risk modifiers.', 90, 1, 100, 0, 1, JSON_OBJECT('stealth', 5, 'evidence_risk', -3), JSON_OBJECT('min_reputation', 0), 1, NOW(), NOW()),
('work_uniform', 'Work Uniform', 'clothing', 'clothing', 'A generic work uniform that supports disguise-style preparation in industrial or warehouse opportunities.', 180, 1, 100, 0, 1, JSON_OBJECT('disguise', 7, 'suspicion', -3), JSON_OBJECT('min_reputation', 1), 1, NOW(), NOW()),
('vehicle_tools', 'Vehicle Tools', 'tool', 'tool', 'A generic vehicle tool kit used in vehicle-related opportunities and breakdown events.', 260, 2, 100, 0, 1, JSON_OBJECT('driving', 4, 'vehicle_escape', 5, 'equipment_backup', 3), JSON_OBJECT('min_reputation', 1), 1, NOW(), NOW())
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

INSERT INTO npcs (
  first_name, last_name, nickname, age, gender, biography, background,
  occupation, role, organization, affiliation, current_activity, home_territory_id,
  personal_cash, wealth_class, reputation, criminal_reputation, health,
  morale, loyalty, status, alive, is_contact, is_informant, is_witness,
  is_police, is_rival, reliability, courage, greed, source_event, met_player_at,
  last_seen_at, notes, created_at, updated_at
)
SELECT 'Mae', 'Rusk', 'Iron Mae', 42, 'female', 'A bartender who hears too much and talks only when trust or cash is right.', 'Runs a smoky bar where low-level contacts cross paths.', 'Bartender', 'bartender', 'Iron Glass Bar', 'player contact', 'Listening for useful rumors.', (SELECT id FROM territories ORDER BY id LIMIT 1), 700, 'working', 12, 8, 100, 62, 54, 'active', 1, 1, 1, 0, 0, 0, 72, 50, 36, 'v0.4_seed', NOW(), NOW(), 'Seeded v0.4 bar contact.' , NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM npcs WHERE nickname = 'Iron Mae');

INSERT INTO npcs (
  first_name, last_name, nickname, age, gender, biography, background,
  occupation, role, organization, affiliation, current_activity, home_territory_id,
  personal_cash, wealth_class, reputation, criminal_reputation, health,
  morale, loyalty, status, alive, is_contact, is_informant, is_witness,
  is_police, is_rival, reliability, courage, greed, source_event, met_player_at,
  last_seen_at, notes, created_at, updated_at
)
SELECT 'Jonas', 'Vale', 'Grease', 36, 'male', 'A mechanic with a habit of knowing where cars and parts disappear.', 'Backstreet garage regular with useful but sometimes inflated stories.', 'Mechanic', 'mechanic', 'Backstreet Garage', 'informant', 'Watching garage activity.', (SELECT id FROM territories ORDER BY id DESC LIMIT 1), 420, 'working', 8, 16, 100, 58, 45, 'active', 1, 1, 1, 0, 0, 0, 61, 48, 58, 'v0.4_seed', NOW(), NOW(), 'Seeded v0.4 garage contact.' , NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM npcs WHERE nickname = 'Grease');

INSERT INTO npcs (
  first_name, last_name, nickname, age, gender, biography, background,
  occupation, role, organization, affiliation, current_activity, home_territory_id,
  personal_cash, wealth_class, reputation, criminal_reputation, health,
  morale, loyalty, status, alive, is_contact, is_informant, is_witness,
  is_police, is_rival, reliability, courage, greed, source_event, met_player_at,
  last_seen_at, notes, created_at, updated_at
)
SELECT 'Viktor', 'Marl', 'Ledger', 51, 'male', 'A pawn shop fence who values silence and remembers unpaid favors.', 'Runs a back-room counter for questionable goods.', 'Fence', 'fence', 'Pawn Shop Fence', 'supplier', 'Checking buyers.', (SELECT id FROM territories ORDER BY wealth DESC LIMIT 1), 1800, 'middle', 18, 28, 100, 66, 40, 'active', 1, 1, 1, 0, 0, 0, 68, 42, 76, 'v0.4_seed', NOW(), NOW(), 'Seeded v0.4 fence contact.' , NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM npcs WHERE nickname = 'Ledger');

INSERT INTO npcs (
  first_name, last_name, nickname, age, gender, biography, background,
  occupation, role, organization, affiliation, current_activity, home_territory_id,
  personal_cash, wealth_class, reputation, criminal_reputation, health,
  morale, loyalty, status, alive, is_contact, is_informant, is_witness,
  is_police, is_rival, reliability, courage, greed, source_event, met_player_at,
  last_seen_at, notes, created_at, updated_at
)
SELECT 'Rafi', 'Krol', 'Dock Rat', 29, 'male', 'A dock worker who sells vague schedules when money is tight.', 'Knows which warehouse doors get watched and which workers gossip.', 'Dock Worker', 'warehouse_worker', 'Dockside crew', 'informant', 'Avoiding supervisors.', (SELECT id FROM territories WHERE name LIKE '%Dock%' LIMIT 1), 260, 'poor', 5, 10, 100, 52, 38, 'active', 1, 1, 1, 0, 0, 0, 55, 44, 64, 'v0.4_seed', NOW(), NOW(), 'Seeded v0.4 dock contact.' , NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM npcs WHERE nickname = 'Dock Rat');

INSERT INTO npcs (
  first_name, last_name, nickname, age, gender, biography, background,
  occupation, role, organization, affiliation, current_activity, home_territory_id,
  personal_cash, wealth_class, reputation, criminal_reputation, health,
  morale, loyalty, status, alive, is_contact, is_informant, is_witness,
  is_police, is_rival, reliability, courage, greed, source_event, met_player_at,
  last_seen_at, notes, created_at, updated_at
)
SELECT 'Mila', 'Stone', 'Red Stitch', 33, 'female', 'A rival scout who sometimes sells information and sometimes poisons it.', 'Known around contested blocks for testing weak crews.', 'Scout', 'rival_scout', 'Red Knives', 'rival gang', 'Watching the player crew.', (SELECT id FROM territories ORDER BY crime_rate DESC LIMIT 1), 350, 'working', 14, 35, 100, 60, 35, 'active', 1, 0, 0, 0, 0, 1, 48, 62, 70, 'v0.4_seed', NULL, NOW(), 'Seeded v0.4 rival NPC.' , NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM npcs WHERE nickname = 'Red Stitch');

INSERT INTO npcs (
  first_name, last_name, nickname, age, gender, biography, background,
  occupation, role, organization, affiliation, current_activity, home_territory_id,
  personal_cash, wealth_class, reputation, criminal_reputation, health,
  morale, loyalty, status, alive, is_contact, is_informant, is_witness,
  is_police, is_rival, reliability, courage, greed, source_event, met_player_at,
  last_seen_at, death_category, death_game_date, death_notes, notes, created_at, updated_at
)
SELECT 'Tomas', 'Grey', 'Old Smoke', 58, 'male', 'A former informant kept in the records as an example of historical NPC state.', 'Dead NPC sample for admin visual validation.', 'Former informant', 'informant', 'Unknown', 'former contact', 'Historical record only.', (SELECT id FROM territories ORDER BY id LIMIT 1), 0, 'poor', -3, 18, 0, 0, 0, 'dead', 0, 0, 1, 0, 0, 0, 44, 37, 45, 'v0.4_seed', NOW(), NOW(), 'rival_aftermath', 'Year 1 Day 42', 'Died during a past off-screen rival aftermath event. Historical reference only.', 'Dead NPC sample for watermark and admin filters.' , NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM npcs WHERE nickname = 'Old Smoke');

INSERT INTO npc_timeline_events (npc_id, user_id, crime_run_id, event_type, title, description, metadata, created_at)
SELECT id, NULL, NULL, 'status', 'Dead record imported', 'Old Smoke remains in the world database but cannot offer jobs, contacts, or new opportunities.', JSON_OBJECT('status', 'dead'), NOW()
FROM npcs
WHERE nickname = 'Old Smoke'
  AND NOT EXISTS (
    SELECT 1 FROM npc_timeline_events events
    WHERE events.npc_id = npcs.id
      AND events.event_type = 'status'
      AND events.title = 'Dead record imported'
  );
