<?php

namespace App\Services;

use App\Core\Database;

final class ItemRequirementService
{
    public function inventoryForUser(int $userId): array
    {
        $items = $this->ownedItems($userId);
        $weapons = $this->ownedWeapons($userId);

        return [
            'items' => $items,
            'weapons' => $weapons,
            'tags' => $this->collectTags($items, $weapons),
        ];
    }

    public function validate(int $userId, array $requiredAllTags, array $requiredAnyTags): array
    {
        $inventory = $this->inventoryForUser($userId);
        $tags = $inventory['tags'];
        $missing = [];

        foreach ($requiredAllTags as $tag) {
            if (!isset($tags[$tag])) {
                $missing[] = [
                    'tag' => $tag,
                    'label' => $this->labelForTag((string) $tag),
                    'type' => 'required_all',
                    'source_hints' => $this->sourceHintsForTag((string) $tag),
                ];
            }
        }

        if ($requiredAnyTags !== []) {
            $hasAny = false;

            foreach ($requiredAnyTags as $tag) {
                if (isset($tags[$tag])) {
                    $hasAny = true;
                    break;
                }
            }

            if (!$hasAny) {
                $missing[] = [
                    'tag' => implode('|', $requiredAnyTags),
                    'label' => 'One of: ' . implode(', ', array_map([$this, 'labelForTag'], $requiredAnyTags)),
                    'type' => 'required_any',
                    'source_hints' => $this->sourceHintsForTags(array_map('strval', $requiredAnyTags)),
                ];
            }
        }

        return [
            'ok' => $missing === [],
            'missing' => $missing,
            'message' => $missing === [] ? 'All item requirements satisfied.' : 'Missing required item tags.',
            'inventory' => $inventory,
        ];
    }

    public function effectsForSelection(int $userId, array $equipment): array
    {
        $inventory = $this->inventoryForUser($userId);
        $effects = [];

        foreach ($equipment as $entry) {
            $assetType = (string) ($entry['asset_type'] ?? 'item');
            $assetId = (int) ($entry['asset_id'] ?? 0);

            foreach (array_merge($inventory['items'], $inventory['weapons']) as $asset) {
                if ($asset['asset_type'] !== $assetType || (int) $asset['asset_id'] !== $assetId) {
                    continue;
                }

                foreach (($asset['effects'] ?? []) as $code => $value) {
                    if (is_numeric($value)) {
                        $effects[$code] = ($effects[$code] ?? 0) + (int) $value;
                    }
                }
            }
        }

        return $effects;
    }

    public function collectTags(array $items, array $weapons): array
    {
        $tags = [];

        foreach (array_merge($items, $weapons) as $asset) {
            foreach ($asset['tags'] as $tag) {
                $tags[$tag][] = $asset['name'];
            }
        }

        return $tags;
    }

    private function ownedItems(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    item.id AS asset_id,
                    item.code AS asset_code,
                    item.name,
                    item.category,
                    item.effects,
                    inventory.quantity
                FROM user_items inventory
                JOIN item_definitions item ON item.id = inventory.item_definition_id
                WHERE inventory.user_id = ?
                  AND inventory.quantity > 0
                  AND item.active = 1
                ORDER BY item.name
            SQL
        );
        $statement->execute([$userId]);
        $items = $statement->fetchAll();

        foreach ($items as &$item) {
            $item['asset_type'] = 'item';
            $item['effects'] = $this->decodeJson($item['effects']);
            $item['tags'] = $this->tagsFor('item', (string) $item['asset_code'], (string) $item['name'], (string) $item['category'], $item['effects']);
        }

        return $items;
    }

    private function ownedWeapons(int $userId): array
    {
        $statement = Database::pdo()->prepare(
            <<<'SQL'
                SELECT
                    weapon.id AS asset_id,
                    weapon.name,
                    weapon.class AS category,
                    weapon.effects,
                    inventory.quantity
                FROM user_weapons inventory
                JOIN weapons weapon ON weapon.id = inventory.weapon_id
                WHERE inventory.user_id = ?
                  AND inventory.quantity > 0
                ORDER BY weapon.name
            SQL
        );
        $statement->execute([$userId]);
        $weapons = $statement->fetchAll();

        foreach ($weapons as &$weapon) {
            $weapon['asset_type'] = 'weapon';
            $weapon['asset_code'] = $weapon['name'];
            $weapon['effects'] = $this->decodeJson($weapon['effects'] ?? null);
            $weapon['tags'] = $this->tagsFor('weapon', (string) $weapon['name'], (string) $weapon['name'], (string) $weapon['category'], $weapon['effects']);
        }

        return $weapons;
    }

    private function tagsFor(string $assetType, string $assetCode, string $name, string $category, array $effects): array
    {
        $tags = [];
        $statement = Database::pdo()->prepare(
            'SELECT tag FROM item_tags WHERE asset_type = ? AND asset_code = ?'
        );
        $statement->execute([$assetType, $assetCode]);

        foreach ($statement->fetchAll() as $row) {
            $tags[] = (string) $row['tag'];
        }

        $text = strtolower($assetCode . ' ' . $name . ' ' . $category . ' ' . implode(' ', array_keys($effects)));

        $inferred = [
            'gloves' => ['glove'],
            'mask' => ['mask', 'face covering', 'balaclava'],
            'lockpick' => ['lockpick'],
            'forced_entry_tool' => ['crowbar', 'screwdriver', 'glass breaker', 'bolt cutter', 'drill'],
            'vehicle_tool' => ['vehicle tool', 'toolbox', 'screwdriver', 'garage key'],
            'carrying_bag' => ['duffel', 'bag', 'backpack', 'messenger'],
            'dark_clothing' => ['dark clothing', 'dark hoodie', 'hoodie', 'dark jacket'],
            'stealth_clothing' => ['dark clothing', 'dark hoodie', 'hoodie', 'dark jacket'],
            'first_aid' => ['first-aid', 'first aid', 'bandage'],
            'communication' => ['burner', 'phone', 'radio'],
            'surveillance' => ['surveillance', 'camera', 'flashlight'],
            'blade_weapon' => ['knife', 'machete', 'blade'],
            'firearm' => ['pistol', 'revolver', 'smg', 'rifle', 'shotgun'],
            'melee_weapon' => ['bat', 'knuckle', 'machete'],
        ];

        foreach ($inferred as $tag => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($text, $needle)) {
                    $tags[] = $tag;
                    break;
                }
            }
        }

        return array_values(array_unique($tags));
    }


    private function sourceHintsForTags(array $tags): array
    {
        $hints = [];

        foreach ($tags as $tag) {
            foreach ($this->sourceHintsForTag($tag) as $hint) {
                $hints[$hint['item_key'] . ':' . $hint['shop_slug']] = $hint;
            }
        }

        return array_values($hints);
    }

    private function sourceHintsForTag(string $tag): array
    {
        $itemKeysByTag = [
            'gloves' => ['work_gloves'],
            'mask' => ['face_covering'],
            'lockpick' => ['lockpick_set'],
            'forced_entry_tool' => ['crowbar', 'screwdriver_set', 'glass_breaker'],
            'vehicle_tool' => ['vehicle_tools'],
            'carrying_bag' => ['duffel_bag', 'backpack'],
            'dark_clothing' => ['dark_clothing'],
            'stealth_clothing' => ['dark_clothing', 'work_uniform'],
            'first_aid' => ['first_aid_kit', 'bandages'],
            'communication' => ['burner_phone'],
            'surveillance' => ['flashlight'],
            'blade_weapon' => ['cheap_knife'],
            'firearm' => ['basic_pistol'],
        ];

        $catalog = new ShopCatalogService();
        $hints = [];

        foreach ($itemKeysByTag[$tag] ?? [] as $itemKey) {
            foreach ($catalog->possibleSources($itemKey) as $source) {
                $hints[] = $source;
            }
        }

        return $hints;
    }

    private function labelForTag(string $tag): string
    {
        return ucwords(str_replace(['_', '|'], [' ', ' or '], $tag));
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
