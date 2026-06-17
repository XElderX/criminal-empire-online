<?php

namespace App\Services;

use App\Core\Database;

final class EquipmentEffectService
{
    public function loadForMembers(array $memberIds): array
    {
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));

        if ($memberIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
        $sql = <<<SQL
            SELECT
                equipment.id AS crew_equipment_id,
                equipment.gang_member_id,
                equipment.asset_type,
                equipment.asset_id,
                equipment.equipment_slot,
                equipment.durability,
                item.name AS item_name,
                item.effects AS item_effects,
                weapon.name AS weapon_name,
                weapon.effects AS weapon_effects
            FROM crew_equipment equipment
            LEFT JOIN item_definitions item
                ON equipment.asset_type = 'item'
                AND item.id = equipment.asset_id
            LEFT JOIN weapons weapon
                ON equipment.asset_type = 'weapon'
                AND weapon.id = equipment.asset_id
            WHERE equipment.gang_member_id IN ({$placeholders})
            ORDER BY equipment.gang_member_id, equipment.equipment_slot
        SQL;

        $statement = Database::pdo()->prepare($sql);
        $statement->execute($memberIds);

        $equipment = $statement->fetchAll();

        foreach ($equipment as &$entry) {
            $effectsJson = $entry['asset_type'] === 'item'
                ? $entry['item_effects']
                : $entry['weapon_effects'];

            $entry['name'] = $entry['asset_type'] === 'item'
                ? $entry['item_name']
                : $entry['weapon_name'];

            $entry['effects'] = $this->decodeEffects($effectsJson);
        }

        return $equipment;
    }

    public function aggregate(array $equipment): array
    {
        $totals = [];

        foreach ($equipment as $entry) {
            $effects = $entry['effects'] ?? [];

            foreach ($effects as $effect => $value) {
                if (!is_numeric($value)) {
                    continue;
                }

                $totals[$effect] = ($totals[$effect] ?? 0) + (float) $value;
            }
        }

        return $totals;
    }

    private function decodeEffects(mixed $effects): array
    {
        if (is_array($effects)) {
            return $effects;
        }

        if (!is_string($effects) || $effects === '') {
            return [];
        }

        $decoded = json_decode($effects, true);

        return is_array($decoded) ? $decoded : [];
    }
}
