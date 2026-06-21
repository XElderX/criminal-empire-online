<?php

namespace App\Services;

use App\Core\Database;

/**
 * v0.7.3 character-centered loadout workspace.
 *
 * This service does not replace the v0.7 inventory/loadout model. It gathers the
 * selected character, portrait data, equipped gear, carried task items, and owned
 * item compatibility in one backend-authoritative payload for the frontend builder.
 */
final class LoadoutWorkspaceService
{
    public function workspace(array $user, array $query = []): array
    {
        $characters = $this->characters($user);
        $requestedType = (string) ($query['character_type'] ?? 'boss');
        $requestedId = (int) ($query['character_id'] ?? 0);
        $selectedSlot = $this->cleanSlot((string) ($query['selected_slot'] ?? ''));

        $selected = $this->findCharacter($characters, $requestedType, $requestedId)
            ?? $characters[0]
            ?? null;

        if ($selected === null) {
            $selected = $this->bossCharacter($user);
            $characters = [$selected];
        }

        $loadout = (new CharacterLoadoutService())->forCharacter(
            $user,
            (string) $selected['character_type'],
            (int) $selected['character_id']
        );

        return [
            'version' => '0.7.3',
            'selected_slot' => $selectedSlot,
            'characters' => $characters,
            'selected_character' => $selected,
            'loadout' => $loadout,
            'owned_items' => $this->ownedItems($user, $selected, $loadout, $selectedSlot),
            'item_role_guide' => $this->itemRoleGuide(),
            'warnings' => $this->groupWarnings($loadout),
        ];
    }

    public function characters(array $user): array
    {
        $characters = [$this->bossCharacter($user)];
        $crewResponse = (new CharacterLoadoutService())->crew($user);

        foreach (($crewResponse['data'] ?? []) as $member) {
            $loadout = $member['loadout'] ?? [];
            $characters[] = $this->summarizeCharacter('crew', (int) $member['id'], $member, $loadout);
        }

        return $characters;
    }

    private function bossCharacter(array $user): array
    {
        $boss = (new BossCharacterService())->asCrewMember($user);
        $loadout = (new CharacterLoadoutService())->boss($user);

        return $this->summarizeCharacter('boss', 0, $boss, $loadout);
    }

    private function summarizeCharacter(string $type, int $id, array $character, array $loadout): array
    {
        $equipped = $loadout['equipped'] ?? [];
        $carried = $loadout['carried'] ?? [];
        $warnings = $loadout['warnings'] ?? [];
        $usedCarry = (float) ($loadout['used_carry_units'] ?? 0);
        $capacity = (float) ($loadout['carry_capacity_units'] ?? 5);
        $firstName = (string) ($character['first_name'] ?? 'Boss');
        $lastName = (string) ($character['last_name'] ?? '');
        $nickname = $character['nickname'] ?? null;

        return [
            'key' => "{$type}:{$id}",
            'character_type' => $type,
            'character_id' => $id,
            'id' => $id,
            'npc_id' => $character['npc_id'] ?? null,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'nickname' => $nickname,
            'display_name' => $this->displayName($firstName, $lastName, $nickname),
            'gender' => $character['gender'] ?? null,
            'age' => $character['age'] ?? null,
            'role' => $character['role'] ?? ['name' => $type === 'boss' ? 'Boss' : 'Crew'],
            'role_code' => $character['role_code'] ?? ($type === 'boss' ? 'leader' : 'crew'),
            'status' => $character['status'] ?? 'active',
            'health' => (int) ($character['health'] ?? 100),
            'max_health' => (int) ($character['max_health'] ?? 100),
            'morale' => $character['morale'] ?? null,
            'loyalty' => $character['loyalty'] ?? null,
            'personal_heat' => (int) ($character['personal_heat'] ?? 0),
            'portrait' => $character['portrait'] ?? null,
            'portrait_set_key' => $character['portrait_set_key'] ?? null,
            'current_assignment_type' => $character['current_assignment_type'] ?? null,
            'current_assignment_id' => $character['current_assignment_id'] ?? null,
            'equipped_count' => count($equipped),
            'carried_count' => array_sum(array_map(static fn (array $item): int => (int) ($item['quantity'] ?? 1), $carried)),
            'used_carry_units' => $usedCarry,
            'carry_capacity_units' => $capacity,
            'loadout_warning_count' => count($warnings),
            'loadout_status' => $this->loadoutStatus($warnings, $usedCarry, $capacity),
        ];
    }

    private function ownedItems(array $user, array $selected, array $loadout, string $selectedSlot): array
    {
        $inventory = (new ItemService())->inventory($user);
        $assets = [];

        foreach (($inventory['items'] ?? []) as $item) {
            $item['asset_type'] = 'item';
            $assets[] = $item;
        }
        foreach (($inventory['weapons'] ?? []) as $weapon) {
            $weapon['asset_type'] = 'weapon';
            $assets[] = $weapon;
        }

        $equippedBy = $this->equippedByMap((int) $user['id']);
        $carriedBy = $this->carriedByMap((int) $user['id']);
        $slots = (new EquipmentSlotService())->slots();

        return array_map(function (array $asset) use ($selected, $loadout, $selectedSlot, $equippedBy, $carriedBy, $slots): array {
            return $this->enrichAsset($asset, $selected, $loadout, $selectedSlot, $equippedBy, $carriedBy, $slots);
        }, $assets);
    }

    private function enrichAsset(
        array $asset,
        array $selected,
        array $loadout,
        string $selectedSlot,
        array $equippedBy,
        array $carriedBy,
        array $allSlots
    ): array {
        $assetType = (string) ($asset['asset_type'] ?? (!empty($asset['class']) ? 'weapon' : 'item'));
        $assetId = (int) ($asset['id'] ?? 0);
        $assetKey = "{$assetType}:{$assetId}";
        $compatibleSlots = $this->compatibleSlots($asset, $assetType, $allSlots);
        $recommendedSlot = $this->recommendedSlot($asset, $assetType, $compatibleSlots);
        $available = (int) ($asset['available_quantity'] ?? $asset['quantity'] ?? 0);
        $effects = $this->effects($asset);
        $tags = $this->tags($asset);
        $itemRole = $this->itemRole($asset, $assetType, $tags, $effects);
        $carryRole = $this->carryRole($asset, $assetType, $tags, $itemRole);
        $isBroken = isset($asset['durability']) && (int) $asset['durability'] <= 0;
        $selectedSlotCompatible = $selectedSlot === '' || in_array($selectedSlot, $compatibleSlots, true);
        $canEquip = $available > 0 && !$isBroken && $this->canEquipAsset($asset, $assetType) && $selectedSlotCompatible;
        $canCarry = $available > 0 && !$isBroken && $this->canCarryAsset($asset, $assetType, $itemRole, $carryRole);
        $equippedHere = $this->equippedHere($loadout, $assetType, $assetId);
        $carriedHere = $this->carriedHere($loadout, $assetType, $assetId);
        $unavailable = $this->unavailableReason($asset, $selectedSlot, $compatibleSlots, $available, $isBroken, $canEquip, $canCarry, $itemRole, $carryRole);

        return [
            ...$asset,
            'asset_type' => $assetType,
            'effects' => $effects,
            'item_effects' => $effects,
            'item_tags' => $tags,
            'item_role' => $itemRole,
            'carry_role' => $carryRole,
            'role_label' => $this->roleLabel($itemRole, $carryRole, $asset),
            'quantity_available' => $available,
            'quantityAvailable' => $available,
            'canEquip' => $canEquip,
            'canCarry' => $canCarry,
            'canUseForSelectedCharacter' => $canEquip || $canCarry || $equippedHere || $carriedHere,
            'compatibleSlots' => $compatibleSlots,
            'compatible_slots' => $compatibleSlots,
            'recommendedSlot' => $recommendedSlot,
            'recommended_slot' => $recommendedSlot,
            'selectedSlotCompatible' => $selectedSlotCompatible,
            'unavailableReason' => $unavailable,
            'unavailable_reason' => $unavailable,
            'currentlyEquippedBy' => $equippedBy[$assetKey] ?? [],
            'currentlyCarriedBy' => $carriedBy[$assetKey] ?? [],
            'equippedBySelected' => $equippedHere,
            'carriedBySelected' => $carriedHere,
            'requiresTransferFromWarehouse' => ($asset['current_location_type'] ?? '') === 'warehouse',
            'isBroken' => $isBroken,
            'isReserved' => (bool) ($asset['is_reserved'] ?? false),
            'benefits' => $this->benefits($effects, $tags),
            'tradeoffs' => $this->tradeoffs($effects, $tags, $asset),
            'carryPurpose' => $this->carryPurpose($carryRole, $tags, $asset),
        ];
    }

    private function compatibleSlots(array $asset, string $assetType, array $allSlots): array
    {
        if ($assetType === 'weapon') {
            $class = strtolower((string) ($asset['class'] ?? ''));
            if (str_contains($class, 'pistol') || str_contains($class, 'revolver')) return ['sidearm'];
            if (str_contains($class, 'knife') || str_contains($class, 'baton') || str_contains($class, 'melee')) return ['melee'];
            return ['primary_weapon'];
        }

        $allowed = $asset['allowed_slots'] ?? [];
        if (is_string($allowed)) {
            $decoded = json_decode($allowed, true);
            $allowed = is_array($decoded) ? $decoded : [];
        }
        $allowed = array_values(array_filter(array_map('strval', (array) $allowed)));
        if ($allowed !== []) {
            return array_values(array_intersect($allowed, $allSlots));
        }

        $legacy = strtolower((string) ($asset['equipment_slot'] ?? ''));
        if ($legacy !== '' && in_array($legacy, $allSlots, true)) return [$legacy];

        return [];
    }

    private function recommendedSlot(array $asset, string $assetType, array $compatibleSlots): ?string
    {
        if ($compatibleSlots !== []) {
            return $compatibleSlots[0];
        }
        return $assetType === 'weapon' ? 'sidearm' : null;
    }

    private function canEquipAsset(array $asset, string $assetType): bool
    {
        if ($assetType === 'weapon') return true;
        return (int) ($asset['is_equippable'] ?? 1) === 1 && $this->compatibleSlots($asset, $assetType, (new EquipmentSlotService())->slots()) !== [];
    }

    private function canCarryAsset(array $asset, string $assetType, string $itemRole, string $carryRole): bool
    {
        if ($assetType !== 'item') return false;
        if ((int) ($asset['is_carryable'] ?? 1) !== 1) return false;
        if (in_array($itemRole, ['equipped_gear', 'armor', 'weapon'], true) && !in_array($carryRole, ['carry_tool', 'task_item', 'consumable', 'crime_utility'], true)) {
            return false;
        }
        return !in_array($carryRole, ['equip_only', 'storage_only'], true);
    }

    private function unavailableReason(array $asset, string $selectedSlot, array $slots, int $available, bool $isBroken, bool $canEquip, bool $canCarry, string $itemRole, string $carryRole): ?string
    {
        if (($asset['current_location_type'] ?? '') === 'warehouse') return 'Stored in warehouse; transfer it before using in a loadout.';
        if ($isBroken) return 'Broken gear cannot be equipped or carried until repaired.';
        if ($available < 1) return 'All owned copies are already equipped or carried.';
        if ($selectedSlot !== '' && !in_array($selectedSlot, $slots, true)) return 'Not compatible with the selected slot.';
        if (!$canEquip && !$canCarry) return $carryRole === 'equip_only' ? 'This is equipment gear; use an equipment slot instead of carried inventory.' : 'This item is not usable by the selected character right now.';
        return null;
    }

    private function equippedHere(array $loadout, string $assetType, int $assetId): ?array
    {
        foreach (($loadout['equipped'] ?? []) as $entry) {
            if (($entry['asset_type'] ?? 'item') === $assetType && (int) ($entry['id'] ?? 0) === $assetId) return $entry;
        }
        return null;
    }

    private function carriedHere(array $loadout, string $assetType, int $assetId): ?array
    {
        foreach (($loadout['carried'] ?? []) as $entry) {
            if (($entry['asset_type'] ?? 'item') === $assetType && (int) ($entry['id'] ?? 0) === $assetId) return $entry;
        }
        return null;
    }

    private function equippedByMap(int $userId): array
    {
        $rows = Database::pdo()->prepare(
            <<<'SQL'
                SELECT equipment.asset_type, equipment.asset_id, equipment.equipment_slot,
                       equipment.gang_member_id,
                       npc.first_name, npc.last_name, npc.nickname
                FROM crew_equipment equipment
                LEFT JOIN player_gang_members member ON member.id = equipment.gang_member_id
                LEFT JOIN npcs npc ON npc.id = member.npc_id
                WHERE equipment.user_id = ?
            SQL
        );
        $rows->execute([$userId]);
        $map = [];
        foreach ($rows->fetchAll() as $row) {
            $key = $row['asset_type'] . ':' . $row['asset_id'];
            $map[$key][] = [
                'character_type' => $row['gang_member_id'] === null ? 'boss' : 'crew',
                'character_id' => $row['gang_member_id'] === null ? 0 : (int) $row['gang_member_id'],
                'display_name' => $row['gang_member_id'] === null ? 'Boss' : $this->displayName((string) $row['first_name'], (string) $row['last_name'], $row['nickname'] ?? null),
                'slot' => $row['equipment_slot'],
            ];
        }
        return $map;
    }

    private function carriedByMap(int $userId): array
    {
        if (!$this->tableExists('character_carry_items')) return [];
        $rows = Database::pdo()->prepare(
            <<<'SQL'
                SELECT carry.asset_type, carry.asset_id, carry.quantity, carry.character_type, carry.character_id,
                       npc.first_name, npc.last_name, npc.nickname
                FROM character_carry_items carry
                LEFT JOIN player_gang_members member ON carry.character_type = 'crew' AND member.id = carry.character_id
                LEFT JOIN npcs npc ON npc.id = member.npc_id
                WHERE carry.user_id = ?
            SQL
        );
        $rows->execute([$userId]);
        $map = [];
        foreach ($rows->fetchAll() as $row) {
            $key = $row['asset_type'] . ':' . $row['asset_id'];
            $map[$key][] = [
                'character_type' => $row['character_type'],
                'character_id' => (int) $row['character_id'],
                'display_name' => $row['character_type'] === 'boss' ? 'Boss' : $this->displayName((string) $row['first_name'], (string) $row['last_name'], $row['nickname'] ?? null),
                'quantity' => (int) $row['quantity'],
            ];
        }
        return $map;
    }

    private function effects(array $asset): array
    {
        return array_merge($this->decode($asset['effects'] ?? []), $this->decode($asset['item_effects'] ?? []));
    }

    private function tags(array $asset): array
    {
        return array_values(array_map('strval', $this->decode($asset['item_tags'] ?? [])));
    }

    private function itemRole(array $asset, string $assetType, array $tags, array $effects): string
    {
        if ($assetType === 'weapon') return 'weapon';
        $category = strtolower((string) ($asset['category'] ?? ''));
        if (in_array('medical', $tags, true) || isset($effects['first_aid_event_unlock'])) return 'consumable';
        if (in_array('task_item', $tags, true) || in_array($category, ['stolen_good', 'vehicle_part', 'production_supply'], true)) return 'task_item';
        if (in_array('bag', $tags, true) || $category === 'armor' || $category === 'clothing') return 'equipped_gear';
        if (in_array('weapon', $tags, true) || $category === 'melee_weapon') return 'weapon';
        if ($category === 'tool') return 'tool';
        if ($category === 'utility') return 'utility';
        return 'owned_item';
    }

    private function carryRole(array $asset, string $assetType, array $tags, string $itemRole): string
    {
        if ($assetType === 'weapon') return 'equip_only';
        if (($asset['is_storage_only'] ?? 0) == 1) return 'storage_only';
        if ($itemRole === 'consumable') return 'consumable';
        if ($itemRole === 'task_item') return 'task_item';
        if (in_array('contact_safety', $tags, true) || in_array('event_unlock', $tags, true) || in_array('low_light', $tags, true)) return 'crime_utility';
        if ($itemRole === 'tool' || in_array('tool', $tags, true) || in_array('entry_tool', $tags, true) || in_array('stealth_entry', $tags, true)) return 'carry_tool';
        if ($itemRole === 'equipped_gear' || $itemRole === 'weapon') return 'equip_only';
        return 'carry_item';
    }

    private function roleLabel(string $itemRole, string $carryRole, array $asset): string
    {
        return match ($carryRole) {
            'consumable' => 'Consumable / carried item',
            'carry_tool' => 'Carry tool / crime-use item',
            'crime_utility' => 'Crime utility / carried item',
            'task_item' => 'Task item / loot',
            'equip_only' => 'Equipped gear',
            'storage_only' => 'Storage only',
            default => match ($itemRole) {
                'weapon' => 'Weapon gear',
                'equipped_gear' => 'Equipped gear',
                'tool' => 'Tool gear',
                default => 'Owned item',
            },
        };
    }

    private function benefits(array $effects, array $tags): array
    {
        $benefits = [];
        $labels = [
            'evidence_risk_multiplier' => 'Improves evidence safety',
            'witness_identification_multiplier' => 'Reduces witness identification',
            'stealth_bonus' => 'Improves stealth',
            'forced_entry_bonus' => 'Helps forced entry',
            'intimidation_bonus' => 'Improves intimidation',
            'injury_reduction' => 'Reduces injury severity',
            'carry_capacity_bonus' => 'Increases carry capacity',
            'vehicle_crime_bonus' => 'Helps vehicle actions',
            'first_aid_event_unlock' => 'Unlocks first-aid choices',
            'contact_exposure_reduction' => 'Protects contact exposure',
        ];
        foreach ($labels as $key => $label) {
            if (array_key_exists($key, $effects)) $benefits[] = $label . ' (' . $effects[$key] . ')';
        }
        if (in_array('medical', $tags, true)) $benefits[] = 'Useful as carried medical gear.';
        return array_values(array_unique($benefits));
    }

    private function tradeoffs(array $effects, array $tags, array $asset): array
    {
        $tradeoffs = [];
        foreach (['police_suspicion_bonus' => 'Raises police suspicion', 'noise_penalty' => 'Noisy during entry', 'mobility_penalty' => 'Reduces mobility', 'heat_multiplier' => 'Increases heat risk'] as $key => $label) {
            if (array_key_exists($key, $effects)) $tradeoffs[] = $label . ' (' . $effects[$key] . ')';
        }
        if ((int) ($asset['visible_illegal'] ?? 0) === 1) $tradeoffs[] = 'Visible illegal or suspicious if searched.';
        if (in_array('bulky', $tags, true)) $tradeoffs[] = 'Bulky item; hurts quiet movement.';
        return array_values(array_unique($tradeoffs));
    }

    private function carryPurpose(string $carryRole, array $tags, array $asset): string
    {
        return match ($carryRole) {
            'consumable' => 'Bring this for injuries, recovery events, or future consumable use.',
            'carry_tool' => 'Bring this as a task/crime tool when it is not equipped in the tool slot.',
            'crime_utility' => 'Bring this for event choices, contact safety, visibility, or task utility.',
            'task_item' => 'Carry this as loot, a delivery object, or future mission/task item.',
            'equip_only' => 'This belongs in an equipment slot rather than the carried inventory.',
            'storage_only' => 'This is mostly for storage or warehouse management.',
            default => 'Optional carried item; it uses carry capacity and may affect risk.',
        };
    }

    private function groupWarnings(array $loadout): array
    {
        $warnings = $loadout['warnings'] ?? [];
        return [
            'bonuses' => $this->scoreHighlights($loadout['scores'] ?? []),
            'tradeoffs' => array_values(array_filter($warnings, static fn (string $warning): bool => str_contains(strtolower($warning), 'suspicion') || str_contains(strtolower($warning), 'mobility'))),
            'problems' => array_values(array_filter($warnings, static fn (string $warning): bool => !str_contains(strtolower($warning), 'suspicion') && !str_contains(strtolower($warning), 'mobility'))),
        ];
    }

    private function scoreHighlights(array $scores): array
    {
        $highlights = [];
        foreach ($scores as $key => $value) {
            if ((int) $value >= 65) {
                $highlights[] = ucfirst(str_replace('_', ' ', (string) $key)) . ' is strong.';
            }
        }
        return $highlights;
    }

    private function itemRoleGuide(): array
    {
        return [
            ['label' => 'Equipped gear', 'description' => 'Clothing, armor, bags, and weapons worn in a specific slot. These give the main slot bonuses and visible-risk tradeoffs.'],
            ['label' => 'Carried inventory', 'description' => 'Tools, consumables, task items, and crime utility this character brings for jobs or events. It uses carry capacity.'],
            ['label' => 'Task / quest items', 'description' => 'Packages, documents, recovered loot, or future mission items. They usually cannot be worn.'],
        ];
    }

    private function findCharacter(array $characters, string $type, int $id): ?array
    {
        foreach ($characters as $character) {
            if (($character['character_type'] ?? '') === $type && (int) ($character['character_id'] ?? -1) === $id) return $character;
        }
        return null;
    }

    private function loadoutStatus(array $warnings, float $used, float $capacity): string
    {
        if ($used > $capacity) return 'overloaded';
        if ($warnings !== []) return 'warning';
        return 'ready';
    }

    private function cleanSlot(string $slot): string
    {
        $slot = trim($slot);
        return in_array($slot, (new EquipmentSlotService())->slots(), true) ? $slot : '';
    }

    private function displayName(string $first, string $last, mixed $nickname): string
    {
        $name = trim($first . ' ' . $last) ?: 'Unknown';
        return $nickname ? $name . ' “' . $nickname . '”' : $name;
    }

    private function tableExists(string $table): bool
    {
        $statement = Database::pdo()->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
        );
        $statement->execute([$table]);
        return (bool) $statement->fetchColumn();
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
