import { useEffect, useMemo, useState } from 'react';
import { getWorldMap } from '../api/worldMap';
import { MapLegend } from '../components/map/MapLegend';
import { MapRegionCard } from '../components/map/MapRegionCard';
import { MapTooltip } from '../components/map/MapTooltip';
import { TravelPanel } from '../components/map/TravelPanel';
import { WorldMap } from '../components/map/WorldMap';
import { Notice } from '../components/Notice';
import { EmptyState } from '../components/game/EmptyState';
import { GameHeader } from '../components/game/GameHeader';
import type { PageName } from '../types';
import type { WorldMapResponse, WorldRegion } from '../types/worldMap';
import { LocationMapPage } from './LocationMapPage';

export function WorldMapPage({
  onNavigate,
  onChanged,
}: {
  onNavigate: (page: PageName) => void;
  onChanged: () => void;
}) {
  const [overview, setOverview] = useState<WorldMapResponse | null>(null);
  const [selectedSlug, setSelectedSlug] = useState<string | null>(null);
  const [regionPathSlug, setRegionPathSlug] = useState<string | null>(() => slugFromPath());
  const [error, setError] = useState('');

  useEffect(() => {
    void load();
    const listener = () => setRegionPathSlug(slugFromPath());
    window.addEventListener('popstate', listener);
    return () => window.removeEventListener('popstate', listener);
  }, []);

  const selectedRegion = useMemo(() => {
    if (!overview) return null;
    return overview.regions.find((region) => region.slug === selectedSlug) || overview.regions[0] || null;
  }, [overview, selectedSlug]);

  async function load(): Promise<void> {
    setError('');
    try {
      const response = await getWorldMap();
      setOverview(response);
      setSelectedSlug(response.currentLocation.region_slug || response.regions[0]?.slug || null);
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  function enterRegion(region: WorldRegion): void {
    window.history.pushState({}, '', `/world-map/${region.slug}`);
    setRegionPathSlug(region.slug);
  }

  function backToWorld(): void {
    window.history.pushState({}, '', '/world-map');
    setRegionPathSlug(null);
    void load();
  }

  if (regionPathSlug) {
    return (
      <LocationMapPage
        regionSlug={regionPathSlug}
        onBack={backToWorld}
        onNavigate={onNavigate}
        onChanged={onChanged}
      />
    );
  }

  if (!overview) {
    return (
      <section className="page-section world-map-page">
        <GameHeader eyebrow="Grimwater County" title="World Map" description="Loading regions, hotspots, heat, and territory links…" />
        {error && <Notice message={error} kind="error" />}
      </section>
    );
  }

  return (
    <section className="page-section world-map-page">
      <GameHeader
        eyebrow="v0.6 game map"
        title={`${overview.world_name} World Map`}
        description="Click a major region, inspect risk and territory control, then enter its interactive sub-map. The map connects crimes, jobs, businesses, recruitment, heat, warehouse, and territories."
      />

      {error && <Notice message={error} kind="error" />}

      <div className="map-summary-strip card">
        <span>Current: <strong>{overview.currentLocation.region_name} / {overview.currentLocation.location_name}</strong></span>
        <span>Cash: <strong>${overview.summary.cash.toLocaleString()}</strong></span>
        <span>Energy: <strong>{overview.summary.energy}/{overview.summary.max_energy}</strong></span>
        <span>Display heat: <strong>{overview.summary.display_heat}</strong></span>
      </div>

      <div className="map-layout-grid">
        <div className="card map-card">
          {overview.regions.length === 0 ? (
            <EmptyState title="No regions seeded" message="Run the v0.6 world map seeder to populate Grimwater County." />
          ) : (
            <WorldMap regions={overview.regions} selectedRegion={selectedRegion} onSelect={(region) => setSelectedSlug(region.slug)} />
          )}
        </div>

        <div className="map-side-stack">
          {selectedRegion && <MapTooltip item={selectedRegion} />}
          <TravelPanel selectedRegion={selectedRegion} busy={false} onEnterRegion={enterRegion} />
          <div className="region-card-list">
            {overview.regions.map((region) => (
              <MapRegionCard
                key={region.slug}
                region={region}
                selected={selectedRegion?.slug === region.slug}
                onSelect={(region) => setSelectedSlug(region.slug)}
              />
            ))}
          </div>
          <MapLegend entries={overview.legend} />
        </div>
      </div>
    </section>
  );
}

function slugFromPath(): string | null {
  const match = window.location.pathname.match(/^\/world-map\/([^/]+)/);
  return match ? decodeURIComponent(match[1]) : null;
}
