import type {
  LocationMapResponse,
  MapHotspotAction,
  RegionMapResponse,
  TravelResponse,
  WorldRegion,
} from '../../types/worldMap';

function money(value: number | undefined): string {
  return `$${Number(value || 0).toLocaleString()}`;
}

function hotspotIsCurrent(locationResponse: LocationMapResponse): boolean {
  return locationResponse.currentLocation.location_slug === locationResponse.location.slug;
}

function unlockLabels(locationResponse: LocationMapResponse): string[] {
  const labels = locationResponse.linkedActions.map((action) => action.label);

  if (labels.length > 0) {
    return labels.slice(0, 6);
  }

  return locationResponse.location.available_actions.slice(0, 6);
}

export function TravelPanel({
  selectedRegion,
  regionResponse,
  locationResponse,
  travelResult,
  busy,
  onEnterRegion,
  onTravel,
  onTravelAndExplore,
  onNavigateAction,
  onOpenShop,
}: {
  selectedRegion?: WorldRegion | null;
  regionResponse?: RegionMapResponse | null;
  locationResponse?: LocationMapResponse | null;
  travelResult?: TravelResponse | null;
  busy: boolean;
  onEnterRegion?: (region: WorldRegion) => void;
  onTravel?: () => void;
  onTravelAndExplore?: () => void;
  onNavigateAction?: (action: MapHotspotAction) => void;
  onOpenShop?: (shopSlug: string) => void;
}) {
  if (locationResponse) {
    const isHere = hotspotIsCurrent(locationResponse);
    const unlocks = unlockLabels(locationResponse);
    const routeOptions = locationResponse.travelInfo.route_options || [];
    const shopSummary = locationResponse.location.shopSummary;

    return (
      <aside className="travel-panel card">
        <p className="eyebrow">Selected hotspot</p>
        <h2>{locationResponse.location.name}</h2>
        <p>{locationResponse.location.description}</p>
        <p className={isHere ? 'success-text' : 'warning-text'}>
          {isHere ? 'You are here. Local actions can be started from this hotspot.' : 'Not here. Travel here to act locally.'}
        </p>

        {locationResponse.territory && (
          <p className="muted">Territory: {locationResponse.territory.name} · {locationResponse.territory.control_label}</p>
        )}

        <dl className="details-grid compact-details-grid">
          <div>
            <dt>Travel</dt>
            <dd>{money(locationResponse.travelInfo.cash_cost)} · {locationResponse.travelInfo.energy_cost} energy</dd>
          </div>
          <div>
            <dt>Route</dt>
            <dd>{locationResponse.travelInfo.route_label || locationResponse.travelInfo.route_type || 'Cheap route'}</dd>
          </div>
          <div>
            <dt>Travel risk</dt>
            <dd>{locationResponse.travelInfo.travel_risk_score ?? locationResponse.riskSummary.score}</dd>
          </div>
          <div>
            <dt>Event chance</dt>
            <dd>{locationResponse.travelInfo.event_chance ?? 0}%</dd>
          </div>
        </dl>

        {unlocks.length > 0 ? (
          <div className="local-purpose-box">
            <strong>Travel here to unlock:</strong>
            <ul>
              {unlocks.map((label) => <li key={label}>{label}</li>)}
            </ul>
          </div>
        ) : (
          <p className="muted">No local actions are available here yet. This hotspot is currently useful for map context and travel presence.</p>
        )}

        <div className="local-purpose-box subdued">
          <strong>Can view remotely:</strong>
          <p className="muted">territory summary, known opportunities, police risk, and travel planning.</p>
        </div>

        {routeOptions.length > 0 && (
          <div className="route-option-list">
            {routeOptions.slice(0, 4).map((route) => (
              <div key={route.type} className="mini-card">
                <strong>{route.label}</strong>
                <span>{money(route.cash_cost)} · {route.energy_cost} energy · {route.event_chance}% event</span>
              </div>
            ))}
          </div>
        )}

        {locationResponse.travelInfo.warnings.map((warning) => <p key={warning} className="warning-text">{warning}</p>)}

        {shopSummary && shopSummary.count > 0 && (
          <div className="local-purpose-box shop-map-callout">
            <strong>Shop on this hotspot</strong>
            <p className="muted">{shopSummary.primary_shop_name || 'Known shop'} · {shopSummary.count} shop/dealer marker{shopSummary.count === 1 ? '' : 's'} here.</p>
            {shopSummary.primary_shop_slug && (
              <button className="btn primary full-width" onClick={() => onOpenShop?.(shopSummary.primary_shop_slug || '')}>
                Open shop catalog
              </button>
            )}
          </div>
        )}

        {travelResult && (
          <div className="travel-result-panel">
            <strong>{travelResult.message}</strong>
            {travelResult.event && (
              <p className="muted">{travelResult.event.title}: {travelResult.event.description}</p>
            )}
            {typeof travelResult.heatChange === 'number' && travelResult.heatChange !== 0 && (
              <p className="warning-text">Heat changed by {travelResult.heatChange}.</p>
            )}
          </div>
        )}

        <div className="map-action-grid">
          <button className="btn primary" disabled={busy || isHere} onClick={onTravel}>
            {isHere ? 'Already Here' : 'Travel Here'}
          </button>
          <button className="btn" disabled={busy} onClick={onTravelAndExplore}>
            {isHere ? 'Explore Area' : 'Travel & Explore'}
          </button>
        </div>

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
          <div><dt>Travel</dt><dd>{money(selectedRegion.travel_cost_cash)} · {selectedRegion.travel_cost_energy} energy</dd></div>
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
        <p className="muted">Select a hotspot to see why traveling matters, what local actions unlock, and what risks apply.</p>
      </aside>
    );
  }

  return null;
}
