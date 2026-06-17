# v0.3 entity relationship overview

This is a logical overview. The executable source of truth is the ordered SQL in `backend/database/migrations`.

```text
users
  ├── api_tokens
  ├── user_tutorial_progress
  │     └── tutorial_step_logs
  ├── job_runs
  ├── recruitment_candidates
  ├── player_gang_members ── npcs
  │     ├── crew_equipment
  │     └── crew_history
  ├── user_items ── item_definitions
  ├── user_weapons ── weapons
  ├── user_drugs ── drugs
  ├── dirty_job_opportunities ── dirty_job_templates
  │     └── dirty_job_runs
  │           ├── dirty_job_preparations
  │           ├── dirty_job_assignments ── player_gang_members
  │           └── dirty_job_equipment
  ├── contact_relationships ── npc_contacts ── npcs
  ├── player_buildings ── building_types
  │     ├── warehouse_storage
  │     ├── player_building_upgrades ── building_upgrades
  │     ├── storage_logs
  │     └── vehicles
  ├── economy_transactions
  ├── heat_actions
  └── audit_logs

property_listings
  ├── building_types
  ├── territories
  └── npcs (seller)

dirty_job_templates
  ├── npcs (default giver where configured)
  └── territories (district context through opportunities)
```

## Important ownership rules

- All tutorial progress, opportunities, runs, crew, inventory, buildings, vehicles, and storage are owned by a specific user.
- `player_gang_members.npc_id` remains unique so the same persistent NPC is reactivated when rehired instead of duplicated.
- A Dirty Job assignment uniquely reserves a member within one run.
- Crew equipment uniquely links one owned item to one active loadout.
- Warehouse transfers lock source and destination rows before changing quantities.
- Vehicle storage uses a unique vehicle row and one `warehouse_id` at a time.
- Property purchase changes a listing from available to sold inside the same transaction that creates the building and deducts cash.
