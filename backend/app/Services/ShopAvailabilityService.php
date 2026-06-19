<?php

namespace App\Services;

final class ShopAvailabilityService
{
    public function availability(array $user, array $shop, array $item, bool $playerIsHere): array
    {
        $reasons = [];
        $warnings = [];

        if ((int) $shop['is_active'] !== 1) {
            $reasons[] = 'Shop is closed.';
        }
        if ((int) $item['is_enabled'] !== 1) {
            $reasons[] = $item['disabled_reason'] ?: 'Item is disabled by shop config.';
        }
        if ((int) $item['can_buy'] !== 1) {
            $reasons[] = 'This item cannot be bought here.';
        }
        if ((int) $shop['requires_local_presence'] === 1 && !$playerIsHere) {
            $reasons[] = 'Travel to this shop location before buying or selling.';
        }
        if ((int) $user['level'] < (int) $item['min_level']) {
            $reasons[] = 'Requires level ' . (int) $item['min_level'] . '.';
        }
        if ((int) ($user['reputation'] ?? 0) < (int) $item['min_reputation']) {
            $reasons[] = 'Requires reputation ' . (int) $item['min_reputation'] . '.';
        }
        if ($this->isLimitedStock($item) && (int) $item['stock_quantity'] < 1) {
            $reasons[] = 'Out of stock.';
        }
        if ((int) $item['buy_price'] > (int) $user['cash']) {
            $warnings[] = 'Not enough cash for one unit.';
        }
        if ((int) $shop['is_black_market'] === 1 || $item['availability_status'] === 'black_market_only') {
            $warnings[] = 'Black-market item: buying may increase heat in future systems.';
        }

        return [
            'can_buy' => $reasons === [] && (int) $user['cash'] >= (int) $item['buy_price'],
            'can_sell' => (int) $item['can_sell'] === 1 && ((int) $shop['requires_local_presence'] !== 1 || $playerIsHere),
            'locked_reasons' => $reasons,
            'warnings' => $warnings,
            'local_presence_satisfied' => $playerIsHere || (int) $shop['requires_local_presence'] !== 1,
        ];
    }

    private function isLimitedStock(array $item): bool
    {
        return $item['stock_quantity'] !== null && $item['stock_quantity'] !== '';
    }
}
