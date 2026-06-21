import { useState } from 'react';
import { getItemIcon } from '../../data/assetManifest';

interface CarryItem {
  id?: number;
  name: string;
  quantity?: number;
  carry_units?: number;
  carry_units_each?: number;
  category?: string;
  class?: string;
  asset_type?: string;
  equipment_slot?: string;
  allowed_slots?: string[];
  is_equippable?: number | boolean;
}

export function CarryInventoryGrid({
  carried = [],
  loading = false,
  onEquip,
  onStore,
}: {
  carried?: CarryItem[];
  loading?: boolean;
  onEquip?: (item: CarryItem) => void;
  onStore?: (item: CarryItem) => void;
}) {
  return (
    <div className="carry-panel">
      <div className="loadout-score-heading">
        <span className="eyebrow">Carried inventory</span>
        <strong>{carried.length} task item{carried.length === 1 ? '' : 's'} carried</strong>
      </div>
      {carried.length === 0 ? (
        <p className="muted">No carried items. Carried inventory is for consumables, tools, task items, and crime utility this character brings into jobs. It is not the same as equipped gear.</p>
      ) : (
        <div className="carry-grid visual-carry-grid">
          {carried.map((item) => (
            <CarryCard
              item={item}
              key={`${item.id || item.name}-${item.category || item.class || ''}`}
              loading={loading}
              onEquip={onEquip}
              onStore={onStore}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function CarryCard({ item, loading, onEquip, onStore }: { item: CarryItem; loading: boolean; onEquip?: (item: CarryItem) => void; onStore?: (item: CarryItem) => void }) {
  const [source, setSource] = useState(getItemIcon(item.name, item.category || item.class || item.asset_type));
  const units = Number(item.carry_units ?? item.carry_units_each ?? 1);
  const canEquip = item.is_equippable !== 0 && item.is_equippable !== false;
  const clickable = Boolean(onEquip && canEquip);

  return (
    <article
      className={`carry-card visual-carry-card ${clickable ? 'clickable' : ''} ${loading ? 'busy' : ''}`}
      onClick={() => {
        if (!clickable || loading) return;
        onEquip?.(item);
      }}
      role={clickable ? 'button' : undefined}
      tabIndex={clickable ? 0 : undefined}
      aria-disabled={clickable ? loading : undefined}
      onKeyDown={(event) => {
        if (!clickable || loading) return;
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          onEquip?.(item);
        }
      }}
      title={clickable ? `Click to equip ${item.name}` : undefined}
    >
      <div className="carry-card-image">
        <img src={source} alt="" loading="lazy" onError={() => setSource('/assets/placeholders/default_item.webp')} />
      </div>
      <div>
        <strong>{item.name}</strong>
        <span>Qty {item.quantity ?? 1}</span>
        <small>{loading ? 'Updating...' : clickable ? `Can equip from carried · ${units} carry unit${units === 1 ? '' : 's'} each` : `Task/utility item · ${units} carry unit${units === 1 ? '' : 's'} each`}</small>
        {onStore && item.id && <button type="button" className="btn compact" disabled={loading} onClick={(event) => { event.stopPropagation(); onStore(item); }}>Remove / store</button>}
      </div>
    </article>
  );
}
