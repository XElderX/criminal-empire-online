import { getItemIcon } from '../../data/assetManifest';
import type { ShopItem } from '../../types/shop';
import { ShopRequirementBadge } from './ShopRequirementBadge';
import { ShopStockBadge } from './ShopStockBadge';

export function ShopItemCard({
  item,
  busy,
  onBuy,
}: {
  item: ShopItem;
  busy: boolean;
  onBuy: (item: ShopItem) => void;
}) {
  const unavailableText = item.disabled_reason || item.locked_reasons[0] || 'Unavailable';

  return (
    <article className={`shop-item-card-clean ${!item.can_buy ? 'is-locked' : ''}`}>
      <div className="shop-item-thumb">
        <img src={getItemIcon(item.item_key || item.category, item.category)} alt="" />
      </div>

      <div className="shop-item-body">
        <div className="shop-item-heading">
          <div>
            <p className="eyebrow">{item.category.replace(/_/g, ' ')}</p>
            <h3>{item.name}</h3>
          </div>
          <ShopRequirementBadge
            text={`$${item.buy_price.toLocaleString()}`}
            tone={item.can_buy ? 'success' : 'neutral'}
          />
        </div>

        <p className="shop-item-description">
          {item.description || 'Shop catalog item.'}
        </p>

        <div className="shop-item-meta-row">
          <ShopStockBadge stock={item.stock_quantity} maxStock={item.max_stock} />
          <ShopRequirementBadge
            text={item.availability_status.replace(/_/g, ' ')}
            tone={item.is_enabled ? 'neutral' : 'danger'}
          />
        </div>

        {item.locked_reasons.length > 0 && (
          <p className="warning-text shop-item-warning">{item.locked_reasons.join(' ')}</p>
        )}
        {item.warnings.length > 0 && (
          <p className="muted shop-item-warning">{item.warnings.join(' ')}</p>
        )}

        <button
          className="btn primary full-width"
          disabled={busy || !item.can_buy}
          onClick={() => onBuy(item)}
        >
          {item.can_buy ? 'Buy one' : unavailableText}
        </button>
      </div>
    </article>
  );
}
