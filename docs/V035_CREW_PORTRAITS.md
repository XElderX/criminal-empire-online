# Criminal Empire Online v0.3.5 — Crew Portraits & Design Update

## Purpose

The portrait system gives every important NPC and crew member a stable visual identity. A person's face does not change on page reload, recruitment, dismissal, return to the NPC world, arrest, or rehiring. Only the resolved life-stage artwork changes as the NPC ages.

## Life stages

The backend is authoritative. `CrewAgeStageResolver` contains the only age-boundary rules:

| Stage key | Label | Age range |
|---|---|---:|
| `very_young` | Very Young | 16–24 |
| `young` | Young | 25–31 |
| `adult` | Adult | 32–40 |
| `mature` | Mature | 41–55 |
| `elder` | Elder | 56–70 |

NPCs below age 16 use the very-young visual fallback but cannot be recruited. NPCs above age 70 remain on the elder portrait and are not newly recruitable. Retirement logic can be expanded later.

## Stable identity and gender assignment

`PortraitAssignmentService` assigns `npcs.portrait_set_key` once. Repeated backfills do not replace an existing key.

Assignment rules:

1. Normalize the NPC's stored gender.
2. Load enabled portrait sets with the same gender.
3. Exclude identities already visible in the same recruitment refresh when possible.
4. Choose through the injectable random source.
5. Save the identity, stage cache, and focal point atomically.

Male NPCs are assigned only portrait sets whose manifest gender is `male`. Female NPCs are assigned only sets whose manifest gender is `female`. Unsupported or missing gender values are skipped and reported by the backfill command instead of receiving an incorrect portrait.

## Asset structure

```text
frontend/public/assets/crew/portraits/
├── fallback.svg
├── manifest.json
├── portrait-set-001/
│   ├── adult.webp
│   └── thumbs/
│       └── adult.webp
├── portrait-set-002/
│   └── ...
└── portrait-set-050/
    └── ...
```

The full expected structure for a completed identity is:

```text
portrait-set-001/
├── very_young.webp
├── young.webp
├── adult.webp
├── mature.webp
├── elder.webp
└── thumbs/
    ├── very_young.webp
    ├── young.webp
    ├── adult.webp
    ├── mature.webp
    └── elder.webp
```

Use WebP, a 4:5 crop, and predictable lower-case filenames. The overview cards load only the current thumbnail. Profile views load only the current larger image. No portrait images are imported into the JavaScript bundle.

## Manifest

Static portrait metadata is defined in:

- `backend/app/Config/CrewPortraitManifest.php`
- `frontend/public/assets/crew/portraits/manifest.json`

Each set includes:

- stable key
- developer label
- gender and presentation
- enabled status
- default stage
- focal point
- available asset and thumbnail paths
- generation notes

The PHP manifest is used by the API. The JSON manifest is a developer-facing asset inventory and can support future import tooling.

## Current supplied artwork

The supplied concept sheets contained 50 distinct people. They did not contain five age versions of the same 50 people.

Current repository contents:

- 50 actual portrait identities
- 50 adult-stage WebP images
- 50 adult-stage thumbnails
- 0 complete five-stage portrait sets
- 200 missing life-stage images

The resolver reports the requested stage but uses the same identity's adult asset when that stage is absent. This preserves identity and keeps the game usable without pretending that unrelated faces are age variants.

## API response

Crew and recruitment records include:

```json
{
  "portrait": {
    "identity_key": "portrait-set-017",
    "gender": "female",
    "stage": "mature",
    "stage_label": "Mature",
    "age_range": "41–55",
    "resolved_asset_stage": "adult",
    "url": "/assets/crew/portraits/portrait-set-017/adult.webp",
    "thumbnail_url": "/assets/crew/portraits/portrait-set-017/thumbs/adult.webp",
    "fallback_url": "/assets/crew/portraits/fallback.svg",
    "focal_x": 50,
    "focal_y": 42,
    "uses_fallback": false,
    "uses_stage_fallback": true
  }
}
```

`stage` is derived from the backend age. The frontend cannot submit or override it.

## Existing NPC backfill

After applying migration `004_crew_portraits_design.sql`, run:

```bash
cd backend
php commands/crew-portraits.php backfill
```

The command:

- selects only NPCs with no portrait identity
- preserves all existing NPC and crew data
- assigns matching-gender identities
- avoids repeated visible identities where possible
- reports records skipped because of missing or unsupported gender values
- can be run repeatedly without reassigning completed records

Inspect the result:

```bash
php commands/crew-portraits.php status
```

## Validation

Run without a database connection:

```bash
php commands/crew-portraits.php validate
```

Validation checks:

- portrait set key format
- duplicate keys
- gender metadata
- enabled set assets
- supported file extensions
- default-stage asset
- fallback image
- all five expected life stages

Missing final life-stage artwork is reported as warnings. Broken paths, invalid formats, bad default assets, and missing fallback artwork are errors.

## Aging

The migration introduces a singleton `world_state` and NPC age-processing fields. Run:

```bash
php commands/world.php process-year
```

or:

```bash
php commands/crew-portraits.php age-one-year
```

The process is transactional and uses the last processed game year to avoid double aging. It updates:

- NPC age
- cached portrait stage
- birth game year/day metadata
- last processed year
- crew history when a life-stage boundary is crossed

It does not reset or replace stats, traits, salary, personal money, equipment, injuries, loyalty, morale, history, or gang relationships.

To recalculate caches without advancing time:

```bash
php commands/crew-portraits.php sync-stages
```

## Adding a complete portrait set

1. Create the five full-size files and five thumbnails under the existing set folder.
2. Add all paths to both manifests.
3. Keep gender metadata unchanged for that identity.
4. Run validation.
5. Test ages at every stage boundary.

Example:

```bash
php commands/crew-portraits.php validate
php tests/v035_unit.php
php tests/v035_contract.php
```

## Frontend components

Reusable presentation lives under `frontend/src/features/crew/components`:

- `CrewPortrait`
- `CrewCard`
- `CrewProfile`
- `RecruitmentCard`
- `CrewStatusBadge`
- `CrewRoleBadge`
- `CrewSkillGrid`
- `CrewEquipmentGrid`
- `CrewTraitList`
- `CrewConditionMeters`
- `CrewExperienceBar`
- `CrewHistoryTimeline`

The My Crew page supports filters, sorting, grid/list modes, summary counts, empty states, loading states, and a detailed profile. Recruitment uses the same portrait identity and presentation model.
