interface EquipmentSlotGridProps {
  slots: string[];
  equipped?: Array<{ equipped_slot?: string; name: string; id?: number }>;
}

const SLOT_META: Record<string, { label: string; icon: string; area: string }> = {
  head: { label: 'Head', icon: '🎩', area: 'head' },
  torso: { label: 'Torso', icon: '🧥', area: 'torso' },
  legs: { label: 'Legs', icon: '👖', area: 'legs' },
  boots: { label: 'Boots', icon: '🥾', area: 'boots' },
  hands: { label: 'Hands', icon: '🧤', area: 'hands' },
  primary_weapon: { label: 'Primary', icon: '▰', area: 'primary' },
  sidearm: { label: 'Sidearm', icon: '◩', area: 'sidearm' },
  melee: { label: 'Melee', icon: '✦', area: 'melee' },
  tool: { label: 'Tool', icon: '🛠', area: 'tool' },
  utility_1: { label: 'Utility 1', icon: '◈', area: 'utility1' },
  utility_2: { label: 'Utility 2', icon: '◇', area: 'utility2' },
  bag: { label: 'Bag', icon: '▣', area: 'bag' },
  armor: { label: 'Armor', icon: '⬟', area: 'armor' },
  disguise: { label: 'Disguise', icon: '◍', area: 'disguise' },
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
    <div className="loadout-board" aria-label="Character equipment slots">
      <div className="loadout-silhouette" aria-hidden="true">
        <span className="silhouette-head" />
        <span className="silhouette-body" />
      </div>
      {orderedSlots.map((slot) => {
        const item = equipped.find((entry) => entry.equipped_slot === slot);
        const meta = SLOT_META[slot] ?? { label: humanize(slot), icon: '□', area: 'other' };
        return (
          <article className={`equipment-slot-card ${item ? 'filled' : 'empty'} slot-${meta.area}`} key={slot}>
            <span className="slot-icon" aria-hidden="true">{meta.icon}</span>
            <span className="slot-label">{meta.label}</span>
            <strong>{item?.name ?? 'Empty'}</strong>
          </article>
        );
      })}
    </div>
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
