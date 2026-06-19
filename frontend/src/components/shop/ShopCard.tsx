import type { ShopSummary } from '../../types/shop';
import { ShopRequirementBadge } from './ShopRequirementBadge';

export function ShopCard({
  shop,
  selected,
  onSelect,
  compact = false,
}: {
  shop: ShopSummary;
  selected: boolean;
  onSelect: (shop: ShopSummary) => void;
  compact?: boolean;
}) {
  const shopIcon = shop.is_black_market ? '◆' : '🛒';

  return (
    <article className={`card shop-card ${selected ? 'selected' : ''} ${compact ? 'compact' : ''}`}>
      <button className="shop-card-button" onClick={() => onSelect(shop)}>
        <span className="shop-card-icon" aria-hidden="true">{shopIcon}</span>
        <span className="shop-card-main">
          <span className="eyebrow">{shop.shop_type.replace(/_/g, ' ')}</span>
          <strong>{shop.name}</strong>
          <small>{shop.location_label}</small>
          {!compact && <span>{shop.description}</span>}
        </span>
        <span className="shop-card-open">Open</span>
      </button>

      {!compact && (
        <div className="location-effect-summary shop-card-badges">
          <ShopRequirementBadge
            text={shop.local_presence_satisfied ? 'Available here' : 'Travel to trade'}
            tone={shop.local_presence_satisfied ? 'success' : 'warning'}
          />
          {shop.is_black_market && <ShopRequirementBadge text="Black-market contact" tone="danger" />}
          {shop.is_legal && <ShopRequirementBadge text="Legal shop" tone="success" />}
          <ShopRequirementBadge text={`Heat risk ${shop.heat_risk}`} />
        </div>
      )}
    </article>
  );
}
