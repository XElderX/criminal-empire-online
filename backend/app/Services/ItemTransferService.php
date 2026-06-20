<?php

namespace App\Services;

final class ItemTransferService
{
    public function explain(): array
    {
        return ['owned', 'equipped', 'carried', 'warehouse', 'lost'];
    }
}
