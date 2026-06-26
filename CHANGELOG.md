# Criminal Empire Online v0.7.4 — Global UX, Notifications & Outcome Focus Polish

## v0.7.4 highlights

- Added global notifications, toast messages, notification drawer, notification bell, and outcome focus reports.
- Added dimmed important action reports for crime, quick crime, dirty job, travel/explore, shop, inventory/loadout, heat, and police-style outcomes.
- Added reusable `AppTabs`, loading, empty, error, warning, stat delta, next-action, and outcome badge components.
- Added frontend API notification adapter so structured action responses become consistent reports.
- Added backend `OutcomePayloadService` and structured outcome payloads for key action responses.
- Improved accessibility with dialog role, focus management, Escape close, click-outside dismiss for safe reports, and aria-live notification announcements.
- Added v0.7.4 tests and refreshed documentation.

# Criminal Empire Online v0.7.3 — Loadout UX & Carry Inventory Polish

## v0.7.3 highlights

- Reworked Inventory / Loadouts into a portrait-driven Loadout Builder.
- Added boss/crew character selector cards with status, health, heat, equipped count, carried count, and warning count.
- Combined selected-character equipment slots, carried inventory, and owned item compatibility pool in one tab.
- Added item compatibility data and clearer role labels for equipped gear, carry tools, consumables, crime utility, task items, and storage-only items.
- Added clearer equip/unequip/carry/remove flows without switching away from the selected character.
- Added backend workspace endpoint and item role/carry-purpose seed metadata.
- Added v0.7.3 tests and refreshed documentation.

# Criminal Empire Online v0.7.2 — Inventory Loadout UX & Equipment Visibility Hotfix

This patch builds on **v0.7 — UX Navigation & Inventory Loadout Expansion** and focuses on fixing the rough UI reported during review.

## v0.7.2 highlights

- Rebuilt the Inventory / Loadouts **Owned gear** view as polished item cards instead of a raw table plus duplicated cards.
- Added a clear selected-crew banner and selector so equip/carry actions say exactly which crew member will receive the item.
- Equipped items now visually appear in the loadout slot board with item thumbnails, including boots, tools, and weapons.
- Carried items now visually appear in the carried inventory panel with thumbnails and carry-unit text.
- Item effects now render as readable badges instead of raw JSON/string fragments.
- Loadout equip now supports both item definitions and owned weapons through the v0.7 loadout endpoint.
- Heat & Police now renders only the selected subtab; Recent Heat Logs no longer appear as a giant wall on the main page.
- Preserved v0.7 backend loadout APIs, dirty-money shop payments, map-first shops, tutorial fix, and Street Job NPC assignment requirements.

## Install/update

```bash
php backend/database/migrate.php
cd frontend
npm install
npm run build
```

---

# Criminal Empire Online v0.7 — UX Navigation & Inventory Loadout Expansion

This update builds on **v0.6.5.1 — Map Shop UX & Navigation Hotfix** and treats the larger UX/loadout scope as a major v0.7 milestone.

## v0.7 highlights

- Added compact categorized navigation with dropdown groups and a mobile quick menu.
- Added admin/log subtabs and backend log pagination capped at 30 records per page.
- Reorganized Heat & Police, Dirty Jobs, and Warehouse pages with task-focused subtabs.
- Expanded Inventory into character loadouts, owned item management, item effects, and paginated inventory logs.
- Added character equipment slots: head, torso, legs, boots, hands, primary weapon, sidearm, melee, tool, utility slots, bag, armor, and disguise.
- Added carried inventory support with carry units, item size, capacity, warnings, and loadout score summaries.
- Added structured item properties and effects for stealth, intimidation, protection, carry capacity, police suspicion, mobility, evidence safety, and utility.
- Added dirty-money payment support for shady/black-market shops while legal shops reject dirty money by default.
- Preserved map-first shops, clickable shop markers, tutorial restart fix, and Street Job NPC assignment requirement.

## Install/update

```bash
php backend/database/migrate.php
cd frontend
npm install
npm run build
```


# Criminal Empire Online v0.6.5.1 — Map Shop UX & Navigation Hotfix

- Added map-based shops and dealers tied to world-map hotspots.
- Inventory now focuses on owned-item management instead of acting as the global equipment shop.
- Added `backend/app/Config/ShopConfig.php` for config-driven item availability, legal/restricted/black-market/future-only states, prices, requirements, and source hints.
- Added shop tables for locations, catalog stock, and transaction history.
- Added shop buy/sell API endpoints with backend validation for local presence, cash, stock, requirements, disabled items, and shop category rules.
- Added stock/restock support and map hotspot shop previews.
- Added missing-item shop source hints for Quick Crimes and Dirty Jobs.
- Added frontend Shops page and shop cards, stock badges, requirement badges, local-presence notices, and transaction panels.
- Added tutorial/help copy explaining shops vs Inventory.
- Added v0.6.5 tests and documentation.

## 2026-06-19 — Criminal Empire Online v0.6.4 — World Tutorial & Player Guidance Update

- Bumped backend and frontend version metadata to `0.6.4` with release title `Criminal Empire Online v0.6.4 — World Tutorial & Player Guidance Update`.
- Reworked tutorial flow into a versioned module/step system covering dashboard basics, world map, travel, local presence, hotspot exploration, quick crimes, Dirty Jobs, recruitment, crew equipment, Heat & Police, territories, warehouse/storage, XP/skill progression, boss character, and succession.
- Added a short `World Systems Update` tutorial for existing players who completed older tutorial versions, so they are not forced through the full new-player flow again.
- Added backend tutorial objective validation through `TutorialObjectiveValidator`; frontend cannot simply post a fake `complete_step=true` flag.
- Added tutorial progress/version fields, per-step progress, objective event logging, contextual help tip state, guide sections, and seeded tutorial/help content.
- Added contextual help panels for major pages and a reopenable Guide page.
- Added modest once-only tutorial rewards and preserved starter economy balance.
- Added new API endpoints including `GET /api/tutorial/current`, `GET /api/tutorial/steps`, `POST /api/tutorial/objective`, `GET /api/tutorial/guide`, `GET /api/help/tips`, and help-tip dismiss/reopen routes.
- Added v0.6.4 tests and documentation.
- Preserved v0.6.3.1 Street Job NPC assignment hotfix: Street Jobs still require at least one assigned active real NPC crew member.

## 2026-06-19 — Criminal Empire Online v0.6.3.1 — Street Job NPC Assignment Hotfix

- Street Jobs now require at least one assigned active NPC crew member before they can start.
- Backend validation rejects empty assignments and boss/fake crew ids such as `0` for Street Jobs.
- Street Jobs list payload now exposes minimum NPC assignment requirements and assignable crew count.
- Updated the Street Jobs page to hide the boss from the assignment picker, show required NPC crew messaging, and disable Start until enough real crew are selected.
- Added v0.6.3.1 tests and refreshed version metadata.

## 2026-06-19 — Criminal Empire Online v0.6.3 — Meaningful Travel & Local Presence

- Travel now unlocks local actions instead of only changing a stored location.
- Added travel costs, route risk previews, arrival events, travel history, and local presence tracking.
- Added `Travel & Explore`, which travels first and only explores after a successful local arrival.
- Backend local presence checks now protect selected Quick Crimes, Dirty Jobs, and hotspot exploration.
- Hotspot panels and local activity panels now explain what travel unlocks, what can be viewed remotely, and what requires being there.
- Dashboard now shows the player’s current region/hotspot and local risk context.
- Added v0.6.3 migration, seed data, API routes, service logic, frontend UI updates, tests, and documentation.

## 2026-06-19 — Criminal Empire Online v0.6.1.2 — Dirty Job Boss Support

- Added boss support to Dirty Job assignments so the boss can now take a role beside crew members in the Dirty Jobs tab.
- Dirty Job backend assignment, validation, active-run checks, and outcome handling now recognize the boss as a real actor instead of requiring a `player_gang_members` row.
- Updated the Dirty Jobs role picker to show the boss again and count boss assignments correctly when saving and executing a run.

## 2026-06-19 — Criminal Empire Online v0.6.1.1 — Crimes Tab SQL Hotfix

- Fixed a MySQL `DISTINCT` plus `ORDER BY` compatibility error that could break the Crimes tab when location-aware quick crimes were loaded.
- Quick crime listing now keeps hotspot ordering while deduplicating templates safely in PHP instead of relying on a SQL pattern rejected by stricter MySQL modes.

## 2026-06-19 — Criminal Empire Online v0.6.1 — Map Gameplay Integration

- Added location-aware Quick Crime rules, Dirty Job rules, local activity endpoints, and hotspot exploration.
- Updated map hotspot panels to show real activity groups, previews, territory/risk effects, and contextual action buttons.
- Updated Crimes and Dirty Jobs pages to read region/location query context from map buttons.


## 2026-06-19 — Criminal Empire Online v0.6 — Game Map & Territories

- Added Grimwater County as the fictional world-map setting name.
- Added interactive World Map and Location Map pages.
- Added local WebP/SVG map assets and safe placeholder fallbacks.
- Added world regions, hotspots, map activities, territory map links, and user current-location/travel state.
- Added backend world-map and travel APIs plus read-only admin map overview endpoints.
- Added map components, risk badges, tooltips, legends, travel panels, and navigation entry.
- Added v0.6 tests and documentation.

## 2026-06-19 — Criminal Empire Online v0.5.1.3 — Dirty Job Crew Requirement Hotfix

- Hotfixed Dirty Jobs so every job now requires at least one assigned crew member before execution.
- Added backend enforcement plus frontend warnings and button disabling for zero-assignment runs.
- Fixed a Dirty Jobs assignment bug where invalid non-crew selections could make role saves appear to fail and leave the run showing missing crew.
- Dirty Jobs now only count real assigned crew members in the role picker and save payload.

## 2026-06-19 — Criminal Empire Online v0.5.1.2 — Admin Heat Reset

- Added an admin-only player tool to set a selected user's heat back to `0`.
- Clearing heat now resets the user's legacy heat, boss personal heat, gang heat, and crew-member personal heat from the Admin panel.

## 2026-06-19 — Criminal Empire Online v0.5.1.1 — Boss Name Setup

- New registrations must provide boss first name and surname, which are stored as the initial boss identity instead of falling back to the username.
- Existing accounts can rename the boss once only if they still use the original default boss name.
- Added backend boss rename endpoint and Heat & Police UI for one-time boss naming.

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

- Reworked heat into boss, crew, NPC, gang, and district pressure with heat logs and police investigations.
- Added Heat & Police page, reduction actions, idle daily decay, weekly quiet bonus, investigation pressure, boss profile, succession support, and one-time update notice modal.
- Added high-heat crew dismissal relief with revenge-risk events for furious dismissed NPCs.


## 2026-06-19 — Criminal Empire Online v0.4.2 — Fallback Street Actions

- Split the Crimes page into three subtabs: `Explore Leads`, `Quick Crimes & Street Actions`, and `Fallback Street Actions`.
- Added a 10-minute per-action cooldown to each fallback street action using server-side enforcement and UI countdowns.
- Kept the newer quick-crime content intact while leaving the legacy fallback list trimmed down for balance.
- Bumped application metadata to v0.4.2.

## 2026-06-19 — Criminal Empire Online v0.4.1 — Fallback Street Actions & Quick Crimes

- Added quick crimes and fallback street actions as a faster loop beside the v0.4 discovered crime system.
- Added item-tag requirements, level requirements, cooldowns, lightweight preparation, random events, result panels, XP, crew XP, rare skill progression logs, heat/repeat penalties, and API routes.
- Updated frontend Crimes page with a Quick Crimes section, requirement/missing-item display, cooldowns, event choices, result summaries, and quick action history.


## v0.4.0
- Implemented Crimes Expansion: discovery locations, player-specific crime opportunities, investigation/preparation/execution flow, event decisions, NPC relationships, NPC timeline memory, admin NPC browser, dead NPC watermark UI, and v0.4 equipment/image mappings.
- Added v0.3.6.5 asset primer: refreshed supplied crime/job/territory art and new item art for Screwdriver Set, First-Aid Kit, Surveillance Kit, Dark Clothing, Work Uniform, Vehicle Tools, and Duffel Bag.

## v0.4.0
- Refreshed territory, dirty job, and crime visual assets with the new supplied art set.
- Updated matching SVG wrappers for backward compatibility.

# Changelog

## 0.3.5 — 2026-06-18

### Criminal Empire Online v0.3.5 — Crew Portraits & Design Update

Added:

- Fifty stable crew portrait identities with optimized card and thumbnail assets
- Centralized five-stage age resolver for ages 16–70, with safe out-of-range behavior
- Strict male-to-male and female-to-female portrait assignment
- Portrait manifest, resolver, assignment, validation, fallback, and existing-NPC backfill services
- Game-world crew aging with stage-boundary history entries
- Redesigned crew overview, detailed profile, recruitment cards, status badges, skill grids, equipment displays, condition meters, and history timeline
- Responsive desktop, tablet, and mobile crew layouts
- Portrait validation, backfill, stage synchronization, and year-processing commands
- v0.3.5 unit and source-contract tests

Changed:

- Crew and recruitment API payloads now include backend-resolved portrait, life-stage, role, reputation, and experience presentation metadata
- Existing NPCs retain all identity, stats, traits, finances, equipment, history, and gang relationships during portrait backfill and aging
- Missing life-stage art uses the same identity's adult portrait rather than a random replacement face

Artwork status:

- 50 portrait identities are present
- 50 adult-stage WebP assets and 50 thumbnails are present
- 0 sets currently contain all five matching age-stage portraits
- 200 final life-stage assets remain to be supplied

## 0.3.0 — 2026-06-17

### Criminal Empire Online v0.3 — Dirty Jobs Expansion

Added:

- Persistent, backend-validated ten-step tutorial
- Dirty Job templates, opportunities, preparation, role assignment, execution, decisions, and outcomes
- Structured equipment definitions, loadouts, condition, durability, job damage, and loss
- Crew history, injuries, arrests, recovery, dismissal, and delayed world return
- Extensible building/property model and purchasable warehouses
- Transactional item, weapon, drug, asset, and vehicle storage
- Warehouse upgrades, security, operating costs, and storage logs
- NPC contacts and relationship-ready job-giver records
- Dirty Job, warehouse, tutorial, recovery, heat, and developer command support
- Unit, contract, and guarded MySQL integration test suites
- Split React feature pages and tutorial panel

Changed:

- Application version metadata updated to 0.3.0
- Existing compact PHP and React areas touched by the update were reformatted and separated for developer readability
- Existing weapons, drugs, inventory, crew, territories, and economy systems are reused rather than duplicated
- Existing users are not forced through the new tutorial and retain all balances and progression

## 0.2.0 — 2026-06-16

- Added the single-player Phase 1 and Phase 2 foundation
- Changed new-player starting cash to $500
- Added NPC beginner jobs, recruitment, My Gang, salaries, personal NPC money, economy ledger, and world commands

## 0.1.0

- Initial custom PHP/MySQL and React crime-game MVP
