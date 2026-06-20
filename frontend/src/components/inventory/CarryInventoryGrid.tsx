import { useState } from 'react';
import { getItemIcon } from '../../data/assetManifest';

interface CarryItem {
  name: string;
  quantity?: number;
  carry_units?: number;
  carry_units_each?: number;
  category?: string;
  class?: string;
  asset_type?: string;
}

export function CarryInventoryGrid({ carried = [] }: { carried?: CarryItem[] }) {
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
          {carried.map((item) => <CarryCard item={item} key={`${item.name}-${item.category || item.class || ''}`} />)}
        </div>
      )}
    </div>
  );
}

function CarryCard({ item }: { item: CarryItem }) {
  const [source, setSource] = useState(getItemIcon(item.name, item.category || item.class || item.asset_type));
  const units = Number(item.carry_units ?? item.carry_units_each ?? 1);

  return (
    <article className="carry-card visual-carry-card">
      <div className="carry-card-image">
        <img src={source} alt="" loading="lazy" onError={() => setSource('/assets/placeholders/default_item.webp')} />
      </div>
      <div>
        <strong>{item.name}</strong>
        <span>Qty {item.quantity ?? 1}</span>
        <small>{units} carry unit{units === 1 ? '' : 's'} each</small>
      </div>
    </article>
  );
}
