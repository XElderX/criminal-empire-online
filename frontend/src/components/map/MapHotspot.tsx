import { getHotspotIcon } from '../../data/mapAssetManifest';
import type { WorldLocation } from '../../types/worldMap';
import { LocationRiskBadge } from './LocationRiskBadge';

export function MapHotspot({
  location,
  selected,
  onSelect,
}: {
  location: WorldLocation;
  selected: boolean;
  onSelect: (location: WorldLocation) => void;
}) {
  return (
    <button
      type="button"
      className={`map-hotspot ${selected ? 'selected' : ''} ${location.riskSummary.tone}`}
      style={{ left: `${location.x_percent}%`, top: `${location.y_percent}%` }}
      onClick={() => onSelect(location)}
      aria-label={`Select ${location.name}. ${location.riskSummary.label}`}
    >
      <span className="map-hotspot-icon">{getHotspotIcon(location.location_type)}</span>
      <span className="map-hotspot-label">{location.name}</span>
      <small><LocationRiskBadge risk={location.riskSummary} /></small>
    </button>
  );
}
