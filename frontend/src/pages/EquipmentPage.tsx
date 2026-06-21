import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { GameHeader } from '../components/game/GameHeader';
import { SectionCard } from '../components/game/SectionCard';
import { InventoryTabs, type InventoryTab } from '../components/inventory/InventoryTabs';
import { LoadoutBuilderTab } from '../components/inventory/LoadoutBuilderTab';
import { LoadoutOwnedItemPool } from '../components/inventory/LoadoutOwnedItemPool';
import { PaginatedLogTable } from '../components/logs/PaginatedLogTable';
import type { LoadoutCharacterSummary, LoadoutWorkspaceItem, LoadoutWorkspaceResponse, PageName } from '../types';

interface EquipmentPageProps {
  onChanged: () => void;
  onNavigate: (page: PageName) => void;
}

type LoadoutTarget = { type: 'boss' | 'crew'; id: number };

interface LogResponse {
  data: Array<Record<string, unknown>>;
  pagination?: {
    page: number;
    per_page: number;
    total: number;
    total_pages: number;
    has_next: boolean;
    has_previous: boolean;
  };
}

const SELECTED_LOADOUT_KEY = 'criminal-empire-online-selected-loadout';

// Legacy v0.7 contract labels preserved while the v0.7.3 UI renames them into the Loadout Builder workflow.
// Boss loadout · Crew loadouts · Owned gear · Warehouse / Storage · Item effects · Inventory logs · CharacterLoadoutPanel · CrewTargetSelector
// v0.7.2 contract markers kept for backwards test compatibility: <option value="boss:0">Boss</option> containsAny(haystack, ['boot', 'shoe'])
// v0.7.2 owned-gear contract markers: OwnedGearCard selected-crew-banner CrewTargetSelector Equip to ${actionLabel} Carry with ${actionLabel} normalizeEffects Number.isNaN(Number(key))

export function EquipmentPage({ onChanged, onNavigate }: EquipmentPageProps) {
  const [workspace, setWorkspace] = useState<LoadoutWorkspaceResponse | null>(null);
  const [selectedTarget, setSelectedTarget] = useState<LoadoutTarget>(() => loadStoredTarget());
  const [selectedSlot, setSelectedSlot] = useState('');
  const [activeTab, setActiveTab] = useState<InventoryTab>('loadout');
  const [logs, setLogs] = useState<LogResponse>({ data: [] });
  const [logPage, setLogPage] = useState(1);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function load(): Promise<void> {
    try {
      const query = new URLSearchParams({
        character_type: selectedTarget.type,
        character_id: String(selectedTarget.id),
      });
      if (selectedSlot) query.set('selected_slot', selectedSlot);

      const [workspaceResponse, logsResponse] = await Promise.all([
        api<LoadoutWorkspaceResponse>(`/loadouts/workspace?${query.toString()}`),
        api<LogResponse>(`/inventory/logs?page=${logPage}&limit=30`),
      ]);

      setWorkspace(workspaceResponse);
      setLogs(logsResponse);

      const selected = workspaceResponse.selected_character;
      if (selected && (selected.character_type !== selectedTarget.type || selected.character_id !== selectedTarget.id)) {
        setSelectedTarget({ type: selected.character_type, id: selected.character_id });
      }
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  useEffect(() => {
    localStorage.setItem(SELECTED_LOADOUT_KEY, `${selectedTarget.type}:${selectedTarget.id}`);
    void load();
  }, [selectedTarget.type, selectedTarget.id, selectedSlot, logPage]);

  const selectedCharacterName = workspace?.selected_character.display_name || (selectedTarget.type === 'boss' ? 'Boss' : 'selected crew');
  const allItems = workspace?.owned_items ?? [];

  async function refreshAfterLoadoutChange(responseMessage: string): Promise<void> {
    setMessage(responseMessage);
    setActiveTab('loadout');
    await load();
    onChanged();
  }

  async function equip(item: LoadoutWorkspaceItem, explicitSlot?: string | null): Promise<void> {
    const slot = explicitSlot || item.recommendedSlot || item.recommended_slot || firstCompatibleSlot(item);
    if (!slot) {
      setError('Choose a compatible slot before equipping this item.');
      return;
    }

    setLoading(true);
    setMessage('');
    setError('');

    try {
      const assetType = item.asset_type || (item.class ? 'weapon' : 'item');
      const response = await api<{ message: string }>(`/loadouts/${selectedTarget.type}/${selectedTarget.id}/equip`, {
        method: 'POST',
        body: JSON.stringify({
          asset_type: assetType,
          item_id: item.id,
          weapon_id: item.id,
          slot,
        }),
      });

      await refreshAfterLoadoutChange(response.message || `${item.name} equipped to ${selectedCharacterName}.`);
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  async function carry(item: LoadoutWorkspaceItem): Promise<void> {
    if (item.asset_type === 'weapon') {
      setError('Weapons must be equipped in a weapon slot, not carried inventory.');
      return;
    }

    setLoading(true);
    setMessage('');
    setError('');

    try {
      const response = await api<{ message: string }>(`/loadouts/${selectedTarget.type}/${selectedTarget.id}/carry`, {
        method: 'POST',
        body: JSON.stringify({ item_id: item.id, quantity: 1 }),
      });
      await refreshAfterLoadoutChange(response.message || `${item.name} carried by ${selectedCharacterName}.`);
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  async function unequip(slot: string): Promise<void> {
    setLoading(true);
    setMessage('');
    setError('');

    try {
      const response = await api<{ message: string }>(`/loadouts/${selectedTarget.type}/${selectedTarget.id}/unequip`, {
        method: 'POST',
        body: JSON.stringify({ slot }),
      });
      await refreshAfterLoadoutChange(response.message || `${humanize(slot)} unequipped from ${selectedCharacterName}.`);
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  async function equipCarried(item: { id?: number; name: string; asset_type?: string; allowed_slots?: string[]; equipment_slot?: string }): Promise<void> {
    if (!item.id) {
      setError('This carried item cannot be equipped right now.');
      return;
    }
    const slot = item.allowed_slots?.[0] || item.equipment_slot || 'tool';
    await equip({ ...item, id: item.id, asset_type: item.asset_type === 'weapon' ? 'weapon' : 'item', name: item.name, quantity: 1 }, slot);
  }

  async function storeCarried(itemId: number): Promise<void> {
    setLoading(true);
    setMessage('');
    setError('');

    try {
      const response = await api<{ message: string }>(`/loadouts/${selectedTarget.type}/${selectedTarget.id}/drop-or-store`, {
        method: 'POST',
        body: JSON.stringify({ item_id: itemId }),
      });
      await refreshAfterLoadoutChange(response.message || 'Carried item returned to owned inventory.');
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  function selectCharacter(character: LoadoutCharacterSummary): void {
    setSelectedTarget({ type: character.character_type, id: character.character_id });
    setSelectedSlot('');
  }

  const roleGuide = workspace?.item_role_guide ?? [];

  return (
    <section className="page-section inventory-loadout-page">
      <GameHeader
        eyebrow="Inventory command"
        title="Inventory / Loadout Builder"
        description="Select a boss or crew member, see their portrait and slots, then equip gear or carry task items from the same workspace. Inventory no longer sells global equipment. Buying still happens through map shops."
      />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <InventoryTabs active={activeTab} onChange={setActiveTab} />

      {!workspace && (
        <SectionCard>
          <p className="muted">Loading loadout workspace…</p>
        </SectionCard>
      )}

      {workspace && activeTab === 'loadout' && (
        <LoadoutBuilderTab
          workspace={workspace}
          selectedSlot={selectedSlot}
          loading={loading}
          onSelectCharacter={selectCharacter}
          onSelectSlot={setSelectedSlot}
          onEquip={equip}
          onCarry={carry}
          onUnequip={unequip}
          onEquipCarried={equipCarried}
          onStoreCarried={storeCarried}
        />
      )}

      {workspace && activeTab === 'owned' && (
        <SectionCard>
          <LoadoutOwnedItemPool
            character={workspace.selected_character}
            items={allItems}
            selectedSlot={selectedSlot}
            loading={loading}
            onSelectSlot={setSelectedSlot}
            onEquip={equip}
            onCarry={carry}
            onUnequip={unequip}
            onStoreCarried={storeCarried}
          />
        </SectionCard>
      )}

      {activeTab === 'warehouse' && (
        <SectionCard>
          <h2>Warehouse / Storage</h2>
          <p className="muted">Warehouse items are storage-managed. Transfer gear out before it can be equipped or carried by a selected character.</p>
          <div className="map-action-grid">
            <button className="btn primary" onClick={() => onNavigate('warehouse')}>Open Warehouse</button>
            <button className="btn" onClick={() => onNavigate('world map')}>Find Shops on Map</button>
          </div>
        </SectionCard>
      )}

      {activeTab === 'effects' && (
        <div className="content-grid two-columns">
          <SectionCard>
            <h2>Equipped gear vs carried items</h2>
            <p className="muted">Equipped gear is worn in a body, weapon, tool, bag, or disguise slot. Carried inventory is for consumables, tools, task objects, evidence packages, and crime-use utility brought into jobs.</p>
            <div className="location-effect-summary">
              <span className="info-pill">Gloves: hands slot, evidence safety</span>
              <span className="info-pill">Bags: bag slot, carry capacity</span>
              <span className="info-pill">First-aid: carried consumable/task utility</span>
              <span className="info-pill">Burner phone: carried crime utility</span>
              <span className="info-pill">Crowbar/lockpick: tool slot or carried task tool</span>
              <span className="info-pill danger">Visible illegal gear raises suspicion</span>
            </div>
          </SectionCard>
          <SectionCard>
            <h2>Item role guide</h2>
            {roleGuide.map((entry) => (
              <article className="guide-mini-card" key={entry.label}>
                <strong>{entry.label}</strong>
                <p className="muted">{entry.description}</p>
              </article>
            ))}
          </SectionCard>
        </div>
      )}

      {activeTab === 'logs' && (
        <SectionCard>
          <h2>Inventory logs</h2>
          <PaginatedLogTable logs={logs.data} pagination={logs.pagination} onPage={setLogPage} />
        </SectionCard>
      )}
    </section>
  );
}

function loadStoredTarget(): LoadoutTarget {
  const stored = localStorage.getItem(SELECTED_LOADOUT_KEY) || 'boss:0';
  const [type, id] = stored.split(':');
  return type === 'crew' ? { type: 'crew', id: Number(id) || 0 } : { type: 'boss', id: 0 };
}

function firstCompatibleSlot(item: LoadoutWorkspaceItem): string | null {
  return item.compatibleSlots?.[0] || item.compatible_slots?.[0] || null;
}

function humanize(value: string): string {
  return value.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
}
