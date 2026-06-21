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
}: {
  carried?: CarryItem[];
  loading?: boolean;
  onEquip?: (item: CarryItem) => void;
}) {
  return (
    <div className="carry-panel">
      <div className="loadout-score-heading">
        <span className="eyebrow">Carried inventory</span>
        <strong>{carried.length} carried item{carried.length === 1 ? '' : 's'}</strong>
      </div>
      {carried.length === 0 ? (
        <p className="muted">No carried items. Carrying tools, medical gear, or bags can unlock options, but extra bulk increases risk.</p>
      ) : (
        <div className="carry-grid visual-carry-grid">
          {carried.map((item) => (
            <CarryCard
              item={item}
              key={`${item.id || item.name}-${item.category || item.class || ''}`}
              loading={loading}
              onEquip={onEquip}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function CarryCard({ item, loading, onEquip }: { item: CarryItem; loading: boolean; onEquip?: (item: CarryItem) => void }) {
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
        <small>{loading ? 'Updating...' : clickable ? `Click to equip · ${units} carry unit${units === 1 ? '' : 's'} each` : `${units} carry unit${units === 1 ? '' : 's'} each`}</small>
      </div>
    </article>
  );
}
