<?php

namespace Tests;

use App\Services\RandomSource;
use RuntimeException;

final class SequenceRandomSource implements RandomSource
{
    /** @var list<int> */
    private array $values;

    public function __construct(int ...$values)
    {
        $this->values = $values;
    }

    public function integer(int $minimum, int $maximum): int
    {
        if ($this->values === []) {
            throw new RuntimeException('The deterministic random sequence is empty.');
        }

        $value = array_shift($this->values);

        if ($value < $minimum || $value > $maximum) {
            throw new RuntimeException(
                "Deterministic value {$value} is outside {$minimum}-{$maximum}."
            );
        }

        return $value;
    }
}
