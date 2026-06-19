## 2026-06-19 — Criminal Empire Online v0.5.1.2 — Admin Heat Reset

- Added an admin panel action to clear a selected player's heat profile to `0`.
- The admin reset now clears user heat, boss personal heat, gang heat, and crew member personal heat in one step for easier moderation and testing.

## 2026-06-19 — Criminal Empire Online v0.5.1.1 — Boss Name Setup

- New registrations now require explicit boss first name and surname instead of inheriting the username as the default boss identity.
- Existing users can set the boss name once from the Heat & Police page if the account still uses the original default boss name.
- Added `POST /api/boss/rename` and exposed boss rename eligibility in the boss profile payload.

## 2026-06-19 — Criminal Empire Online v0.5.1 — Boss Character Integration

- Added full boss operational stats beside the older account stats: shooting, driving, stealth, intimidation, discipline, street knowledge, endurance, and age/role metadata.
- Boss now appears in the Crew page as a real dossier card/profile with skill grid, health, personal heat, and boss status.
- `/my-gang/0` now returns the boss profile, and `/my-gang/0/history` returns boss history in crew-history compatible format.
- Discovered crime opportunities can assign the boss as an actor using the existing crew assignment UI. Boss participation uses boss skills in risk calculation and applies boss heat when personally involved.
- Quick Crimes can select boss and/or crew actors. Boss skills count toward quick crime success, and disastrous boss-led quick crimes can injure the boss.
- Added actor fields to crime assignment tables so future systems can distinguish boss and crew participation without fake crew rows.
- Updated Heat & Police boss card to display the full boss skill grid.
- Added a one-time update notice for v0.5.1.

## 2026-06-19 — Criminal Empire Online v0.5 — Heat & Police Pressure Expansion

- Preserved v0.4 Crimes Expansion, v0.4.1 Quick Crimes, and v0.4.2 crime subtabs while expanding heat into a strategic police-pressure system.
- Added boss personal heat, gang heat, crew personal heat, NPC heat, district heat, heat logs, police investigations, police events, boss history, update notices, and acknowledgement tracking.
- Added services: `HeatPressureService`, `InvestigationService`, `BossCharacterService`, `SuccessionService`, and `UpdateNoticeService`.
- Added routes for heat overview/logs/reduction actions, investigations, boss profile/history/succession, admin heat/investigation views, and update notice acknowledgement.
- Added daily idle heat decay (-5) and weekly quiet bonus (-15), with idempotent per-day processing state.
- Added heat reduction actions for lie low, bribe contact, pay lawyer, destroy evidence, and send high-heat crew away.
- Integrated heat logging into fallback street actions, quick crimes, dirty jobs, and v0.4 major crimes.
- Added high-heat crew dismissal consequences: Dismissed high-heat crew can reduce pressure while risking revenge. dismissing a hot crew member can reduce boss/gang heat but creates revenge risk and possible sabotage/retaliation events.
- Added Heat & Police frontend page, crew personal heat badges, and a one-time update notice modal that appears after login until confirmed.
- Added v0.5 contract/unit coverage and updated verification notes.


## 2026-06-19 — Criminal Empire Online v0.4.2 — Fallback Street Actions

- Split the Crimes page into separate subtabs for `Explore Leads`, `Quick Crimes & Street Actions`, and `Fallback Street Actions`.
- Added a 10-minute cooldown per fallback street action, enforced through `player_action_cooldowns` and surfaced in the crimes UI.
- Preserved the full quick-crime feature set while keeping the legacy fallback crime seed reduced.
- Bumped application metadata to v0.4.2 and refreshed the navigation badge.

## 2026-06-19 — Criminal Empire Online v0.4.1 — Fallback Street Actions & Quick Crimes

- Built on the v0.4 Crimes Expansion without removing discovered opportunities, NPC leads, or the Admin NPC browser.
- Added quick-crime templates for ask-around rumors, target street watching, pickpocketing, shoplifting, bicycle parts theft, car-lamp theft, parked-car break-in, warehouse sneak-in, store robbery, and low-value vehicle theft.
- Added structured item tags/effects for requirements such as gloves, masks, lockpicks, vehicle tools, carrying bags, first aid, communication, surveillance, blade weapons, firearms, and forced-entry tools.
- Added quick-crime cooldowns, player recent action logs, preparation records, event decisions, experience logs, skill progression logs, and run-level reward idempotency.
- Added backend services: `QuickCrimeService`, `ItemRequirementService`, `ExperienceService`, and `SkillProgressionService`.
- Added routes for listing, preparing, starting, resolving, deciding, and viewing quick crimes/history/progression.
- Updated the Crimes frontend with Quick Crime cards, missing item notices, cooldown display, preparation buttons, event decisions, result panels, XP/skill-gain display, and quick action history.
- Added v0.4.1 migration, seeder, tests, API documentation, and frontend styling.


## 2026-06-18 — Criminal Empire Online v0.4 — Crimes Expansion

- Bumped application metadata to v0.4.0 / `Criminal Empire Online v0.4 — Crimes Expansion`.
- Reworked the Crimes page from a primitive static click-result list into a multi-step crime loop: explore locations, discover rumors/leads, investigate, prepare, assign crew/equipment, execute, handle random decision events, and review outcomes.
- Added backend v0.4 crime lifecycle tables for discovery locations, crime templates, player-specific opportunities, preparations, opportunity crew/equipment selections, runs, events, NPC involvement, NPC relationships, NPC timeline events, and NPC status logs.
- Added v0.4 seed data for bar/street/garage/pawn-shop/warehouse discovery locations, reusable crime templates, persistent NPC contacts/informants/rivals, a dead historical NPC sample, and new equipment items.
- Added focused backend services: `CrimeOpportunityService`, `CrimeRiskCalculator`, `CrimeNarrativeService`, and `NpcAdminService`.
- Added player API endpoints for exploring locations, viewing/investigating/preparing opportunities, assigning crew/equipment, starting crime runs, resolving decision events, abandoning opportunities, listing contacts, and viewing crime history.
- Added admin API endpoints for NPC list/detail browsing with filters/search/sorting and timeline/relationship/involvement data.
- Added dead NPC visual behavior in the admin UI: grayscale portrait, reduced opacity, visible `DEAD` watermark, death status notes, and readable dead status text.
- Updated v0.4 Crimes frontend with known opportunities, leads/rumors, active decision events, preparation cards, crew/equipment selectors, contacts, recent outcomes, and legacy quick-crime fallback.
- Updated Admin frontend with an NPC World browser, filters, searchable cards, detail drawer, stats, flags, timelines, relationships, and death records.
- Added v0.4 tests for risk calculation, event/preparation definitions, backend contract coverage, endpoints, admin NPC browser, frontend payload types, and asset mapping.

## 2026-06-18 — Criminal Empire Online v0.3.6.5 — v0.4 Asset Primer

- Applied the new supplied territory, dirty job, and crime art sheets to the current local asset system before the v0.4 feature work.
- Refreshed WebP and matching SVG wrapper assets for territory backgrounds, dirty-job thumbnails, and crime thumbnails so stale `.svg` paths still render real visuals.
- Added new v0.4 inventory art cards for `Screwdriver Set`, `First-Aid Kit`, `Surveillance Kit`, `Dark Clothing`, `Work Uniform`, `Vehicle Tools`, and `Duffel Bag`.
- Updated `itemIconMap.ts` aliases so new v0.4 equipment appears in Inventory/Admin/Crimes selectors with local WebP art.

## 2026-06-18 — Criminal Empire Online v0.3.6.4 — Territory, Job & Crime Art Refresh

- Bumped the application version to v0.3.6.4.
- Replaced the territory background art with the new supplied noir district cards for slums, downtown, industrial zone, docks, suburbs, nightlife district, market district, police district, rich district, and old town.
- Replaced the dirty job images with the new supplied job card art for back alley collection, warehouse pickup, night delivery, evidence cleanup, protection visit, stolen goods pickup, debt pressure, fake document run, and dockside drop.
- Replaced the crime images with the new supplied card art for pickpocket, wallet snatch, shoplifting, car break-in, bike theft, street scam, store robbery, cargo theft, and heist planning.
- Refreshed matching `.svg` wrappers so stale references still render the updated local WebP visuals.

## 2026-06-18 — Criminal Empire Online v0.3.6.3 — Navigation & Status Icons Refresh + Admin Item Counts

- Bumped the application version to v0.3.6.3.
- Replaced the old main navigation icons with the new supplied noir icon set for dashboard, crimes, dirty jobs, crew, inventory, drug market, businesses, gangs, territories, admin, messages, and settings.
- Replaced the old risk and heat visuals with the new supplied status icon set, and updated frontend mappings so risk levels and heat badges use the new assets.
- Updated the admin asset catalog page so every item, weapon, and drug row shows its image, id, and current quantity in the game.
- Extended the admin backend catalog endpoint to include inventory quantities, warehouse quantities, owner counts, and total quantities in game.

## 2026-06-18 — Criminal Empire Online v0.3.6.2 — Expanded Item Assets & Admin Inventory Tools

- Bumped the application version to v0.3.6.2.
- Added the new supplied tools, clothing, vehicle, valuables, and contraband item art into the real local asset folders under `frontend/public/assets/icons/items/`.
- Refreshed `itemIconMap.ts` so the new asset names and the main legacy aliases resolve to the updated visuals instead of older placeholders.
- Added backend admin endpoints for a full obtainables catalog and for granting items, weapons, or drugs directly into a player inventory by user id.
- Expanded the Admin page with an obtainables/equipmentables reference table, quick user list, asset id lookup, and an "Add to inventory" tool.
- Added a new seeder `005_v0362_items_admin.sql` with additional item definitions for the new utility, clothing, transport, valuables, and contraband entries.


## 2026-06-18 — Criminal Empire Online v0.3.6.1 — Weapons Asset Refresh

- Bumped the application version to v0.3.6.1.
- Removed the previous weapon art set under `frontend/public/assets/icons/items/weapons/`.
- Replaced the weapons folder with a new noir card-style local asset pack based on the supplied mockup sheet.
- Added 15 weapon visuals: Basic Pistol, Heavy Pistol, Revolver, Compact SMG, Black Market Rifle, Shotgun, Knife, Machete, Baseball Bat, Brass Knuckles, Sniper Rifle, Assault Rifle, Carbine Rifle, Battle Rifle, and Tactical SMG.
- Rebuilt matching `.svg` wrappers for the new weapon `.webp` files so older cached paths still render the updated art where the filename still exists.
- Updated `itemIconMap.ts` weapon aliases so legacy item keys route to the new v0.3.6.1 weapon assets without falling back to deleted files.

# Development log

## 2026-06-16 — Single-player foundation, Phase 1 and Phase 2

- Centralized new-player starting values and changed new registrations to $500 cash, $0 bank cash, $0 dirty money, 0 reputation, and 0 heat.
- Existing user balances are not reset.
- Added persistent NPC profiles, structured NPC traits, recruitment candidates, and player gang members.
- Added nine timed beginner jobs from NPC employers and criminal contacts.
- Added backend-authoritative job start/completion, energy costs, requirements, success calculation, duplicate-completion prevention, crew assignments, XP, heat, and reward ledger entries.
- Added recruitment market, atomic hiring, recruitment fees, biographies, stats, traits, salaries, personal NPC money, morale, loyalty, health, and status.
- Added weekly salary processing and overdue salary payments.
- Added economy transaction ledger and developer economy status command/API.
- Added Jobs, Recruitment, and My Gang frontend pages.
- Added native Ubuntu/MySQL installation guide without Docker.

## 2026-06-17 — Criminal Empire Online v0.3 — Dirty Jobs Expansion

- Updated application, API, package, frontend, documentation, and release metadata to v0.3.0.
- Added a persistent ten-step new-player tutorial with backend-validated objectives, skip/reopen behavior, completion history, and idempotent rewards.
- Migrated existing users into a completed tutorial state without resetting money, inventory, crew, or progression.
- Added structured Dirty Job templates, finite player opportunities, NPC contacts, expiry, acceptance, preparation, crew roles, execution timers, narrative decisions, and final resolution.
- Added a centralized calculator using role-weighted crew skills, traits, health, morale, equipment, preparation, district police presence, player heat, difficulty, and controlled randomness.
- Added critical success, success, partial success, failure, and critical failure outcomes.
- Added physical loot, dirty cash, drugs, stolen goods, vehicle parts, vehicles, contact reputation, experience, heat, injury, arrest, and equipment consequences.
- Added structured item definitions and effect JSON for tools, clothing, utility gear, bags, armor, loot, and abstract production supplies.
- Extended the existing weapon inventory and connected both weapons and items to one crew-loadout system.
- Added item condition, weapon durability, equipment damage/loss, ownership checks, slot validation, and warehouse-access rules.
- Added persistent crew history for recruitment, operations, injuries, arrests, dismissal, world return, and rehiring.
- Implemented dismissal without deleting NPC identity, blocked dismissal during active operations, returned player-owned equipment, and preserved unpaid wages and relationship history.
- Added extensible building types, NPC property listings, warehouse purchase, capacities, security, operating costs, debt, upgrades, storage logs, and vehicle slots.
- Added transaction-safe storage transfers for items, weapons, drugs, stolen assets, and vehicles.
- Added an abstract fictional warehouse production operation without real-world cultivation instructions.
- Added heat reduction, crew recovery, Dirty Job refresh/expiry, warehouse-cost processing, and related CLI commands.
- Split the React frontend into readable pages and shared components for tutorial, Dirty Jobs, crew, equipment, warehouse, jobs, recruitment, market, territories, and admin.
- Added v0.3 unit, source-contract, and guarded MySQL integration tests.

## 2026-06-18 — Criminal Empire Online v0.3.5 — Crew Portraits & Design Update

- Added 50 persistent portrait identities from the supplied concept sheets, each with an optimized adult WebP asset and thumbnail.
- Added a neutral fallback portrait and manifest validation.
- Added exact life stages: Very Young 16–24, Young 25–31, Adult 32–40, Mature 41–55, and Elder 56–70.
- Enforced gender-compatible portrait assignment: male NPCs receive male sets and female NPCs receive female sets.
- Added stable portrait identity fields, focal points, birth game metadata, stage cache, and last processed world year.
- Added idempotent existing-NPC portrait backfill and stage synchronization commands.
- Added game-world year processing that ages persistent NPCs without resetting any character state.
- Added portrait-stage history records when an active or former crew member crosses an age boundary.
- Added backend-resolved portrait, life-stage, role, experience, and reputation presentation data to crew and recruitment responses.
- Redesigned My Crew with cinematic portrait cards, summary statistics, filtering, sorting, grid/list views, clear status, skills, traits, equipment, and condition.
- Added a dedicated crew profile with biography, finances, full stats, traits, equipment, history, and actions.
- Redesigned recruitment cards with portraits, life stages, biographies, strengths, negative traits, fees, salaries, and clear hire restrictions.
- Split crew presentation into focused reusable React components rather than one large component.
- Added responsive desktop, tablet, and mobile styling with lazy image loading and stable portrait dimensions.
- Added v0.3.5 unit and contract tests.
- Recorded the art limitation honestly: 50 identity sets exist, but 0 complete matching five-stage sets; 200 age-stage files remain to be supplied.

## 2026-06-18 — Criminal Empire Online v0.3.6 — Visual Redesign & Asset Integration

- Updated frontend package/version labels to v0.3.6 while preserving the existing PHP REST backend, MySQL schema style, API routes, and gameplay systems.
- Added a dark noir visual layer with charcoal panels, bronze/grey borders, green money highlights, red heat warnings, yellow/orange risk indicators, blue planning accents, purple rare/elite accents, and mobile-first responsive grids.
- Added local asset folders under `frontend/public/assets/` for crew, item icons, role icons, crimes, jobs, businesses, territories, and placeholders.
- Added local SVG fallback placeholders for items, crimes, dirty jobs, territories, male crew, female crew, and global crew portraits so the UI does not rely on remote CDN images.
- Added gender-safe v0.3.6 crew portrait helper `getCrewPortrait(gender, portraitKey, age)` with the 16–24, 25–31, 32–40, 41–55, and 56–70 stage naming convention.
- Copied the available v0.3.5 adult portrait assets into the new gender-safe 32–40 WebP locations; missing age stages fall back to the same identity adult image, then gender default, then global default.
- Added centralized asset maps for item icons, role icons, crime images, dirty job images, business images, territory images, and the `assetManifest.ts` export surface.
- Added reusable game UI components: GameLayout, GameHeader, BottomNavigation, SectionCard, StatCard, ProgressBar, RiskBadge, HeatBadge, ItemIconCard, CrimePictureCard, CrewMemberCard, CrewPortrait, EmptyState, and LoadingState.
- Redesigned the dashboard into a command board with player avatar, level/XP, energy, cash, bank money, active Dirty Job panel, crew status, current territory, heat controls, and recent activity.
- Redesigned Crimes with picture cards, reward/energy/heat information, and local crime image fallbacks.
- Updated Dirty Jobs with cinematic job thumbnails, risk and heat badges, visual opportunity list items, and a picture-card briefing panel.
- Updated Crew and Recruitment to use gender-safe portrait paths and role icon assets while keeping existing crew dossier/profile workflows.
- Updated Inventory/Equipment with local item icon cards for owned gear and the equipment shop.
- Updated Territories with local background image cards and control/status details.
- Improved navigation naming and added a mobile bottom navigation that hides admin access for non-admin users.
- Added README documentation for where to place final generated assets, crew portrait naming rules, and fallback behavior.


## 2026-06-18 — Criminal Empire Online v0.3.6 Asset Pack Applied

- Replaced the first-pass empty SVG-style UI placeholders in the frontend mappings with production-style local WebP crops taken from the supplied noir asset concept sheets.
- Added real-looking WebP item icons for weapons, protection gear, tools, vehicles, valuables, and contraband.
- Added real-looking WebP thumbnails for crimes, dirty jobs, businesses, and territory backgrounds.
- Added WebP crew/default, item/default, crime/default, job/default, and territory/default fallbacks so runtime image errors no longer fall back to the plain placeholder cards first.
- Updated `itemIconMap.ts`, `roleIconMap.ts`, `crimeImageMap.ts`, `jobImageMap.ts`, `businessImageMap.ts`, and `territoryImageMap.ts` to reference `.webp` game assets.
- Updated the shared item/crime cards and dashboard player image to use WebP fallbacks.
- Kept the existing SVG placeholder files as emergency backups and development references.

## 2026-06-18 — Criminal Empire Online v0.3.6 Real Visuals Hotfix

- Rebuilt the asset pack so legacy `.svg` asset paths are no longer empty template graphics: every SVG counterpart now wraps the matching local WebP art, so both old `/assets/.../*.svg` references and new `/assets/.../*.webp` references display the same real visuals.
- Verified all React source asset mappings point to WebP assets first and that every referenced `/assets/...` path exists in `frontend/public/assets`.
- Confirmed crime, dirty job, inventory, business, territory, role, and fallback assets now display rendered local game art rather than plain geometric placeholders.
- This hotfix is intended to prevent stale browser/dev-server code from still showing the old placeholder SVG cards while the frontend is being refreshed.

## 2026-06-18 — Criminal Empire Online v0.3.6 Mockup Asset Application Pass

- Applied the supplied noir UI mockup sheet directly into the project as local game assets.
- Re-cropped item icons so weapons, protection gear, tools, vehicles, valuables, documents, and contraband show actual object art instead of blank template cards or large baked labels.
- Added missing local image categories for document/ID assets, navigation icons, and risk/heat indicator icons.
- Expanded `itemIconMap.ts` with aliases for seeded backend names including Saturday Night Special, 9mm Pistol, Sawed-off Shotgun, Cheap Knife, Lockpick Set, Work Gloves, Dark Clothing, Basic Protective Vest, First-Aid Kit, Basic Surveillance Kit, warehouse supplies, stolen goods, vehicle parts, and seeded drugs.
- Rebuilt crime and dirty job thumbnails from the supplied visual sheet and added aliases for Protection Collection, Vehicle Theft, Armored Truck Heist, Back Alley Collection, Warehouse Pickup, Evidence Cleanup, Protection Visit, Night Delivery, Stolen Goods Pickup, Debt Pressure, Fake Document Run, and Dockside Drop.
- Added navigation icon mappings and wired them into desktop navigation and mobile bottom navigation.
- Added risk/heat image icons to `RiskBadge` and `HeatBadge`.
- Updated Drug Market rows and Warehouse storage/deposit rows to show local item/contraband icons, so drugs and stored assets are visually represented outside the inventory page too.
- Rebuilt matching SVG wrappers for generated WebP assets so stale `.svg` paths still display the real local artwork.
- Verified frontend build with `npm run build` and reran v0.3/v0.3.5 backend unit and contract tests successfully.
