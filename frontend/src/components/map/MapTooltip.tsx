import type { WorldLocation, WorldRegion } from '../../types/worldMap';
import { LocationRiskBadge } from './LocationRiskBadge';

export function MapTooltip({ item }: { item: WorldRegion | WorldLocation }) {
  const actions = 'actions' in item ? item.actions : [];
  const territory = 'territory' in item ? item.territory : null;

  return (
    <aside className="map-tooltip-card">
      <p className="eyebrow">{'location_type' in item ? item.location_type.replace(/_/g, ' ') : item.region_type.replace(/_/g, ' ')}</p>
      <h3>{item.name}</h3>
      <p>{item.description}</p>
      <LocationRiskBadge risk={item.riskSummary} />
      {territory && (
        <p className="muted">Territory: {territory.name} · {territory.control_label}</p>
      )}
      {actions.length > 0 && (
        <p className="muted">Actions: {actions.map((action) => action.label).join(', ')}</p>
      )}
    </aside>
  );
}
