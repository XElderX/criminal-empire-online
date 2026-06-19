<?php

namespace App\Services;

final class ShopInventoryService
{
    public function itemsForShop(array $user, string $shopSlug): array
    {
        $detail = (new ShopService())->detail($user, $shopSlug);

        return $detail['items'];
    }

    public function sellableInventory(array $user, string $shopSlug): array
    {
        $detail = (new ShopService())->detail($user, $shopSlug);

        return $detail['sellableInventory'];
    }
}
