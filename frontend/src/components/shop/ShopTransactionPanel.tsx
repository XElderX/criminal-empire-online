import type { SellableInventoryItem } from '../../types/shop';

export function ShopTransactionPanel({ items, busy, onSell }: { items: SellableInventoryItem[]; busy: boolean; onSell: (item: SellableInventoryItem) => void }) {
  return (
    <section className="page-subsection">
      <h2>Sell to this shop</h2>
      {items.length === 0 ? (
        <p className="muted">No unequipped owned items match this shop's buying rules, or you need to travel here first.</p>
      ) : (
        <div className="inventory-icon-grid">
          {items.map((item) => (
            <article className="loadout-slot" key={item.inventory_id}>
              <span>{item.category.replace(/_/g, ' ')}</span>
              <strong>{item.name}</strong>
              <small>Available {item.available_quantity} · ${item.sell_price.toLocaleString()} each</small>
              <button className="btn" disabled={busy || !item.can_sell} onClick={() => onSell(item)}>Sell one</button>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}
