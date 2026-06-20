<?php

namespace App\Services;

final class CarryInventoryService
{
    public const BASE_CARRY_UNITS = 5;
    public const BACKPACK_BONUS = 2;
    public const DUFFEL_BAG_BONUS = 4;

    public function capacityText(): string
    {
        return 'Base carry capacity is 5 carry units; backpack adds +2 and duffel bag adds +4.';
    }
}
