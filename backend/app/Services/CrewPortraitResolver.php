<?php

namespace App\Services;

final class CrewPortraitResolver
{
    public function __construct(
        private readonly ?PortraitManifestService $manifest = null,
        private readonly ?CrewAgeStageResolver $ageResolver = null
    ) {
    }

    /**
     * @param array<string, mixed> $npc
     * @return array<string, mixed>
     */
    public function resolve(array $npc): array
    {
        $manifest = $this->manifest ?? new PortraitManifestService();
        $ageResolver = $this->ageResolver ?? new CrewAgeStageResolver();
        $age = (int) ($npc['age'] ?? 0);
        $stage = $ageResolver->resolve($age);
        $identityKey = (string) ($npc['portrait_set_key'] ?? '');
        $set = $identityKey !== '' ? $manifest->find($identityKey) : null;

        if ($set === null || !(bool) ($set['enabled'] ?? false)) {
            return $this->fallbackPortrait(
                $identityKey,
                $stage,
                $manifest->normalizeGender($npc['gender'] ?? null)
            );
        }

        $requestedStage = (string) $stage['key'];
        $resolvedStage = $requestedStage;
        $url = $set['assets'][$requestedStage] ?? null;
        $thumbnailUrl = $set['thumbnails'][$requestedStage] ?? null;
        $usesStageFallback = false;

        if (!is_string($url) || !$manifest->assetExists($url)) {
            $resolvedStage = (string) ($set['default_stage'] ?? CrewAgeStageResolver::ADULT);
            $url = $set['assets'][$resolvedStage] ?? null;
            $thumbnailUrl = $set['thumbnails'][$resolvedStage] ?? null;
            $usesStageFallback = true;
        }

        if (!is_string($url) || !$manifest->assetExists($url)) {
            return $this->fallbackPortrait(
                $identityKey,
                $stage,
                $manifest->normalizeGender($npc['gender'] ?? null)
            );
        }

        if (!is_string($thumbnailUrl) || !$manifest->assetExists($thumbnailUrl)) {
            $thumbnailUrl = $url;
        }

        return [
            'identity_key' => $identityKey,
            'gender' => $set['gender'],
            'stage' => $requestedStage,
            'stage_label' => $stage['label'],
            'age_range' => $stage['age_range'],
            'resolved_asset_stage' => $resolvedStage,
            'url' => $url,
            'thumbnail_url' => $thumbnailUrl,
            'fallback_url' => PortraitManifestService::FALLBACK_URL,
            'focal_x' => (int) ($npc['portrait_focal_x'] ?? $set['focal_x'] ?? 50),
            'focal_y' => (int) ($npc['portrait_focal_y'] ?? $set['focal_y'] ?? 50),
            'uses_fallback' => false,
            'uses_stage_fallback' => $usesStageFallback,
        ];
    }

    /**
     * @param array<string, bool|int|string> $stage
     * @return array<string, mixed>
     */
    private function fallbackPortrait(
        string $identityKey,
        array $stage,
        ?string $gender
    ): array {
        return [
            'identity_key' => $identityKey !== '' ? $identityKey : null,
            'gender' => $gender,
            'stage' => $stage['key'],
            'stage_label' => $stage['label'],
            'age_range' => $stage['age_range'],
            'resolved_asset_stage' => null,
            'url' => PortraitManifestService::FALLBACK_URL,
            'thumbnail_url' => PortraitManifestService::FALLBACK_URL,
            'fallback_url' => PortraitManifestService::FALLBACK_URL,
            'focal_x' => 50,
            'focal_y' => 50,
            'uses_fallback' => true,
            'uses_stage_fallback' => true,
        ];
    }
}
