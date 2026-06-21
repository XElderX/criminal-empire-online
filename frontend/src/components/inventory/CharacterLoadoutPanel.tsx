import { CarryInventoryGrid } from './CarryInventoryGrid';
import { EquipmentSlotGrid } from './EquipmentSlotGrid';
import { LoadoutSliders } from './LoadoutSliders';
import { LoadoutWarningList } from './LoadoutWarningList';

interface CharacterLoadoutPanelProps {
  title: string;
  loadout?: {
    slots?: string[];
    equipped?: Array<{ equipped_slot?: string; name: string; id?: number }>;
    carried?: Array<{
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
    }>;
    scores?: Record<string, number>;
    warnings?: string[];
    carry_capacity_units?: number;
    used_carry_units?: number;
  } | null;
  loading?: boolean;
  onUnequip?: (slot: string) => void;
  onEquipCarried?: (item: {
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
  }) => void;
}

export function CharacterLoadoutPanel({ title, loadout, loading = false, onUnequip, onEquipCarried }: CharacterLoadoutPanelProps) {
  const usedCarry = Number(loadout?.used_carry_units ?? 0);
  const maxCarry = Number(loadout?.carry_capacity_units ?? 5);

  return (
    <section className="character-loadout-panel">
      <div className="loadout-header-card">
        <div>
          <p className="eyebrow">Character loadout</p>
          <h2>{title}</h2>
          <p className="muted">Equip body slots, tools, weapons, utility gear, bags, and carried items. The board shows what this character is actually taking into the streets.</p>
        </div>
        <span className="loadout-capacity-badge">{usedCarry.toFixed(1)} / {maxCarry.toFixed(1)} carry units</span>
      </div>

      <div className="loadout-workspace">
        <EquipmentSlotGrid slots={loadout?.slots ?? []} equipped={loadout?.equipped ?? []} loading={loading} onUnequip={onUnequip} />
        <aside className="loadout-side-panel">
          <CarryInventoryGrid carried={loadout?.carried ?? []} loading={loading} onEquip={onEquipCarried} />
          <LoadoutSliders scores={loadout?.scores} />
          <LoadoutWarningList warnings={loadout?.warnings} />
        </aside>
      </div>
    </section>
  );
}
