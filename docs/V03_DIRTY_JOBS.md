# Criminal Empire Online v0.3 — Dirty Jobs Expansion

## Gameplay loop

1. A new player starts with $500 and an active tutorial.
2. The player completes safe starter jobs and a first illegal action.
3. The player recruits and equips a street-level crew member.
4. NPC contacts provide a bounded set of expiring Dirty Job opportunities.
5. The player accepts an operation, performs preparation, assigns crew roles, and verifies carried equipment.
6. Execution uses crew skills, traits, morale, health, preparation, equipment, district police presence, current heat, and controlled randomness.
7. The operation may require a narrative decision before it resolves.
8. The backend grants rewards once and applies heat, injury, arrest, equipment, contact, and crew-history consequences.
9. The player saves toward a warehouse for contraband, equipment, drugs, stolen assets, and vehicles.

## Tutorial

The persistent tutorial contains ten steps:

1. Welcome to the City
2. Earn Your First Money
3. Your First Illegal Job
4. Recruit Your First Crew Member
5. Understand Your Crew
6. Equip Basic Gear
7. Prepare a Dirty Job
8. Execute the Operation
9. Heat and Consequences
10. The Warehouse

Gameplay steps are validated from backend records. The execution step counts participation whether the operation succeeds or fails. Tutorial rewards can only be claimed once. Skipping requires confirmation and does not grant completion rewards.

## Dirty Job lifecycle

```text
available
  -> accepted
  -> preparing
  -> ready
  -> executing
  -> awaiting_decision (when the template has a decision event)
  -> completed | partially_completed | failed
```

Expired opportunities cannot be accepted. Resolved operations cannot pay rewards twice. Crew and selected equipment are released or updated during resolution.

## Initial categories and content

- Theft: car lamps and shoplifted electronics
- Burglary: apartment burglary and garage-parts raid
- Vehicle crime: delivery-van theft
- Collection/intimidation: rival payment collection
- Production: fictional warehouse grow cycle

The production operation is intentionally abstract. It uses invented supply names and game-level stages without real cultivation measurements, environmental settings, chemical instructions, or operational guidance.

## Preparation actions

Template-defined preparation options can cost money, energy, or time and can modify:

- Success chance
- Heat
- Injury risk
- Reward modifier
- Information shown to the player

Preparation bonuses are capped and cannot create guaranteed success.

## Crew roles

Supported roles include:

- Leader
- Driver
- Lookout
- Enforcer
- Thief
- Infiltrator
- Planner
- Weapons specialist
- Courier
- Grow operator
- Warehouse handler

Each role references structured stat names in `GameConfig::crewRoleDefinitions()`. A member cannot fill incompatible duplicate roles in the same operation and cannot be assigned while busy, arrested, dismissed, missing, dead, or insufficiently recovered.

## Success calculation

`DirtyJobCalculator` starts from the template’s base success rate and applies:

- Limited player intelligence contribution
- Role-weighted crew contribution
- Trait modifiers
- Structured equipment effects
- Preparation effects
- Decision effects
- District police penalty
- Current player heat penalty
- Template difficulty penalty

The final chance is bounded from 5% to 95%.

Outcome bands are:

- Critical success
- Success
- Partial success
- Failure
- Critical failure

Critical failure is uncommon but can produce stronger heat, injury, arrest, evidence, morale, and equipment consequences. Early-game outcomes are bounded so one operation does not permanently destroy a save.

## Equipment

General items and weapons retain separate domain inventories but share a coherent crew-loadout link. Supported slots include weapon, sidearm, melee, tool, armor, clothing, utility, and bag.

Structured effects can influence burglary, forced entry, stealth entry, driving, production, intimidation, evidence, heat, carrying capacity, injury protection, and reward capacity.

Rules include:

- Ownership validation
- Slot validation
- One owned item cannot be equipped by multiple members
- Condition cannot fall below zero
- Failure can damage or remove equipment once
- Dismissal returns player-owned equipment by removing the loadout link
- Warehouse items must be withdrawn before they can be carried on a Dirty Job

## Warehouse

The warehouse is the first purchasable building. Listings are sold by NPC property brokers and include district, purchase price, capacities, security, operating cost, condition, visibility, and description.

Storage supports:

- General items and stolen goods
- Tools
- Weapons
- Drugs
- Vehicle parts
- Abstract production supplies
- Full vehicles in vehicle slots

Transfers use database transactions and ownership checks. Capacity, reserved quantity, negative quantity, duplicate vehicle storage, and cross-player access are rejected by the backend.

Initial upgrades include stronger locks, an alarm, and a hidden compartment. Weekly operating costs can accumulate debt and reduce security but do not immediately confiscate the property after one missed payment.

## Crew dismissal and history

Crew dismissal:

- Requires a reason and frontend confirmation
- Is blocked during an active assignment
- Does not refund recruitment fees
- Returns player-owned equipment to inventory
- Preserves unpaid salary and historical identity
- Marks the member dismissed instead of deleting the record
- Records time served, job totals, loyalty, relationship, and reason
- Returns the NPC to delayed world recruitment availability

If rehired later, the existing member record is reactivated, preserving character history rather than inserting a duplicate person.

## Heat and consequences

Dirty Job heat is affected by the operation, district police presence, current heat, equipment exposure, preparation, decisions, and outcome severity.

Heat can decay through world processing or be reduced through the lay-low action. Higher heat reduces future success and supports later police surveillance, warehouse investigations, and raids.
