<?php

namespace App\Services;

final class EquipmentSlotService
{
    public const SLOTS = ['head','torso','legs','boots','hands','primary_weapon','sidearm','melee','tool','utility_1','utility_2','bag','armor','disguise'];

    public function slots(): array
    {
        return self::SLOTS;
    }

    public function itemCanUseSlot(array $item, string $slot): bool
    {
        $allowed = $this->decode($item['allowed_slots'] ?? '[]');
        $legacySlot = (string) ($item['equipment_slot'] ?? '');
        return in_array($slot, self::SLOTS, true) && (in_array($slot, $allowed, true) || $legacySlot === $slot || $allowed === []);
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
