import type { WorldLocation } from '../../types/worldMap';
import { MapHotspot } from './MapHotspot';

export function LocationMap({
  mapAsset,
  overlayAsset,
  regionName,
  locations,
  selectedLocation,
  onSelect,
  onOpenShop,
}: {
  mapAsset: string;
  overlayAsset?: string | null;
  regionName: string;
  locations: WorldLocation[];
  selectedLocation: WorldLocation | null;
  onSelect: (location: WorldLocation) => void;
  onOpenShop?: (shopSlug: string) => void;
}) {
  return (
    <div className="location-map-shell">
      <img className="map-art" src={mapAsset} alt={`${regionName} map`} />
      {overlayAsset && <img className="map-overlay" src={overlayAsset} alt="" />}
      {locations.map((location) => (
        <MapHotspot
          key={location.slug}
          location={location}
          selected={selectedLocation?.slug === location.slug}
          onSelect={onSelect}
          onOpenShop={onOpenShop}
        />
      ))}
    </div>
  );
}
