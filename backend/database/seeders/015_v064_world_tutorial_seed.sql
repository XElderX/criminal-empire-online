-- Criminal Empire Online v0.6.4 — World Tutorial & Player Guidance Update seed data

INSERT INTO tutorial_definitions (tutorial_key, version, title, description, is_active, created_at, updated_at) VALUES
('new_player_world_guide', '0.6.4', 'New Player World Guide', 'Full beginner tutorial for world map, travel, local presence, crew, jobs, heat, territories, warehouse, XP, and boss progression.', 1, NOW(), NOW()),
('world_systems_update', '0.6.4', 'World Systems Update', 'Short update guide for existing players who completed an older tutorial before travel and local presence became important.', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description), is_active=VALUES(is_active), updated_at=NOW();

INSERT INTO tutorial_steps (tutorial_key, tutorial_version, step_key, module_key, title, body, objective_type, objective_payload, route_hint, reward_payload, sort_order, is_optional, is_active, created_at, updated_at) VALUES
('new_player_world_guide', '0.6.4', 'welcome_riverdale', 'basics', 'Welcome to Riverdale County', 'Start small, learn the city, recruit NPCs, manage heat, and grow carefully.', 'acknowledge', JSON_OBJECT(), 'dashboard', JSON_OBJECT(), 10, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'core_stats', 'basics', 'Understand Core Stats', 'Review cash, bank cash, energy, heat, XP, level, boss health, and crew count.', 'acknowledge', JSON_OBJECT(), 'dashboard', JSON_OBJECT(), 20, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'open_world_map', 'world', 'Open the World Map', 'Open the world map and learn why hotspots matter.', 'view_page', JSON_OBJECT(), 'world map', JSON_OBJECT(), 30, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'enter_main_city', 'world', 'Enter Main City', 'Open Main City and review its hotspots.', 'view_page', JSON_OBJECT(), 'world map', JSON_OBJECT(), 40, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'travel_to_starter_hotspot', 'world', 'Travel to a Starter Hotspot', 'Travel to Main City / Slums to unlock local actions.', 'travel_to_location', JSON_OBJECT(), 'world map', JSON_OBJECT(), 50, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'explore_hotspot', 'local_actions', 'Explore the Hotspot', 'Explore your current hotspot to reveal local context.', 'explore_hotspot', JSON_OBJECT(), 'world map', JSON_OBJECT(), 60, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'safe_starter_job', 'local_actions', 'Do a Safe Starter Job', 'Complete one starter job or low-risk action.', 'complete_job', JSON_OBJECT(), 'jobs', JSON_OBJECT(), 70, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'try_quick_crime', 'local_actions', 'Try a Quick Crime', 'Attempt a low-tier quick crime.', 'complete_quick_crime', JSON_OBJECT(), 'crimes', JSON_OBJECT(), 80, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'understand_results', 'local_actions', 'Understand Results', 'Review outcomes, XP, cooldowns, heat, and loot.', 'acknowledge', JSON_OBJECT(), 'crimes', JSON_OBJECT(), 90, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'inspect_recruitment', 'crew', 'Inspect Recruitment', 'Inspect recruit stats, traits, fee, salary, and source.', 'inspect_candidate', JSON_OBJECT(), 'recruitment', JSON_OBJECT(), 100, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'hire_first_crew', 'crew', 'Hire First Crew Member', 'Hire one affordable active NPC crew member.', 'hire_crew', JSON_OBJECT(), 'recruitment', JSON_OBJECT(), 110, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'equip_basic_item', 'crew', 'Equip Basic Gear', 'Equip one basic item to boss or crew.', 'equip_item', JSON_OBJECT(), 'equipment', JSON_OBJECT(), 120, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'inspect_dirty_job', 'operations', 'Inspect a Dirty Job', 'Review Dirty Job source, target, preparation, and crew requirements.', 'inspect_dirty_job', JSON_OBJECT(), 'dirty jobs', JSON_OBJECT(), 130, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'execute_beginner_dirty_job', 'operations', 'Prepare or Execute a Beginner Dirty Job', 'Participate in a beginner Dirty Job.', 'execute_dirty_job', JSON_OBJECT(), 'dirty jobs', JSON_OBJECT(), 140, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'view_heat_police', 'pressure', 'Open Heat & Police', 'Review heat, investigations, and reduction options.', 'view_heat_page', JSON_OBJECT(), 'heat', JSON_OBJECT(), 150, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'learn_heat_reduction', 'pressure', 'Learn Heat Reduction', 'Learn safe ways to reduce heat without mandatory spending.', 'acknowledge', JSON_OBJECT(), 'heat', JSON_OBJECT(), 160, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'view_territory', 'pressure', 'View Territory Risk', 'Review local police, rival, and territory effects.', 'view_territory', JSON_OBJECT(), 'territories', JSON_OBJECT(), 170, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'view_warehouse', 'storage', 'Understand Warehouse & Storage', 'Open warehouse guidance and learn storage basics.', 'view_warehouse', JSON_OBJECT(), 'warehouse', JSON_OBJECT(), 180, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'boss_and_succession', 'storage', 'Boss and Succession', 'Learn boss health, heat, rank, XP, and succession risk.', 'acknowledge', JSON_OBJECT(), 'crew', JSON_OBJECT(), 190, 0, 1, NOW(), NOW()),
('new_player_world_guide', '0.6.4', 'finish_world_tutorial', 'storage', 'Finish the World Tutorial', 'Open the guide and review next goals.', 'view_guide', JSON_OBJECT(), 'guide', JSON_OBJECT('cash', 60, 'xp', 15), 200, 0, 1, NOW(), NOW()),
('world_systems_update', '0.6.4', 'update_open_world_map', 'world', 'Open World Map', 'Review the world map added in recent updates.', 'view_page', JSON_OBJECT(), 'world map', JSON_OBJECT(), 10, 0, 1, NOW(), NOW()),
('world_systems_update', '0.6.4', 'update_travel_hotspot', 'world', 'Travel to a Hotspot', 'Travel to any hotspot to set local presence.', 'travel_to_location', JSON_OBJECT(), 'world map', JSON_OBJECT(), 20, 0, 1, NOW(), NOW()),
('world_systems_update', '0.6.4', 'update_view_local_actions', 'local_actions', 'View Local Actions', 'Review what actions are available here or require travel.', 'view_page', JSON_OBJECT(), 'world map', JSON_OBJECT(), 30, 0, 1, NOW(), NOW()),
('world_systems_update', '0.6.4', 'update_explore_hotspot', 'local_actions', 'Explore a Hotspot', 'Explore once to see local rumors and leads.', 'explore_hotspot', JSON_OBJECT(), 'world map', JSON_OBJECT(), 40, 0, 1, NOW(), NOW()),
('world_systems_update', '0.6.4', 'update_heat_police', 'pressure', 'Open Heat & Police', 'Review boss, crew, gang, and police pressure.', 'view_heat_page', JSON_OBJECT(), 'heat', JSON_OBJECT(), 50, 0, 1, NOW(), NOW()),
('world_systems_update', '0.6.4', 'update_territory_effects', 'pressure', 'View Local / Territory Effects', 'Review territory and district risk.', 'view_territory', JSON_OBJECT(), 'territories', JSON_OBJECT(), 60, 0, 1, NOW(), NOW()),
('world_systems_update', '0.6.4', 'update_finish', 'storage', 'Finish World Systems Update', 'Open the guide once.', 'view_guide', JSON_OBJECT(), 'guide', JSON_OBJECT('cash', 20, 'xp', 5), 70, 0, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE module_key=VALUES(module_key), title=VALUES(title), body=VALUES(body), objective_type=VALUES(objective_type), objective_payload=VALUES(objective_payload), route_hint=VALUES(route_hint), reward_payload=VALUES(reward_payload), sort_order=VALUES(sort_order), is_optional=VALUES(is_optional), is_active=VALUES(is_active), updated_at=NOW();

INSERT INTO guide_sections (section_key, title, body, sort_order, is_active, created_at, updated_at) VALUES
('beginner_path', 'Beginner Path', 'Start from the dashboard, open the world map, travel to a starter hotspot, explore, do small work, hire one crew member, equip basic gear, then inspect safer Dirty Jobs.', 10, 1, NOW(), NOW()),
('world_map', 'World Map & Hotspots', 'Hotspots define local quick crimes, dirty job leads, contacts, business hooks, police pressure, and territory context. Viewing is remote; acting often requires local presence.', 20, 1, NOW(), NOW()),
('travel', 'Travel & Local Presence', 'Travel costs small energy or cash, may trigger events, records history, and unlocks local actions. High heat or illegal carried goods increase travel risk.', 30, 1, NOW(), NOW()),
('quick_crimes', 'Quick Crimes', 'Quick Crimes are fast, low-to-mid tier actions. Some are location-specific and will tell you where to travel before starting.', 40, 1, NOW(), NOW()),
('dirty_jobs', 'Dirty Jobs', 'Dirty Jobs have contacts, preparation, crew roles, equipment, execution, and consequences. Some require local presence before accepting or executing.', 50, 1, NOW(), NOW()),
('crew', 'Crew & Recruitment', 'Crew are named NPCs with portraits, age, stats, traits, salaries, heat, morale, loyalty, and histories. Low-level dismissed crew may return to recruitment; experienced crew return to ordinary NPC life.', 60, 1, NOW(), NOW()),
('equipment', 'Equipment', 'Items like gloves, masks, tools, bags, clothing, first aid, and fictional weapons modify risks and outcomes. Use them carefully because heat and searches matter.', 70, 1, NOW(), NOW()),
('heat_police', 'Heat & Police Pressure', 'Actions and travel can raise heat. Heat can exist on the boss, crew, gang, districts, and NPCs. Reduction options and quiet days help manage pressure.', 80, 1, NOW(), NOW()),
('territories', 'Territories', 'Territories tie map places to risk, rewards, police presence, and rival pressure. Scout before pushing deeper.', 90, 1, NOW(), NOW()),
('warehouse', 'Warehouse & Storage', 'Warehouses store physical loot and supplies. Stored items can reduce travel-carrying risk where systems support it.', 100, 1, NOW(), NOW()),
('progression', 'XP, Skills & World Processing', 'XP and skills grow through actions. Hourly, daily, and weekly world processing refreshes jobs, recruitment, recovery, heat decay, salaries, and map opportunities.', 110, 1, NOW(), NOW()),
('boss_succession', 'Boss & Succession', 'The boss is a character with health, rank, XP, and heat. Severe outcomes can injure, arrest, or eventually kill the boss; eligible crew can become successors.', 120, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE title=VALUES(title), body=VALUES(body), sort_order=VALUES(sort_order), is_active=VALUES(is_active), updated_at=NOW();

INSERT INTO help_tips (tip_key, page_key, title, body, guide_section_key, sort_order, is_active, created_at, updated_at) VALUES
('dashboard', 'dashboard', 'Dashboard', 'Use the dashboard to check boss status, current location, heat, energy, XP, and the next safe goal.', 'beginner_path', 10, 1, NOW(), NOW()),
('world_map', 'world map', 'World Map', 'Regions and hotspots are not just art. Travel changes what local actions, risks, contacts, and events are available.', 'world_map', 10, 1, NOW(), NOW()),
('location_map', 'world map', 'Location Map', 'A hotspot can be viewed remotely, but local actions often require being physically present there.', 'travel', 20, 1, NOW(), NOW()),
('crimes', 'crimes', 'Crimes & Quick Crimes', 'Quick Crimes are fast actions. Some are local and require travel; risk changes with heat, location, and equipment.', 'quick_crimes', 10, 1, NOW(), NOW()),
('dirty_jobs', 'dirty jobs', 'Dirty Jobs', 'Dirty Jobs are structured operations with preparation, crew assignment, equipment, travel requirements, and consequences.', 'dirty_jobs', 10, 1, NOW(), NOW()),
('recruitment', 'recruitment', 'Recruitment', 'Recruitable NPCs have stats, traits, salary, morale, loyalty, heat, and sometimes local source hotspots.', 'crew', 10, 1, NOW(), NOW()),
('crew', 'crew', 'Crew', 'Crew members are persistent NPCs. Watch their heat, injuries, status, equipment, history, and loyalty.', 'crew', 20, 1, NOW(), NOW()),
('equipment', 'equipment', 'Inventory & Equipment', 'Basic tools and protective gear improve outcomes. One item cannot be equipped by multiple crew at the same time.', 'equipment', 10, 1, NOW(), NOW()),
('warehouse', 'warehouse', 'Warehouse', 'Storage helps keep physical loot and illegal goods off the boss while adding capacity and security choices.', 'warehouse', 10, 1, NOW(), NOW()),
('territories', 'territories', 'Territories', 'Territories connect to map areas. Police pressure and rival control can change local danger and rewards.', 'territories', 10, 1, NOW(), NOW()),
('heat', 'heat', 'Heat & Police', 'Heat belongs to the boss, crew, gang, NPCs, and districts. High heat can feed investigations and travel risk.', 'heat_police', 10, 1, NOW(), NOW()),
('market', 'market', 'Drug Market', 'The drug market is an abstract economy page; prices, supply, demand, and police pressure vary by region.', 'world_map', 30, 1, NOW(), NOW()),
('jobs', 'jobs', 'Street Jobs', 'Street Jobs are starter work but still require at least one active real NPC crew member after v0.6.3.1.', 'beginner_path', 30, 1, NOW(), NOW()),
('guide', 'guide', 'Guide', 'The guide is always available for tutorial chapters, update notes, and next goals.', 'beginner_path', 40, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE page_key=VALUES(page_key), title=VALUES(title), body=VALUES(body), guide_section_key=VALUES(guide_section_key), sort_order=VALUES(sort_order), is_active=VALUES(is_active), updated_at=NOW();

INSERT INTO update_notices (version, title, body, active, created_at, updated_at)
SELECT '0.6.4', 'v0.6.4 — World Tutorial & Player Guidance Update', 'The tutorial now covers the world map, travel, local presence, hotspot exploration, quick crimes, dirty jobs, crew, heat, territories, warehouse, XP, and boss guidance. Existing players receive a short World Systems Update guide instead of being forced through the full tutorial again.', 1, NOW(), NOW()
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'update_notices')
  AND NOT EXISTS (SELECT 1 FROM update_notices WHERE version = '0.6.4');
