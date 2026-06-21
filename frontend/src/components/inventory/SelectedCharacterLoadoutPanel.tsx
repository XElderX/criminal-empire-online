import { CrewPortrait } from '../game/CrewPortrait';
import { CarryInventoryGrid } from './CarryInventoryGrid';
import { EquipmentSlotGrid } from './EquipmentSlotGrid';
import { LoadoutSliders } from './LoadoutSliders';
import { LoadoutWarningList } from './LoadoutWarningList';
import type { LoadoutCharacterSummary, LoadoutSummary } from '../../types';

interface SelectedCharacterLoadoutPanelProps {
  character: LoadoutCharacterSummary;
  loadout: LoadoutSummary;
  selectedSlot: string;
  loading: boolean;
  onSelectSlot: (slot: string) => void;
  onUnequip: (slot: string) => void;
  onEquipCarried: (item: { id?: number; name: string; asset_type?: string; allowed_slots?: string[]; equipment_slot?: string }) => void;
  onStoreCarried: (itemId: number) => void;
}

export function SelectedCharacterLoadoutPanel({
  character,
  loadout,
  selectedSlot,
  loading,
  onSelectSlot,
  onUnequip,
  onEquipCarried,
  onStoreCarried,
}: SelectedCharacterLoadoutPanelProps) {
  const roleName = typeof character.role === 'object' && character.role && 'name' in character.role ? character.role.name : character.character_type;
  const used = Number(loadout.used_carry_units ?? character.used_carry_units ?? 0);
  const capacity = Number(loadout.carry_capacity_units ?? character.carry_capacity_units ?? 5);

  return (
    <section className="selected-loadout-panel">
      <div className="selected-character-dossier">
        <CrewPortrait
          gender={character.gender}
          portraitKey={character.portrait_set_key || undefined}
          age={character.age}
          alt={character.display_name}
          size="profile"
        />
        <div className="selected-character-copy">
          <p className="eyebrow">Selected character</p>
          <h2>{character.display_name}</h2>
          <p className="muted">{roleName} · {character.status}</p>
          <div className="gear-chip-row">
            <span className="info-pill">Health {character.health}/{character.max_health}</span>
            {character.morale !== null && character.morale !== undefined && <span className="info-pill">Morale {character.morale}</span>}
            {character.loyalty !== null && character.loyalty !== undefined && <span className="info-pill">Loyalty {character.loyalty}</span>}
            <span className={`info-pill ${(character.personal_heat ?? 0) > 25 ? 'danger' : ''}`}>Heat {character.personal_heat ?? 0}</span>
          </div>
          <p className="muted">Equipped gear is worn in a body/tool/weapon slot. Carried items are task tools, consumables, and temporary crime-use items this character brings along.</p>
        </div>
        <div className="carry-meter-card">
          <span className="eyebrow">Carry inventory</span>
          <strong>{used.toFixed(1)} / {capacity.toFixed(1)}</strong>
          <small>carry units used</small>
        </div>
      </div>

      <div className="loadout-builder-grid">
        <div className="loadout-builder-main">
          <div className="subsection-heading-row">
            <div>
              <h3>Equipped gear slots</h3>
              <p className="muted">Click an empty slot to filter compatible owned items. Click filled gear to unequip it.</p>
            </div>
            {selectedSlot && <button className="btn compact" onClick={() => onSelectSlot('')}>Clear slot filter</button>}
          </div>
          <EquipmentSlotGrid
            slots={loadout.slots ?? []}
            equipped={loadout.equipped ?? []}
            loading={loading}
            selectedSlot={selectedSlot}
            onSelectSlot={onSelectSlot}
            onUnequip={onUnequip}
          />
        </div>
        <aside className="loadout-builder-side">
          <CarryInventoryGrid
            carried={loadout.carried ?? []}
            loading={loading}
            onEquip={onEquipCarried}
            onStore={(item) => item.id && onStoreCarried(item.id)}
          />
          <LoadoutSliders scores={loadout.scores} />
          <LoadoutWarningList warnings={loadout.warnings} />
        </aside>
      </div>
    </section>
  );
}
