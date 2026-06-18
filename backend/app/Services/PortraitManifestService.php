<?php

namespace App\Services;

use App\Config\CrewPortraitManifest;
use App\Core\App;

final class PortraitManifestService
{
    public const FALLBACK_URL = '/assets/crew/portraits/fallback.svg';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allSets(): array
    {
        return CrewPortraitManifest::sets();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function enabledSets(?string $gender = null): array
    {
        $normalizedGender = $this->normalizeGender($gender);

        return array_filter(
            $this->allSets(),
            static function (array $set) use ($normalizedGender): bool {
                if (!(bool) ($set['enabled'] ?? false)) {
                    return false;
                }

                if ($normalizedGender === null) {
                    return true;
                }

                return ($set['gender'] ?? null) === $normalizedGender;
            }
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        $sets = $this->allSets();

        return $sets[$key] ?? null;
    }

    public function normalizeGender(?string $gender): ?string
    {
        if ($gender === null) {
            return null;
        }

        $normalized = strtolower(trim($gender));

        return match ($normalized) {
            'male', 'm', 'man', 'masculine' => 'male',
            'female', 'f', 'woman', 'feminine' => 'female',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(): array
    {
        $requiredStages = array_keys(
            (new CrewAgeStageResolver())->definitions()
        );
        $seenKeys = [];
        $errors = [];
        $warnings = [];
        $completeSets = 0;

        foreach ($this->allSets() as $key => $set) {
            if (isset($seenKeys[$key])) {
                $errors[] = "Duplicate portrait set key: {$key}.";
            }

            $seenKeys[$key] = true;

            if (!preg_match('/^portrait-set-\d{3}$/', $key)) {
                $errors[] = "Invalid portrait set key format: {$key}.";
            }

            if (!in_array($set['gender'] ?? null, ['male', 'female'], true)) {
                $errors[] = "Portrait set {$key} has unsupported gender metadata.";
            }

            $availableStages = [];

            foreach ($requiredStages as $stage) {
                $path = $set['assets'][$stage] ?? null;

                if (!is_string($path) || $path === '') {
                    $warnings[] = "Portrait set {$key} is missing {$stage}.";
                    continue;
                }

                if (!$this->assetExists($path)) {
                    $errors[] = "Portrait asset does not exist: {$path}.";
                    continue;
                }

                if (!preg_match('/\.(webp|png|jpe?g|svg)$/i', $path)) {
                    $errors[] = "Portrait asset uses an unsupported format: {$path}.";
                    continue;
                }

                $availableStages[] = $stage;
            }

            if (count($availableStages) === count($requiredStages)) {
                $completeSets++;
            }

            $defaultStage = (string) ($set['default_stage'] ?? '');
            $defaultPath = $set['assets'][$defaultStage] ?? null;

            if (!is_string($defaultPath) || !$this->assetExists($defaultPath)) {
                $errors[] = "Portrait set {$key} has an invalid default-stage asset.";
            }
        }

        if (!$this->assetExists(self::FALLBACK_URL)) {
            $errors[] = 'The portrait fallback asset is missing.';
        }

        return [
            'portrait_sets' => count($this->allSets()),
            'complete_five_stage_sets' => $completeSets,
            'required_stages_per_set' => count($requiredStages),
            'errors' => $errors,
            'warnings' => $warnings,
            'valid' => count($errors) === 0,
            'complete' => count($errors) === 0 && count($warnings) === 0,
        ];
    }

    public function assetExists(string $publicUrl): bool
    {
        $projectRoot = dirname(App::$basePath);
        $relativePath = ltrim($publicUrl, '/');
        $assetPath = $projectRoot . '/frontend/public/' . $relativePath;

        return is_file($assetPath);
    }
}
