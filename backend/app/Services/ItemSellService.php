<?php

namespace App\Services;

final class ItemSellService
{
    public function sellToShop(array $user, string $shopSlug, string $itemKey, int $quantity): array
    {
        return (new ShopTransactionService())->sell($user, $shopSlug, $itemKey, $quantity);
    }
}
