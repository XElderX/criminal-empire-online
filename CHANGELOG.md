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
