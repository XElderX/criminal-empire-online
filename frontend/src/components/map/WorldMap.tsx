import { getWorldMapAsset, getWorldMapOverlay } from '../../data/mapAssetManifest';
import type { WorldRegion } from '../../types/worldMap';

const regionMarkerPositions: Record<string, { x: number; y: number }> = {
  'main-city': { x: 47, y: 42 },
  suburbs: { x: 67, y: 43 },
  'industrial-zone': { x: 76, y: 60 },
  docks: { x: 46, y: 72 },
  'rural-county': { x: 68, y: 22 },
  'forest-hills': { x: 31, y: 21 },
  shore: { x: 70, y: 79 },
  'old-town': { x: 29, y: 57 },
  outskirts: { x: 12, y: 43 },
};

export function WorldMap({
  regions,
  selectedRegion,
  onSelect,
}: {
  regions: WorldRegion[];
  selectedRegion: WorldRegion | null;
  onSelect: (region: WorldRegion) => void;
}) {
  return (
    <div className="world-map-shell">
      <img className="map-art" src={getWorldMapAsset()} alt="Grimwater County world map" />
      <img className="map-overlay" src={getWorldMapOverlay()} alt="" />
      {regions.map((region) => {
        const pos = regionMarkerPositions[region.slug] || { x: 50, y: 50 };
        return (
          <button
            key={region.slug}
            type="button"
            className={`world-region-marker ${selectedRegion?.slug === region.slug ? 'selected' : ''} ${region.riskSummary.tone}`}
            style={{ left: `${pos.x}%`, top: `${pos.y}%` }}
            onClick={() => onSelect(region)}
          >
            <span>{region.name}</span>
            <small>{region.riskSummary.label}</small>
          </button>
        );
      })}
    </div>
  );
}
