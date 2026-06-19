import type { LocationMapResponse, MapHotspotAction, RegionMapResponse, WorldRegion } from '../../types/worldMap';

export function TravelPanel({
  selectedRegion,
  regionResponse,
  locationResponse,
  busy,
  onEnterRegion,
  onTravel,
  onNavigateAction,
}: {
  selectedRegion?: WorldRegion | null;
  regionResponse?: RegionMapResponse | null;
  locationResponse?: LocationMapResponse | null;
  busy: boolean;
  onEnterRegion?: (region: WorldRegion) => void;
  onTravel?: () => void;
  onNavigateAction?: (action: MapHotspotAction) => void;
}) {
  if (locationResponse) {
    return (
      <aside className="travel-panel card">
        <p className="eyebrow">Selected hotspot</p>
        <h2>{locationResponse.location.name}</h2>
        <p>{locationResponse.location.description}</p>
        {locationResponse.territory && (
          <p className="muted">Territory: {locationResponse.territory.name} · {locationResponse.territory.control_label}</p>
        )}
        <dl className="details-grid compact-details-grid">
          <div><dt>Travel</dt><dd>${locationResponse.travelInfo.cash_cost} · {locationResponse.travelInfo.energy_cost} energy</dd></div>
          <div><dt>Actions</dt><dd>{locationResponse.linkedActions.length}</dd></div>
        </dl>
        {locationResponse.travelInfo.warnings.map((warning) => <p key={warning} className="warning-text">{warning}</p>)}
        <button className="btn primary full-width" disabled={busy} onClick={onTravel}>Travel Here</button>
        {locationResponse.linkedActions.length > 0 && (
          <div className="map-action-grid">
            {locationResponse.linkedActions.map((action) => (
              <button key={action.id} className="btn" onClick={() => onNavigateAction?.(action)}>
                {action.label}
              </button>
            ))}
          </div>
        )}
      </aside>
    );
  }

  if (selectedRegion) {
    return (
      <aside className="travel-panel card">
        <p className="eyebrow">Selected region</p>
        <h2>{selectedRegion.name}</h2>
        <p>{selectedRegion.description}</p>
        <dl className="details-grid compact-details-grid">
          <div><dt>Travel</dt><dd>${selectedRegion.travel_cost_cash} · {selectedRegion.travel_cost_energy} energy</dd></div>
          <div><dt>Recommended</dt><dd>Level {selectedRegion.recommended_level}+</dd></div>
          <div><dt>Heat</dt><dd>{selectedRegion.base_heat}</dd></div>
          <div><dt>Police</dt><dd>{selectedRegion.police_pressure}</dd></div>
        </dl>
        <button className="btn primary full-width" onClick={() => onEnterRegion?.(selectedRegion)}>
          Enter Region
        </button>
      </aside>
    );
  }

  if (regionResponse) {
    return (
      <aside className="travel-panel card">
        <p className="eyebrow">Region</p>
        <h2>{regionResponse.region.name}</h2>
        <p>{regionResponse.region.description}</p>
        <p className="muted">Select a hotspot to travel, inspect territory control, or jump to linked gameplay.</p>
      </aside>
    );
  }

  return null;
}
