import { useState } from 'react';
import { getItemIcon } from '../../data/assetManifest';

interface EquippedEntry {
  equipped_slot?: string;
  name: string;
  id?: number;
  asset_type?: string;
  category?: string;
  class?: string;
  description?: string;
}

interface EquipmentSlotGridProps {
  slots: string[];
  equipped?: EquippedEntry[];
}

const SLOT_META: Record<string, { label: string; icon: string; area: string; hint: string }> = {
  head: { label: 'Head', icon: '🎩', area: 'head', hint: 'Mask, cap, helmet' },
  torso: { label: 'Torso', icon: '🧥', area: 'torso', hint: 'Clothing, uniform' },
  legs: { label: 'Legs', icon: '👖', area: 'legs', hint: 'Pants, cargo gear' },
  boots: { label: 'Boots', icon: '🥾', area: 'boots', hint: 'Boots, shoes' },
  hands: { label: 'Hands', icon: '🧤', area: 'hands', hint: 'Gloves' },
  primary_weapon: { label: 'Primary', icon: '▰', area: 'primary', hint: 'Large weapon' },
  sidearm: { label: 'Sidearm', icon: '◩', area: 'sidearm', hint: 'Pistol/revolver' },
  melee: { label: 'Melee', icon: '✦', area: 'melee', hint: 'Knife/baton' },
  tool: { label: 'Tool', icon: '🛠', area: 'tool', hint: 'Lockpick/crowbar' },
  utility_1: { label: 'Utility 1', icon: '◈', area: 'utility1', hint: 'Phone/flashlight' },
  utility_2: { label: 'Utility 2', icon: '◇', area: 'utility2', hint: 'Medical/extra' },
  bag: { label: 'Bag', icon: '▣', area: 'bag', hint: 'Backpack/duffel' },
  armor: { label: 'Armor', icon: '⬟', area: 'armor', hint: 'Vest/protection' },
  disguise: { label: 'Disguise', icon: '◍', area: 'disguise', hint: 'Cover identity' },
};

const DEFAULT_SLOT_ORDER = [
  'head',
  'disguise',
  'torso',
  'armor',
  'legs',
  'boots',
  'hands',
  'primary_weapon',
  'sidearm',
  'melee',
  'tool',
  'utility_1',
  'utility_2',
  'bag',
];

export function EquipmentSlotGrid({ slots, equipped = [] }: EquipmentSlotGridProps) {
  const orderedSlots = normalizeSlots(slots);

  return (
    <div className="loadout-board polished-loadout-board" aria-label="Character equipment slots">
      <div className="loadout-silhouette" aria-hidden="true">
        <span className="silhouette-head" />
        <span className="silhouette-body" />
      </div>
      {orderedSlots.map((slot) => {
        const item = equipped.find((entry) => entry.equipped_slot === slot);
        const meta = SLOT_META[slot] ?? { label: humanize(slot), icon: '□', area: 'other', hint: 'Gear slot' };
        return (
          <EquipmentSlotCard item={item} meta={meta} slot={slot} key={slot} />
        );
      })}
    </div>
  );
}

function EquipmentSlotCard({ item, meta, slot }: { item?: EquippedEntry; meta: { label: string; icon: string; area: string; hint: string }; slot: string }) {
  const [imageSource, setImageSource] = useState(item ? getItemIcon(item.name, item.category || item.class || item.asset_type) : '');

  return (
    <article className={`equipment-slot-card ${item ? 'filled visual-filled' : 'empty'} slot-${meta.area}`}>
      {item ? (
        <>
          <div className="slot-item-image">
            <img src={imageSource} alt="" loading="lazy" onError={() => setImageSource('/assets/placeholders/default_item.webp')} />
          </div>
          <span className="slot-label">{meta.label}</span>
          <strong title={item.name}>{item.name}</strong>
          <small>{humanize(item.category || item.class || item.asset_type || 'equipped')}</small>
        </>
      ) : (
        <>
          <span className="slot-icon" aria-hidden="true">{meta.icon}</span>
          <span className="slot-label">{meta.label}</span>
          <strong>Empty</strong>
          <small>{meta.hint}</small>
        </>
      )}
      <span className="slot-code">{humanize(slot)}</span>
    </article>
  );
}

function normalizeSlots(slots: string[]): string[] {
  const source = slots.length > 0 ? slots : DEFAULT_SLOT_ORDER;
  const unique = new Set(source);
  return DEFAULT_SLOT_ORDER.filter((slot) => unique.has(slot)).concat(source.filter((slot) => !DEFAULT_SLOT_ORDER.includes(slot)));
}

function humanize(value: string): string {
  return value.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
}
