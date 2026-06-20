interface EquipmentSlotGridProps {
  slots: string[];
  equipped?: Array<{ equipped_slot?: string; name: string; id?: number }>;
}

export function EquipmentSlotGrid({ slots, equipped = [] }: EquipmentSlotGridProps) {
  return (
    <div className="equipment-slot-grid">
      {slots.map((slot) => {
        const item = equipped.find((entry) => entry.equipped_slot === slot);
        return (
          <article className={`equipment-slot-card ${item ? 'filled' : ''}`} key={slot}>
            <span>{slot.replace(/_/g, ' ')}</span>
            <strong>{item?.name ?? 'Empty'}</strong>
          </article>
        );
      })}
    </div>
  );
}
