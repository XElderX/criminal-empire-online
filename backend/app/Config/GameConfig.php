<?php
namespace App\Config;

final class GameConfig
{
    public const STARTING_CASH = 500;
    public const STARTING_BANK_CASH = 0;
    public const STARTING_DIRTY_MONEY = 0;
    public const STARTING_REPUTATION = 0;
    public const STARTING_HEAT = 0;
    public const STARTING_ENERGY = 100;
    public const STARTING_MAX_ENERGY = 100;
    public const MAX_GANG_MEMBERS = 12;
    public const SALARY_INTERVAL_DAYS = 7;

    public static function jobDurationMultiplier(): float
    {
        $value = (float)($_ENV['JOB_DURATION_MULTIPLIER'] ?? getenv('JOB_DURATION_MULTIPLIER') ?: 1);
        return max(0.01, min(10, $value));
    }
}
