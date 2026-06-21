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
        $recommended = $this->recommendedItemSlot($item);

        return in_array($slot, self::SLOTS, true)
            && (
                in_array($slot, $allowed, true)
                || $legacySlot === $slot
                || $recommended === $slot
                || $allowed === []
            );
    }

    public function recommendedItemSlot(array $item): string
    {
        $allowed = $this->decode($item['allowed_slots'] ?? '[]');
        if ($allowed !== []) {
            return (string) $allowed[0];
        }

        $legacySlot = strtolower(trim((string) ($item['equipment_slot'] ?? '')));
        if (in_array($legacySlot, self::SLOTS, true)) {
            return $legacySlot;
        }
        if ($legacySlot === 'weapon') {
            return 'primary_weapon';
        }

        $name = strtolower((string) ($item['name'] ?? ''));
        $category = strtolower((string) ($item['category'] ?? ''));
        $tags = array_map('strtolower', $this->decode($item['item_tags'] ?? '[]'));
        $haystack = trim(implode(' ', array_filter([$name, $category, implode(' ', $tags)])));

        if ($this->containsAny($haystack, ['boot', 'shoe'])) {
            return 'boots';
        }
        if ($this->containsAny($haystack, ['glove'])) {
            return 'hands';
        }
        if ($this->containsAny($haystack, ['mask', 'beanie', 'cap', 'helmet', 'hat', 'hood', 'face shield'])) {
            return 'head';
        }
        if ($this->containsAny($haystack, ['vest', 'armor', 'armour', 'protective'])) {
            return 'armor';
        }
        if ($this->containsAny($haystack, ['pants', 'jeans', 'trouser'])) {
            return 'legs';
        }
        if ($this->containsAny($haystack, ['bag', 'backpack', 'duffel'])) {
            return 'bag';
        }
        if ($this->containsAny($haystack, ['crowbar', 'lockpick', 'tool', 'screwdriver'])) {
            return 'tool';
        }
        if ($this->containsAny($haystack, ['flashlight', 'radio', 'phone', 'med', 'medical', 'kit'])) {
            return 'utility_1';
        }
        if ($legacySlot === 'clothing' || $category === 'clothing') {
            return 'torso';
        }

        return 'tool';
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
