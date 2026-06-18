<?php

namespace App\Services;

final class CrewAgeStageResolver
{
    public const VERY_YOUNG = 'very_young';
    public const YOUNG = 'young';
    public const ADULT = 'adult';
    public const MATURE = 'mature';
    public const ELDER = 'elder';

    /**
     * @return array<string, array<string, int|string>>
     */
    public function definitions(): array
    {
        return [
            self::VERY_YOUNG => [
                'key' => self::VERY_YOUNG,
                'label' => 'Very Young',
                'minimum_age' => 16,
                'maximum_age' => 24,
                'age_range' => '16–24',
            ],
            self::YOUNG => [
                'key' => self::YOUNG,
                'label' => 'Young',
                'minimum_age' => 25,
                'maximum_age' => 31,
                'age_range' => '25–31',
            ],
            self::ADULT => [
                'key' => self::ADULT,
                'label' => 'Adult',
                'minimum_age' => 32,
                'maximum_age' => 40,
                'age_range' => '32–40',
            ],
            self::MATURE => [
                'key' => self::MATURE,
                'label' => 'Mature',
                'minimum_age' => 41,
                'maximum_age' => 55,
                'age_range' => '41–55',
            ],
            self::ELDER => [
                'key' => self::ELDER,
                'label' => 'Elder',
                'minimum_age' => 56,
                'maximum_age' => 70,
                'age_range' => '56–70',
            ],
        ];
    }

    /**
     * Ages below sixteen use the very-young visual stage but are not
     * recruitable. Ages above seventy remain on the elder visual stage.
     *
     * @return array<string, bool|int|string>
     */
    public function resolve(int $age): array
    {
        $safeAge = max(0, $age);
        $stage = match (true) {
            $safeAge <= 24 => self::VERY_YOUNG,
            $safeAge <= 31 => self::YOUNG,
            $safeAge <= 40 => self::ADULT,
            $safeAge <= 55 => self::MATURE,
            default => self::ELDER,
        };

        $definition = $this->definitions()[$stage];

        return [
            ...$definition,
            'age' => $safeAge,
            'recruitable' => $safeAge >= 16 && $safeAge <= 70,
            'outside_standard_range' => $safeAge < 16 || $safeAge > 70,
        ];
    }

    public function stageKey(int $age): string
    {
        return (string) $this->resolve($age)['key'];
    }
}
