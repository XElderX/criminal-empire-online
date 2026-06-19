import { useEffect, useMemo, useState } from 'react';
import { exploreHotspot, getLocationActivities, getLocationMap, getRegionMap, travelToLocation } from '../api/worldMap';
import { LocationMap } from '../components/map/LocationMap';
import { LocalActivityPanel } from '../components/map/LocalActivityPanel';
import { MapLegend } from '../components/map/MapLegend';
import { MapTooltip } from '../components/map/MapTooltip';
import { TravelPanel } from '../components/map/TravelPanel';
import { Notice } from '../components/Notice';
import { EmptyState } from '../components/game/EmptyState';
import { GameHeader } from '../components/game/GameHeader';
import type { PageName } from '../types';
import type { LocationActivitiesResponse, LocationMapResponse, MapHotspotAction, RegionMapResponse, WorldLocation } from '../types/worldMap';

export function LocationMapPage({
  regionSlug,
  onBack,
  onNavigate,
  onChanged,
}: {
  regionSlug: string;
  onBack: () => void;
  onNavigate: (page: PageName) => void;
  onChanged: () => void;
}) {
  const [region, setRegion] = useState<RegionMapResponse | null>(null);
  const [selectedSlug, setSelectedSlug] = useState<string | null>(null);
  const [locationDetail, setLocationDetail] = useState<LocationMapResponse | null>(null);
  const [activities, setActivities] = useState<LocationActivitiesResponse | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    void load();
  }, [regionSlug]);

  const selectedLocation = useMemo(() => {
    if (!region) return null;
    return region.locations.find((location) => location.slug === selectedSlug) || region.locations[0] || null;
  }, [region, selectedSlug]);

  useEffect(() => {
    if (selectedLocation) {
      void loadLocation(selectedLocation.slug);
    }
  }, [selectedLocation?.slug]);

  async function load(): Promise<void> {
    setError('');
    try {
      const response = await getRegionMap(regionSlug);
      setRegion(response);
      setSelectedSlug(response.locations[0]?.slug || null);
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  async function loadLocation(locationSlug: string): Promise<void> {
    try {
      const [response, activityResponse] = await Promise.all([
        getLocationMap(locationSlug),
        getLocationActivities(locationSlug),
      ]);
      setLocationDetail(response);
      setActivities(activityResponse);
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  async function travel(): Promise<void> {
    if (!selectedLocation) return;
    setBusy(true);
    setMessage('');
    setError('');
    try {
      const response = await travelToLocation({ location_slug: selectedLocation.slug });
      setMessage(`${response.message} Current location: ${response.currentLocation.location_name}.`);
      await load();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setBusy(false);
    }
  }

  async function exploreSelectedHotspot(): Promise<void> {
    if (!selectedLocation) return;
    setBusy(true);
    setMessage('');
    setError('');
    try {
      const response = await exploreHotspot(selectedLocation.slug);
      setMessage(`${response.message} ${response.opportunity.title}`);
      setActivities(response.activities);
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setBusy(false);
    }
  }

  function openRouteHint(routeHint: string): void {
    const [pagePart, queryPart] = routeHint.split('?');
    const page = pagePart as PageName;
    onNavigate(page);
    if (queryPart) {
      window.history.pushState({}, '', `/?${queryPart}`);
    }
  }

  function navigateAction(action: MapHotspotAction): void {
    if (!action.route_hint || action.route_hint === 'world map') return;
    openRouteHint(String(action.route_hint));
  }

  if (!region) {
    return (
      <section className="page-section world-map-page">
        <GameHeader eyebrow="Grimwater County" title="Location Map" description="Loading region map…" />
        {error && <Notice message={error} kind="error" />}
      </section>
    );
  }

  return (
    <section className="page-section world-map-page">
      <GameHeader
        eyebrow="Region map"
        title={region.region.name}
        description={`${region.region.description} Select hotspots to inspect heat, police pressure, territory control, and linked actions.`}
      />
      <div className="map-top-actions">
        <button className="btn" onClick={onBack}>Back to World Map</button>
        <span className="info-pill">Current: {region.currentLocation.region_name} / {region.currentLocation.location_name}</span>
      </div>
      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <div className="map-layout-grid">
        <div className="card map-card">
          {region.locations.length === 0 ? (
            <EmptyState title="No hotspots seeded" message="Run the v0.6 world map seeder to populate this region." />
          ) : (
            <LocationMap
              mapAsset={region.mapAssets.map}
              overlayAsset={region.mapAssets.overlay}
              regionName={region.region.name}
              locations={region.locations}
              selectedLocation={selectedLocation}
              onSelect={(location) => setSelectedSlug(location.slug)}
            />
          )}
        </div>
        <div className="map-side-stack">
          {selectedLocation && <MapTooltip item={selectedLocation} />}
          {/* Travel Here button and linkedActions are rendered by TravelPanel for the selected hotspot. */}
          <TravelPanel
            regionResponse={region}
            locationResponse={locationDetail}
            busy={busy}
            onTravel={travel}
            onNavigateAction={navigateAction}
          />
          <LocalActivityPanel
            activities={activities}
            busy={busy}
            onExplore={exploreSelectedHotspot}
            onOpenRoute={openRouteHint}
          />
          <MapLegend entries={[
            { type: 'district', label: 'District' },
            { type: 'safehouse', label: 'Safehouse' },
            { type: 'garage', label: 'Garage' },
            { type: 'black_market', label: 'Black Market' },
            { type: 'police', label: 'Police' },
            { type: 'business', label: 'Business' },
            { type: 'warehouse', label: 'Warehouse' },
            { type: 'point_of_interest', label: 'Point of Interest' },
          ]} />
        </div>
      </div>
    </section>
  );
}
