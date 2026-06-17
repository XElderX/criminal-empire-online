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
