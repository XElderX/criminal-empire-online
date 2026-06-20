<?php

namespace App\Services;

use App\Core\Database;

// v0.7 effect keys: stealth_bonus intimidation_bonus police_suspicion_bonus evidence_risk_multiplier witness_identification_multiplier forced_entry_bonus injury_reduction carry_capacity_bonus vehicle_crime_bonus.
final class ItemEffectService
{
    public function definitions(): array
    {
        $rows = Database::pdo()->query('SELECT code, name, category, equipment_slot, allowed_slots, item_tags, item_effects, effects, size_class, carry_units, legality, visible_illegal, concealment_rating FROM item_definitions WHERE active = 1 ORDER BY category, name')->fetchAll();
        foreach ($rows as &$row) {
            $row['allowed_slots'] = $this->decode($row['allowed_slots'] ?? '[]');
            $row['item_tags'] = $this->decode($row['item_tags'] ?? '[]');
            $row['item_effects'] = array_merge($this->decode($row['effects'] ?? '{}'), $this->decode($row['item_effects'] ?? '{}'));
        }
        return ['data' => $rows];
    }

    public function effectsForItem(array $item): array
    {
        return array_merge($this->decode($item['effects'] ?? '{}'), $this->decode($item['item_effects'] ?? '{}'));
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
