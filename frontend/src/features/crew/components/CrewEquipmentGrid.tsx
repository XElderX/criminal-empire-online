import type { CrewEquipment } from '../../../types';

export function CrewEquipmentGrid({
  equipment,
  compact = false,
}: {
  equipment: CrewEquipment[];
  compact?: boolean;
}) {
  const visibleEquipment = compact ? equipment.slice(0, 4) : equipment;

  if (visibleEquipment.length === 0) {
    return <p className="muted crew-empty-line">No equipment assigned.</p>;
  }

  return (
    <div className="crew-equipment-grid">
      {visibleEquipment.map((item) => (
        <div
          className={`crew-equipment-slot ${item.durability <= 0 ? 'is-broken' : ''}`}
          title={`${item.name} · ${item.equipment_slot} · ${item.durability}% condition`}
          key={item.id}
        >
          <span className="crew-equipment-symbol" aria-hidden="true">
            {equipmentSymbol(item.equipment_slot)}
          </span>
          <div>
            <strong>{item.name}</strong>
            <small>{item.equipment_slot} · {item.durability}%</small>
          </div>
        </div>
      ))}
    </div>
  );
}

function equipmentSymbol(slot: string): string {
  const normalized = slot.toLowerCase();

  if (normalized.includes('weapon') || normalized.includes('sidearm')) {
    return '⌖';
  }

  if (normalized.includes('tool')) {
    return '⌁';
  }

  if (normalized.includes('armor')) {
    return '⬡';
  }

  if (normalized.includes('utility')) {
    return '▣';
  }

  return '◆';
}
