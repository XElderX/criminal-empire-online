import type { WorldRegion } from '../../types/worldMap';
import { LocationRiskBadge } from './LocationRiskBadge';

export function MapRegionCard({
  region,
  selected,
  onSelect,
}: {
  region: WorldRegion;
  selected: boolean;
  onSelect: (region: WorldRegion) => void;
}) {
  return (
    <button
      type="button"
      className={`map-region-card ${selected ? 'selected' : ''}`}
      onClick={() => onSelect(region)}
    >
      <span className="eyebrow">{region.region_type.replace(/_/g, ' ')}</span>
      <strong>{region.name}</strong>
      <LocationRiskBadge risk={region.riskSummary} />
      <small>${region.travel_cost_cash} · {region.travel_cost_energy} energy · lvl {region.recommended_level}+</small>
    </button>
  );
}
