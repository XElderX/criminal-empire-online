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
