import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { GameHeader } from '../components/game/GameHeader';
import { ItemIconCard } from '../components/game/ItemIconCard';
import { SectionCard } from '../components/game/SectionCard';
import { CharacterLoadoutPanel } from '../components/inventory/CharacterLoadoutPanel';
import { InventoryTabs, type InventoryTab } from '../components/inventory/InventoryTabs';
import { OwnedItemTable } from '../components/inventory/OwnedItemTable';
import { PaginatedLogTable } from '../components/logs/PaginatedLogTable';
import type { CrewMember, InventoryAsset, InventoryResponse, LoadoutSummary, PageName } from '../types';

interface EquipmentPageProps {
  onChanged: () => void;
  onNavigate: (page: PageName) => void;
}

interface CrewLoadoutsResponse {
  data: Array<CrewMember & { loadout?: LoadoutSummary }>;
}

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

// v0.7 tabs: Boss Loadout Crew Loadouts Owned Items Warehouse / Storage Item Effects Transactions / Logs CharacterLoadoutPanel LoadoutSliders
export function EquipmentPage({ onChanged, onNavigate }: EquipmentPageProps) {
  const [inventory, setInventory] = useState<InventoryResponse>({ items: [], weapons: [], drugs: [] });
  const [crew, setCrew] = useState<Array<CrewMember & { loadout?: LoadoutSummary }>>([]);
  const [bossLoadout, setBossLoadout] = useState<LoadoutSummary | null>(null);
  const [selectedMemberId, setSelectedMemberId] = useState<number | null>(null);
  const [activeTab, setActiveTab] = useState<InventoryTab>('overview');
  const [logs, setLogs] = useState<LogResponse>({ data: [] });
  const [logPage, setLogPage] = useState(1);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function load(): Promise<void> {
    try {
      const [inventoryResponse, crewResponse, bossResponse, logsResponse] = await Promise.all([
        api<InventoryResponse>('/inventory'),
        api<CrewLoadoutsResponse>('/loadouts/crew'),
        api<LoadoutSummary>('/loadouts/boss'),
        api<LogResponse>(`/inventory/logs?page=${logPage}&limit=30`),
      ]);

      setInventory(inventoryResponse);
      setCrew(crewResponse.data);
      setBossLoadout(bossResponse);
      setLogs(logsResponse);

      if (!selectedMemberId && crewResponse.data.length > 0) {
        setSelectedMemberId(crewResponse.data[0].id);
      }
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  useEffect(() => {
    void load();
  }, [logPage]);

  const selectedMember = useMemo(
    () => crew.find((member) => member.id === selectedMemberId) || null,
    [crew, selectedMemberId],
  );

  async function equip(assetType: 'item' | 'weapon', assetId: number): Promise<void> {
    if (!selectedMember) {
      setError('Select a crew member first.');
      return;
    }

    setLoading(true);
    setMessage('');
    setError('');

    try {
      const item = inventory.items.find((entry) => entry.id === assetId);
      const slot = item?.allowed_slots?.[0] || item?.equipment_slot || 'tool';
      const response = assetType === 'item'
        ? await api<{ message: string }>(`/loadouts/crew/${selectedMember.id}/equip`, {
          method: 'POST',
          body: JSON.stringify({ item_id: assetId, slot }),
        })
        : await api<{ message: string }>(`/my-gang/${selectedMember.id}/equip`, {
          method: 'POST',
          body: JSON.stringify({ asset_type: assetType, asset_id: assetId }),
        });

      setMessage(response.message);
      await load();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  async function carry(assetId: number): Promise<void> {
    if (!selectedMember) {
      setError('Select a crew member first.');
      return;
    }
    setLoading(true);
    setMessage('');
    setError('');
    try {
      const response = await api<{ message: string }>(`/loadouts/crew/${selectedMember.id}/carry`, {
        method: 'POST',
        body: JSON.stringify({ item_id: assetId, quantity: 1 }),
      });
      setMessage(response.message);
      await load();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  const ownedAssets = [...inventory.items, ...inventory.weapons];
  const selectedLoadout = selectedMember?.loadout ?? null;

  return (
    <section className="page-section inventory-loadout-page">
      <GameHeader
        eyebrow="Inventory command"
        title="Inventory / Loadouts"
        description="Manage owned items, character equipment slots, carried inventory, item effects, and storage. Buying still happens through map shops."
      />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <InventoryTabs active={activeTab} onChange={setActiveTab} />

      {activeTab === 'overview' && (
        <div className="content-grid two-columns">
          <SectionCard>
            <h2>Loadout overview</h2>
            <p className="muted">Characters now use equipment slots and small carried inventories. Weight, bulk, visible illegal gear, concealment, and item benefits affect risk.</p>
            <div className="stat-grid compact-stat-grid">
              <span className="info-pill">Owned items: {inventory.loadout_summary?.total_owned_items ?? ownedAssets.length}</span>
              <span className="info-pill">Equipped: {inventory.loadout_summary?.equipped_items ?? 0}</span>
              <span className="info-pill">Illegal carried: {inventory.loadout_summary?.illegal_carried_items?.length ?? 0}</span>
              <span className="info-pill">Slots: {(inventory.equipment_slots ?? []).length || 14}</span>
            </div>
            {(inventory.loadout_summary?.warnings ?? []).map((warning) => <p className="muted" key={warning}>{warning}</p>)}
          </SectionCard>

          <SectionCard>
            <h2>Find gear</h2>
            <p className="muted">Inventory no longer sells global equipment. Travel to map shops, fences, garages, or black-market contacts.</p>
            <div className="map-action-grid">
              <button className="btn primary" onClick={() => onNavigate('world map')}>Find Shops on Map</button>
              <button className="btn" onClick={() => onNavigate('shops')}>Known shop shortcuts</button>
            </div>
          </SectionCard>
        </div>
      )}

      {activeTab === 'boss' && <CharacterLoadoutPanel title="Boss loadout" loadout={bossLoadout} />}

      {activeTab === 'crew' && (
        <SectionCard>
          <div className="section-heading-row">
            <div>
              <h2>Crew loadouts</h2>
              <p className="muted">Select a real crew member and manage body slots, tools, utility, bags, and carried items.</p>
            </div>
            <select value={selectedMemberId || ''} onChange={(event) => setSelectedMemberId(Number(event.target.value))}>
              <option value="">Select crew member</option>
              {crew.map((member) => <option value={member.id} key={member.id}>{displayName(member)} — {member.status}</option>)}
            </select>
          </div>
          <CharacterLoadoutPanel title={selectedMember ? displayName(selectedMember) : 'Select crew'} loadout={selectedLoadout} />
        </SectionCard>
      )}

      {activeTab === 'owned' && (
        <SectionCard>
          <h2>Owned items</h2>
          <OwnedItemTable items={ownedAssets} />
          <div className="inventory-icon-grid compact-owned-actions">
            {ownedAssets.map((asset) => {
              const assetType = asset.class ? 'weapon' : 'item';
              const available = asset.available_quantity ?? asset.quantity;
              return (
                <ItemIconCard
                  item={asset}
                  key={`${assetType}-${asset.id}`}
                  footer={(
                    <>
                      <EffectList effects={{ ...(asset.effects || {}), ...(asset.item_effects || {}) }} />
                      <p className="muted">Owned {asset.quantity || 0} · available: {available}</p>
                      <button className="btn primary full-width" disabled={loading || !selectedMember || available < 1} onClick={() => equip(assetType, asset.id)}>Equip to selected member</button>
                      {assetType === 'item' && <button className="btn full-width" disabled={loading || !selectedMember || available < 1} onClick={() => carry(asset.id)}>Carry with selected member</button>}
                    </>
                  )}
                />
              );
            })}
          </div>
        </SectionCard>
      )}

      {activeTab === 'warehouse' && (
        <SectionCard>
          <h2>Warehouse / Storage</h2>
          <p className="muted">Use the Warehouse page for capacity, contraband separation, vehicle parts, and transfers. Loadouts pull from owned inventory unless future storage-to-loadout transfer is enabled.</p>
          <button className="btn primary" onClick={() => onNavigate('warehouse')}>Open Warehouse</button>
        </SectionCard>
      )}

      {activeTab === 'effects' && (
        <SectionCard>
          <h2>Item effects</h2>
          <div className="location-effect-summary">
            <span className="info-pill">Gloves reduce evidence risk.</span>
            <span className="info-pill">Masks reduce witness ID but can raise suspicion.</span>
            <span className="info-pill">Crowbars help forced entry but are noisy.</span>
            <span className="info-pill">Vests reduce injury but hurt stealth/mobility.</span>
            <span className="info-pill">Bags increase carry capacity but add bulk.</span>
            <span className="info-pill">Illegal visible gear raises police suspicion.</span>
          </div>
        </SectionCard>
      )}

      {activeTab === 'logs' && (
        <SectionCard>
          <h2>Inventory logs</h2>
          <PaginatedLogTable logs={logs.data} pagination={logs.pagination} onPage={setLogPage} />
        </SectionCard>
      )}

      {inventory.drugs.length > 0 && activeTab === 'overview' && (
        <section className="page-subsection">
          <h2>Carried contraband warning</h2>
          <p className="muted">Carried illegal goods can increase travel search risk. Use warehouse storage where available.</p>
          <div className="inventory-icon-grid">
            {inventory.drugs.map((drug) => <ItemIconCard key={`drug-${drug.id}`} item={drug} footer={<p className="muted">Quantity {drug.quantity}</p>} />)}
          </div>
        </section>
      )}
    </section>
  );
}

function EffectList({ effects }: { effects: Record<string, number> }) {
  const entries = Object.entries(effects);
  if (entries.length === 0) return <p className="muted">No operational modifiers.</p>;
  return <ul className="effect-list">{entries.slice(0, 4).map(([name, value]) => <li key={name}>{humanize(name)}: {value > 0 ? '+' : ''}{value}</li>)}</ul>;
}

function humanize(value: string): string {
  return value.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
}

function displayName(member: CrewMember): string {
  const nickname = member.nickname ? ` “${member.nickname}”` : '';
  return `${member.first_name}${nickname} ${member.last_name}`;
}
