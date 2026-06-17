<?php

namespace Tests;

use App\Services\RandomSource;

final class PredictableRandomSource implements RandomSource
{
    public function integer(int $minimum, int $maximum): int
    {
        if ($minimum === 1 && $maximum === 100) {
            return 50;
        }

        if ($minimum === 0) {
            return 0;
        }

        return (int) floor(($minimum + $maximum) / 2);
    }
}
