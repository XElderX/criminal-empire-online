import { useMemo, useState } from 'react';
import { getItemIcon } from '../../data/assetManifest';
import type { LoadoutCharacterSummary, LoadoutWorkspaceItem } from '../../types';

interface LoadoutOwnedItemPoolProps {
  character: LoadoutCharacterSummary;
  items: LoadoutWorkspaceItem[];
  selectedSlot: string;
  loading: boolean;
  onSelectSlot: (slot: string) => void;
  onEquip: (item: LoadoutWorkspaceItem, slot?: string | null) => void;
  onCarry: (item: LoadoutWorkspaceItem) => void;
  onUnequip: (slot: string) => void;
  onStoreCarried: (itemId: number) => void;
}

type PoolFilter = 'usable' | 'slot' | 'equipment' | 'carry' | 'tools' | 'weapons' | 'clothing' | 'illegal' | 'assigned' | 'all';

const FILTERS: Array<[PoolFilter, string]> = [
  ['usable', 'Usable now'],
  ['slot', 'Selected slot'],
  ['equipment', 'Equipment'],
  ['carry', 'Carry items'],
  ['tools', 'Tools / utility'],
  ['weapons', 'Weapons'],
  ['clothing', 'Clothing / armor'],
  ['illegal', 'Illegal / suspicious'],
  ['assigned', 'Already assigned'],
  ['all', 'All owned'],
];

export function LoadoutOwnedItemPool({ character, items, selectedSlot, loading, onSelectSlot, onEquip, onCarry, onUnequip, onStoreCarried }: LoadoutOwnedItemPoolProps) {
  const [filter, setFilter] = useState<PoolFilter>('usable');
  const filtered = useMemo(() => items.filter((item) => filterItem(item, filter, selectedSlot)), [items, filter, selectedSlot]);

  return (
    <section className="loadout-owned-pool">
      <div className="section-heading-row">
        <div>
          <p className="eyebrow">Owned item pool</p>
          <h2>Gear available for {character.display_name}</h2>
          <p className="muted">Equip wearable gear into slots, or carry consumables, tools, task items, and crime utility. Backend validation still controls every action.</p>
        </div>
        {selectedSlot && <span className="info-pill success">Filtering for {humanize(selectedSlot)}</span>}
      </div>

      <div className="pool-filter-row" role="tablist" aria-label="Owned item filters">
        {FILTERS.map(([key, label]) => (
          <button type="button" key={key} className={filter === key ? 'active' : ''} onClick={() => setFilter(key)}>{label}</button>
        ))}
      </div>

      {filtered.length === 0 ? (
        <div className="empty-state compact-empty-state">
          <strong>No matching gear.</strong>
          <p>Clear the slot filter or travel to map shops to buy more items.</p>
        </div>
      ) : (
        <div className="loadout-owned-grid">
          {filtered.map((item) => (
            <CompatibleItemCard
              key={`${item.asset_type}-${item.id}`}
              item={item}
              selectedSlot={selectedSlot}
              loading={loading}
              onSelectSlot={onSelectSlot}
              onEquip={onEquip}
              onCarry={onCarry}
              onUnequip={onUnequip}
              onStoreCarried={onStoreCarried}
            />
          ))}
        </div>
      )}
    </section>
  );
}

function CompatibleItemCard({ item, selectedSlot, loading, onSelectSlot, onEquip, onCarry, onUnequip, onStoreCarried }: {
  item: LoadoutWorkspaceItem;
  selectedSlot: string;
  loading: boolean;
  onSelectSlot: (slot: string) => void;
  onEquip: (item: LoadoutWorkspaceItem, slot?: string | null) => void;
  onCarry: (item: LoadoutWorkspaceItem) => void;
  onUnequip: (slot: string) => void;
  onStoreCarried: (itemId: number) => void;
}) {
  const [source, setSource] = useState(getItemIcon(item.name, item.category || item.class || item.asset_type));
  const compatibleSlots = item.compatibleSlots || item.compatible_slots || [];
  const targetSlot = selectedSlot && compatibleSlots.includes(selectedSlot) ? selectedSlot : item.recommendedSlot || item.recommended_slot || compatibleSlots[0] || null;
  const equippedHere = item.equippedBySelected;
  const carriedHere = item.carriedBySelected;

  return (
    <article className={`compatible-item-card ${item.canUseForSelectedCharacter ? 'usable' : ''} ${item.unavailableReason ? 'blocked' : ''}`}>
      <div className="compatible-item-media">
        <img src={source} alt="" loading="lazy" onError={() => setSource('/assets/placeholders/default_item.webp')} />
      </div>
      <div className="compatible-item-body">
        <div className="compatible-item-title-row">
          <div>
            <p className="eyebrow">{item.role_label || humanize(item.category || item.class || item.asset_type)}</p>
            <h3>{item.name}</h3>
          </div>
          <span className="info-pill">Avail {item.quantityAvailable ?? item.quantity_available ?? item.available_quantity ?? item.quantity ?? 0}</span>
        </div>
        {item.description && <p className="muted item-card-description">{item.description}</p>}
        <div className="gear-chip-row compact-chip-row">
          <span className="info-pill">{humanize(String(item.size_class || 'small'))} · {Number(item.carry_units ?? 1)} units</span>
          <span className={`info-pill ${isIllegal(item) ? 'danger' : ''}`}>{humanize(String(item.legality || (item.illegal ? 'illegal' : 'legal')))}</span>
          {(item.compatibleSlots || []).slice(0, 3).map((slot) => (
            <button className={`slot-filter-chip ${selectedSlot === slot ? 'active' : ''}`} key={slot} onClick={() => onSelectSlot(slot)} type="button">{humanize(slot)}</button>
          ))}
        </div>
        <ItemTextList title="Helps" entries={item.benefits || []} />
        <ItemTextList title="Tradeoffs" entries={item.tradeoffs || []} danger />
        {item.carryPurpose && <p className="carry-purpose-note">Carry purpose: {item.carryPurpose}</p>}
        {item.currentlyEquippedBy && item.currentlyEquippedBy.length > 0 && <small className="muted">Equipped by: {item.currentlyEquippedBy.map((entry) => `${entry.display_name}${entry.slot ? ` (${humanize(entry.slot)})` : ''}`).join(', ')}</small>}
        {item.currentlyCarriedBy && item.currentlyCarriedBy.length > 0 && <small className="muted">Carried by: {item.currentlyCarriedBy.map((entry) => `${entry.display_name} ×${entry.quantity ?? 1}`).join(', ')}</small>}
        {item.unavailableReason && <p className="item-unavailable-reason">{item.unavailableReason}</p>}
      </div>
      <div className="compatible-item-actions">
        {equippedHere?.equipped_slot ? (
          <button className="btn danger full-width" disabled={loading} onClick={() => onUnequip(equippedHere.equipped_slot || '')}>Unequip from {humanize(equippedHere.equipped_slot || '')}</button>
        ) : (
          <button className="btn primary full-width" disabled={loading || !item.canEquip || !targetSlot} onClick={() => onEquip(item, targetSlot)}>Equip{targetSlot ? ` to ${humanize(targetSlot)}` : ''}</button>
        )}
        {carriedHere ? (
          <button className="btn full-width" disabled={loading} onClick={() => onStoreCarried(item.id)}>Remove from carried</button>
        ) : (
          <button className="btn full-width" disabled={loading || !item.canCarry} onClick={() => onCarry(item)}>Carry for tasks</button>
        )}
      </div>
    </article>
  );
}

function ItemTextList({ title, entries, danger = false }: { title: string; entries: string[]; danger?: boolean }) {
  if (entries.length === 0) return null;
  return (
    <div className={`item-text-list ${danger ? 'danger' : ''}`}>
      <strong>{title}</strong>
      <ul>{entries.slice(0, 4).map((entry) => <li key={entry}>{entry}</li>)}</ul>
    </div>
  );
}

function filterItem(item: LoadoutWorkspaceItem, filter: PoolFilter, selectedSlot: string): boolean {
  const category = String(item.category || item.class || '').toLowerCase();
  const role = String(item.item_role || '').toLowerCase();
  const carryRole = String(item.carry_role || '').toLowerCase();
  const slots = item.compatibleSlots || item.compatible_slots || [];
  switch (filter) {
    case 'usable': return Boolean(item.canEquip || item.canCarry || item.equippedBySelected || item.carriedBySelected);
    case 'slot': return selectedSlot === '' ? true : slots.includes(selectedSlot);
    case 'equipment': return Boolean(item.canEquip || role === 'equipped_gear' || role === 'weapon');
    case 'carry': return Boolean(item.canCarry || ['consumable', 'carry_tool', 'crime_utility', 'task_item'].includes(carryRole));
    case 'tools': return category.includes('tool') || category.includes('utility') || role === 'tool';
    case 'weapons': return item.asset_type === 'weapon' || role === 'weapon' || category.includes('weapon');
    case 'clothing': return ['clothing', 'armor'].some((needle) => category.includes(needle)) || role === 'equipped_gear';
    case 'illegal': return isIllegal(item) || String(item.legality || '').includes('suspicious');
    case 'assigned': return Boolean(item.currentlyEquippedBy?.length || item.currentlyCarriedBy?.length);
    case 'all': return true;
    default: return true;
  }
}

function isIllegal(item: LoadoutWorkspaceItem): boolean {
  return Boolean(item.illegal || item.visible_illegal || ['illegal', 'restricted', 'suspicious'].includes(String(item.legality || '').toLowerCase()));
}

function humanize(value: string | undefined): string {
  return String(value || 'item').split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
}
