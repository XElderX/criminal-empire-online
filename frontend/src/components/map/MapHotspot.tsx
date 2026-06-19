import { getHotspotIcon } from '../../data/mapAssetManifest';
import type { WorldLocation } from '../../types/worldMap';
import { LocationRiskBadge } from './LocationRiskBadge';

function shopIconLabel(location: WorldLocation): string {
  const summary = location.shopSummary;

  if (!summary || summary.count < 1) {
    return '';
  }

  if (summary.has_black_market) {
    return '◆';
  }

  return '🛒';
}

export function MapHotspot({
  location,
  selected,
  onSelect,
  onOpenShop,
}: {
  location: WorldLocation;
  selected: boolean;
  onSelect: (location: WorldLocation) => void;
  onOpenShop?: (shopSlug: string) => void;
}) {
  const shopSummary = location.shopSummary;
  const hasShop = Boolean(shopSummary?.count);
  const primaryShopSlug = shopSummary?.primary_shop_slug || '';

  return (
    <div
      className={`map-hotspot ${selected ? 'selected' : ''} ${location.riskSummary.tone} ${hasShop ? 'has-shop' : ''}`}
      style={{ left: `${location.x_percent}%`, top: `${location.y_percent}%` }}
    >
      <button
        type="button"
        className="map-hotspot-main"
        onClick={() => onSelect(location)}
        aria-label={`Select ${location.name}. ${location.riskSummary.label}`}
      >
        <span className="map-hotspot-icon">{getHotspotIcon(location.location_type)}</span>
        <span className="map-hotspot-label">{location.name}</span>
        <small><LocationRiskBadge risk={location.riskSummary} /></small>
      </button>

      {hasShop && primaryShopSlug && (
        <button
          type="button"
          className="map-shop-marker"
          onClick={(event) => {
            event.stopPropagation();
            onOpenShop?.(primaryShopSlug);
          }}
          title={`Open ${shopSummary?.primary_shop_name || 'shop'} at ${location.name}`}
          aria-label={`Open ${shopSummary?.primary_shop_name || 'shop'} at ${location.name}`}
        >
          <span>{shopIconLabel(location)}</span>
          <strong>{shopSummary?.count}</strong>
        </button>
      )}
    </div>
  );
}
