<?php

namespace App\Services;

interface RandomSource
{
    public function integer(int $minimum, int $maximum): int;
}
