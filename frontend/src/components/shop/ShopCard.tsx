import type { ShopSummary } from '../../types/shop';
import { ShopRequirementBadge } from './ShopRequirementBadge';

export function ShopCard({ shop, selected, onSelect }: { shop: ShopSummary; selected: boolean; onSelect: (shop: ShopSummary) => void }) {
  return (
    <article className={`card shop-card ${selected ? 'selected' : ''}`}>
      <div className="card-heading small-heading">
        <div>
          <p className="eyebrow">{shop.shop_type.replace(/_/g, ' ')}</p>
          <h3>{shop.name}</h3>
          <p className="muted">{shop.location_label}</p>
        </div>
        <button className="btn" onClick={() => onSelect(shop)}>Open</button>
      </div>
      <p>{shop.description}</p>
      <div className="location-effect-summary">
        <ShopRequirementBadge text={shop.local_presence_satisfied ? 'Available here' : 'Travel to trade'} tone={shop.local_presence_satisfied ? 'success' : 'warning'} />
        {shop.is_black_market && <ShopRequirementBadge text="Black-market contact" tone="danger" />}
        {shop.is_legal && <ShopRequirementBadge text="Legal shop" tone="success" />}
        <ShopRequirementBadge text={`Heat risk ${shop.heat_risk}`} />
      </div>
    </article>
  );
}
