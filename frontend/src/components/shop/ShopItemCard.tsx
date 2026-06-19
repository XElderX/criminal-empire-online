import { getItemIcon } from '../../data/assetManifest';
import type { ShopItem } from '../../types/shop';
import { ShopRequirementBadge } from './ShopRequirementBadge';
import { ShopStockBadge } from './ShopStockBadge';

export function ShopItemCard({ item, busy, onBuy }: { item: ShopItem; busy: boolean; onBuy: (item: ShopItem) => void }) {
  return (
    <article className="item-icon-card shop-item-card">
      <img src={getItemIcon(item.item_key || item.category)} alt="" />
      <div>
        <p className="eyebrow">{item.category.replace(/_/g, ' ')}</p>
        <h3>{item.name}</h3>
        <p>{item.description || 'Shop catalog item.'}</p>
        <div className="location-effect-summary">
          <ShopRequirementBadge text={`$${item.buy_price.toLocaleString()}`} tone="success" />
          <ShopStockBadge stock={item.stock_quantity} maxStock={item.max_stock} />
          <ShopRequirementBadge text={item.availability_status.replace(/_/g, ' ')} tone={item.is_enabled ? 'neutral' : 'danger'} />
        </div>
        {item.locked_reasons.length > 0 && <p className="warning-text">{item.locked_reasons.join(' ')}</p>}
        {item.warnings.length > 0 && <p className="muted">{item.warnings.join(' ')}</p>}
        <button className="btn primary full-width" disabled={busy || !item.can_buy} onClick={() => onBuy(item)}>
          {item.can_buy ? 'Buy one' : (item.disabled_reason || 'Unavailable')}
        </button>
      </div>
    </article>
  );
}
