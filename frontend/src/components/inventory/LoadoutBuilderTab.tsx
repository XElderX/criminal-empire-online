import { LoadoutCharacterSelector } from './LoadoutCharacterSelector';
import { LoadoutOwnedItemPool } from './LoadoutOwnedItemPool';
import { SelectedCharacterLoadoutPanel } from './SelectedCharacterLoadoutPanel';
import type { LoadoutCharacterSummary, LoadoutWorkspaceItem, LoadoutWorkspaceResponse } from '../../types';

interface LoadoutBuilderTabProps {
  workspace: LoadoutWorkspaceResponse;
  selectedSlot: string;
  loading: boolean;
  onSelectCharacter: (character: LoadoutCharacterSummary) => void;
  onSelectSlot: (slot: string) => void;
  onEquip: (item: LoadoutWorkspaceItem, slot?: string | null) => void;
  onCarry: (item: LoadoutWorkspaceItem) => void;
  onUnequip: (slot: string) => void;
  onEquipCarried: (item: { id?: number; name: string; asset_type?: string; allowed_slots?: string[]; equipment_slot?: string }) => void;
  onStoreCarried: (itemId: number) => void;
}

export function LoadoutBuilderTab({ workspace, selectedSlot, loading, onSelectCharacter, onSelectSlot, onEquip, onCarry, onUnequip, onEquipCarried, onStoreCarried }: LoadoutBuilderTabProps) {
  return (
    <div className="loadout-builder-tab">
      <LoadoutCharacterSelector
        characters={workspace.characters}
        selectedKey={workspace.selected_character.key}
        onSelect={onSelectCharacter}
      />

      <SelectedCharacterLoadoutPanel
        character={workspace.selected_character}
        loadout={workspace.loadout}
        selectedSlot={selectedSlot}
        loading={loading}
        onSelectSlot={onSelectSlot}
        onUnequip={onUnequip}
        onEquipCarried={onEquipCarried}
        onStoreCarried={onStoreCarried}
      />

      <LoadoutOwnedItemPool
        character={workspace.selected_character}
        items={workspace.owned_items}
        selectedSlot={selectedSlot}
        loading={loading}
        onSelectSlot={onSelectSlot}
        onEquip={onEquip}
        onCarry={onCarry}
        onUnequip={onUnequip}
        onStoreCarried={onStoreCarried}
      />
    </div>
  );
}
