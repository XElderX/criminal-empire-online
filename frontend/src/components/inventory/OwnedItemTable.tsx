import type { InventoryAsset } from '../../types';
import { ItemEffectBadge } from './ItemEffectBadge';

export function OwnedItemTable({ items }: { items: InventoryAsset[] }) {
  if (items.length === 0) {
    return <p className="muted">No owned items yet. Travel to map shops to buy starter gear.</p>;
  }

  return (
    <div className="responsive-table-wrap">
      <table className="compact-data-table">
        <thead>
          <tr><th>Item</th><th>Qty</th><th>Size</th><th>Legality</th><th>Effects</th></tr>
        </thead>
        <tbody>
          {items.map((item) => (
            <tr key={`${item.id}-${item.name}`}>
              <td><strong>{item.name}</strong><small>{item.category || item.class || 'item'}</small></td>
              <td>{item.quantity}</td>
              <td>{item.size_class || 'small'} · {item.carry_units ?? 1} units</td>
              <td>{item.legality || (item.illegal ? 'illegal' : 'legal')}</td>
              <td>{Object.entries(item.effects || {}).slice(0, 3).map(([key, value]) => <ItemEffectBadge key={key} label={key} value={value} />)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
