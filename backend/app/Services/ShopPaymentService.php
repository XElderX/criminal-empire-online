<?php

namespace App\Services;

use RuntimeException;

final class ShopPaymentService
{
    public const TYPES = ['cash', 'bank', 'dirty_money'];

    public function options(array $shop, ?array $item = null): array
    {
        $types = $this->decode($shop['accepted_payment_types_json'] ?? '[]');
        if ($types === []) {
            $types = ((int) ($shop['is_black_market'] ?? 0) === 1 || (int) ($shop['accepts_dirty_money'] ?? 0) === 1)
                ? ['cash', 'dirty_money']
                : ['cash'];
        }
        if ($item !== null) {
            $override = $this->decode($item['allowed_payment_types_json'] ?? '[]');
            if ($override !== []) $types = array_values(array_intersect($types, $override));
            if ((int) ($item['dirty_money_only'] ?? 0) === 1) $types = ['dirty_money'];
        }
        return array_values(array_filter($types, static fn (string $type): bool => in_array($type, self::TYPES, true)));
    }

    public function validate(array $user, array $shop, int $total, string $paymentType, ?array $item = null): void
    {
        $options = $this->options($shop, $item);
        if (!in_array($paymentType, $options, true)) {
            if ($paymentType === 'dirty_money' && (int) ($shop['is_legal'] ?? 0) === 1) {
                throw new RuntimeException('Legal shop rejects dirty money.');
            }
            throw new RuntimeException('This shop does not accept that payment type.');
        }
        if ($paymentType === 'cash' && (int) ($user['cash'] ?? 0) < $total) throw new RuntimeException('Not enough cash.');
        if ($paymentType === 'bank' && (int) ($user['bank_cash'] ?? 0) < $total) throw new RuntimeException('Not enough bank money.');
        if ($paymentType === 'dirty_money' && (int) ($user['dirty_money'] ?? 0) < $total) throw new RuntimeException('Not enough dirty money.');
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
