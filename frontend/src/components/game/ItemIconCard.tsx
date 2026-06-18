import { useState } from 'react';
import { getItemIcon } from '../../data/assetManifest';
import type { InventoryAsset } from '../../types';

interface ItemIconCardProps {
  item: Pick<InventoryAsset, 'name' | 'category' | 'class' | 'equipment_slot' | 'quantity' | 'description'> & Partial<InventoryAsset>;
  footer?: React.ReactNode;
  compact?: boolean;
}

export function ItemIconCard({ item, footer, compact = false }: ItemIconCardProps) {
  const [source, setSource] = useState(getItemIcon(item.name, item.category || item.class));

  return (
    <article className={`item-icon-card ${compact ? 'item-icon-card-compact' : ''}`}>
      <div className="item-icon-frame">
        <img
          src={source}
          alt=""
          loading="lazy"
          onError={() => setSource('/assets/placeholders/default_item.webp')}
        />
      </div>
      <div className="item-icon-content">
        <p className="eyebrow">{item.equipment_slot || item.category || item.class || 'Item'}</p>
        <h3>{item.name}</h3>
        {!compact && item.description && <p className="muted">{item.description}</p>}
        <span className="status-badge">Qty {item.quantity ?? 1}</span>
      </div>
      {footer && <div className="item-icon-footer">{footer}</div>}
    </article>
  );
}
