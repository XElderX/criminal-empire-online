<?php

namespace App\Services;

final class SecureRandomSource implements RandomSource
{
    public function integer(int $minimum, int $maximum): int
    {
        return random_int($minimum, $maximum);
    }
}
