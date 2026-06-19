<?php

namespace App\Services;

final class ItemPricingService
{
    public function sellPrice(array $item, float $multiplier): int
    {
        $effects = $this->decodeJson($item['effects'] ?? null);
        $base = (int) ($item['price'] ?? 0);

        if ($base <= 0) {
            $base = (int) ($effects['fence_value'] ?? $effects['vehicle_value'] ?? $effects['supply_value'] ?? 0);
        }

        if ($base <= 0) {
            $base = 10;
        }

        return max(1, (int) floor($base * $multiplier));
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
