import { CarryInventoryGrid } from './CarryInventoryGrid';
import { EquipmentSlotGrid } from './EquipmentSlotGrid';
import { LoadoutSliders } from './LoadoutSliders';
import { LoadoutWarningList } from './LoadoutWarningList';

interface CharacterLoadoutPanelProps {
  title: string;
  loadout?: {
    slots?: string[];
    equipped?: Array<{ equipped_slot?: string; name: string; id?: number }>;
    carried?: Array<{ name: string; quantity?: number; carry_units?: number }>;
    scores?: Record<string, number>;
    warnings?: string[];
    carry_capacity_units?: number;
    used_carry_units?: number;
  } | null;
}

export function CharacterLoadoutPanel({ title, loadout }: CharacterLoadoutPanelProps) {
  return (
    <section className="card section-card character-loadout-panel">
      <div className="card-heading">
        <div>
          <p className="eyebrow">Character loadout</p>
          <h2>{title}</h2>
          <p className="muted">
            Gear slots, carried inventory, carry capacity, and risk scores now matter for local actions.
          </p>
        </div>
        <span className="version-badge">
          {Number(loadout?.used_carry_units ?? 0).toFixed(1)} / {Number(loadout?.carry_capacity_units ?? 5).toFixed(1)} units
        </span>
      </div>
      <EquipmentSlotGrid slots={loadout?.slots ?? []} equipped={loadout?.equipped ?? []} />
      <CarryInventoryGrid carried={loadout?.carried ?? []} />
      <LoadoutSliders scores={loadout?.scores} />
      <LoadoutWarningList warnings={loadout?.warnings} />
    </section>
  );
}
