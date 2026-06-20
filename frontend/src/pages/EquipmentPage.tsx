import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { GameHeader } from '../components/game/GameHeader';
import { SectionCard } from '../components/game/SectionCard';
import { CharacterLoadoutPanel } from '../components/inventory/CharacterLoadoutPanel';
import { InventoryTabs, type InventoryTab } from '../components/inventory/InventoryTabs';
import { PaginatedLogTable } from '../components/logs/PaginatedLogTable';
import { getItemIcon } from '../data/assetManifest';
import type { CrewMember, InventoryAsset, InventoryResponse, LoadoutSummary, PageName } from '../types';

interface EquipmentPageProps {
  onChanged: () => void;
  onNavigate: (page: PageName) => void;
}

interface CrewLoadoutsResponse {
  data: Array<CrewMember & { loadout?: LoadoutSummary }>;
}

type LoadoutTarget =
  | { type: 'boss'; id: 0 }
  | { type: 'crew'; id: number };

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

// v0.7.2 UX cleanup: Owned item cards, selected crew action context, visible equipped/carry updates.
export function EquipmentPage({ onChanged, onNavigate }: EquipmentPageProps) {
  const [inventory, setInventory] = useState<InventoryResponse>({ items: [], weapons: [], drugs: [] });
  const [crew, setCrew] = useState<Array<CrewMember & { loadout?: LoadoutSummary }>>([]);
  const [bossLoadout, setBossLoadout] = useState<LoadoutSummary | null>(null);
  const [selectedTarget, setSelectedTarget] = useState<LoadoutTarget>({ type: 'boss', id: 0 });
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

    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  useEffect(() => {
    void load();
  }, [logPage]);

  const selectedMember = useMemo(
    () => selectedTarget.type === 'crew'
      ? crew.find((member) => member.id === selectedTarget.id) || null
      : null,
    [crew, selectedTarget],
  );

  const selectedLoadout = selectedTarget.type === 'boss'
    ? bossLoadout
    : selectedMember?.loadout ?? null;

  const selectedTargetName = selectedTarget.type === 'boss'
    ? 'Boss'
    : selectedMember
      ? displayName(selectedMember)
      : '';

  async function refreshAfterLoadoutChange(responseMessage: string): Promise<void> {
    setMessage(responseMessage);
    await load();
    setActiveTab(selectedTarget.type === 'boss' ? 'boss' : 'crew');
    onChanged();
  }

  async function equip(asset: InventoryAsset): Promise<void> {
    const assetType = getAssetType(asset);
    const recommendedSlot = getRecommendedSlot(asset, assetType);

    setLoading(true);
    setMessage('');
    setError('');

    try {
      const response = await api<{ message: string }>(`/loadouts/${selectedTarget.type}/${selectedTarget.id}/equip`, {
        method: 'POST',
        body: JSON.stringify({
          asset_type: assetType,
          item_id: asset.id,
          weapon_id: asset.id,
          slot: recommendedSlot,
        }),
      });

      await refreshAfterLoadoutChange(response.message || `${asset.name} equipped to ${selectedTargetName || 'target loadout'}.`);
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  async function carry(asset: InventoryAsset): Promise<void> {
    setLoading(true);
    setMessage('');
    setError('');
    try {
      const response = await api<{ message: string }>(`/loadouts/${selectedTarget.type}/${selectedTarget.id}/carry`, {
        method: 'POST',
        body: JSON.stringify({ item_id: asset.id, quantity: 1 }),
      });
      await refreshAfterLoadoutChange(response.message || `${asset.name} assigned to ${selectedTargetName || 'target loadout'}.`);
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

      await refreshAfterLoadoutChange(response.message || `${humanize(slot)} unequipped from ${selectedTargetName || 'target loadout'}.`);
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  const ownedAssets = useMemo(() => [...inventory.items, ...inventory.weapons], [inventory.items, inventory.weapons]);

  return (
    <section className="page-section inventory-loadout-page">
      <GameHeader
        eyebrow="Inventory command"
        title="Inventory / Loadouts"
        description="Manage owned gear, crew equipment slots, carried items, benefits, penalties, and storage. Buying still happens through map shops."
      />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <InventoryTabs active={activeTab} onChange={setActiveTab} />

      {activeTab === 'overview' && (
        <div className="content-grid two-columns">
          <SectionCard>
            <h2>Loadout overview</h2>
            <p className="muted">Characters use equipment slots and small carried inventories. Weight, bulk, visible illegal gear, concealment, and item benefits affect risk.</p>
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

      {activeTab === 'boss' && <CharacterLoadoutPanel title="Boss loadout" loadout={bossLoadout} loading={loading} onUnequip={unequip} />}

      {activeTab === 'crew' && (
        <SectionCard>
          <h2>Crew loadouts</h2>
          <CharacterTargetSelector
            crew={crew}
            selectedTarget={selectedTarget}
            onSelect={setSelectedTarget}
          />
          <CharacterLoadoutPanel
            title={selectedTarget.type === 'boss' ? 'Boss loadout' : selectedMember ? displayName(selectedMember) : 'Choose a crew member'}
            loadout={selectedLoadout}
            loading={loading}
            onUnequip={unequip}
          />
        </SectionCard>
      )}

      {activeTab === 'owned' && (
        <SectionCard>
          <div className="owned-items-header">
            <div>
              <h2>Owned gear</h2>
              <p className="muted">Choose the boss or a crew member, then equip gear into slots or assign useful items to carried inventory.</p>
            </div>
            <CharacterTargetSelector
              compact
              crew={crew}
              selectedTarget={selectedTarget}
              onSelect={setSelectedTarget}
            />
          </div>

          <div className="selected-crew-banner ready">
            <div>
              <p className="eyebrow">Current action target</p>
              <strong>{selectedTargetName}</strong>
              <p className="muted">
                {`Equip and carry buttons below will modify ${selectedTargetName}'s loadout.`}
              </p>
            </div>
            <button className="btn" onClick={() => setActiveTab(selectedTarget.type === 'boss' ? 'boss' : 'crew')}>
              {selectedTarget.type === 'boss' ? 'View boss loadout' : `View ${selectedMember?.first_name || 'crew'}'s loadout`}
            </button>
          </div>

          {ownedAssets.length === 0 ? (
            <p className="muted">No owned gear yet. Travel to map shops to buy starter equipment.</p>
          ) : (
            <div className="owned-gear-grid">
              {ownedAssets.map((asset) => (
                <OwnedGearCard
                  asset={asset}
                  key={`${getAssetType(asset)}-${asset.id}`}
                  selectedTargetName={selectedTargetName}
                  canUse={Number(asset.available_quantity ?? asset.quantity ?? 0) > 0}
                  loading={loading}
                  onEquip={() => equip(asset)}
                  onCarry={() => carry(asset)}
                />
              ))}
            </div>
          )}
        </SectionCard>
      )}

      {activeTab === 'warehouse' && (
        <SectionCard>
          <h2>Warehouse / Storage</h2>
          <p className="muted">Use the Warehouse page for capacity, contraband separation, vehicle parts, and transfers. Storage logs stay in the Warehouse Storage Logs subtab.</p>
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
        </section>
      )}
    </section>
  );
}

function CharacterTargetSelector({ crew, selectedTarget, onSelect, compact = false }: {
  crew: Array<CrewMember & { loadout?: LoadoutSummary }>;
  selectedTarget: LoadoutTarget;
  onSelect: (target: LoadoutTarget) => void;
  compact?: boolean;
}) {
  return (
    <div className={`crew-target-selector ${compact ? 'compact' : ''}`}>
      <div>
        <p className="eyebrow">Loadout target</p>
        <strong>{crew.length > 0 ? 'Select loadout owner' : 'Boss or crew loadout'}</strong>
      </div>
      <select
        value={selectedTarget.type === 'boss' ? 'boss:0' : `crew:${selectedTarget.id}`}
        onChange={(event) => {
          const [type, id] = event.target.value.split(':');
          if (type === 'boss') {
            onSelect({ type: 'boss', id: 0 });
            return;
          }
          onSelect({ type: 'crew', id: Number(id) });
        }}
      >
        <option value="boss:0">Boss</option>
        {crew.map((member) => <option value={`crew:${member.id}`} key={member.id}>{displayName(member)} — {member.status}</option>)}
      </select>
    </div>
  );
}

function OwnedGearCard({ asset, selectedTargetName, canUse, loading, onEquip, onCarry }: {
  asset: InventoryAsset;
  selectedTargetName: string;
  canUse: boolean;
  loading: boolean;
  onEquip: () => void;
  onCarry: () => void;
}) {
  const [imageSource, setImageSource] = useState(getItemIcon(asset.name, asset.category || asset.class));
  const assetType = getAssetType(asset);
  const available = Number(asset.available_quantity ?? asset.quantity ?? 0);
  const effects = normalizeEffects({ ...(normalizeEffects(asset.effects)), ...(normalizeEffects(asset.item_effects)) });
  const recommendedSlot = getRecommendedSlot(asset, assetType);
  const canCarry = assetType === 'item' && asset.is_carryable !== 0 && asset.is_carryable !== false;
  const canEquip = assetType === 'weapon' || asset.is_equippable !== 0 && asset.is_equippable !== false;
  const actionLabel = selectedTargetName || 'selected loadout';

  return (
    <article className="owned-gear-card">
      <div className="owned-gear-image-frame">
        <img src={imageSource} alt="" loading="lazy" onError={() => setImageSource('/assets/placeholders/default_item.webp')} />
      </div>
      <div className="owned-gear-body">
        <p className="eyebrow">{humanize(asset.category || asset.class || assetType)}</p>
        <h3>{asset.name}</h3>
        {asset.description && <p className="muted owned-gear-description">{asset.description}</p>}
        <div className="gear-chip-row">
          <span className="info-pill">Owned {asset.quantity ?? 0}</span>
          <span className={`info-pill ${available > 0 ? 'success' : 'danger'}`}>Available {available}</span>
          <span className="info-pill">{humanize(String(asset.size_class || 'small'))} · {Number(asset.carry_units ?? 1)} units</span>
          <span className={`info-pill ${isIllegal(asset) ? 'danger' : ''}`}>{humanize(String(asset.legality || (asset.illegal ? 'illegal' : 'legal')))}</span>
        </div>
        <div className="gear-effect-list">
          {Object.entries(effects).slice(0, 5).map(([name, value]) => (
            <span className="item-effect-badge" key={name}>{humanize(name)} {formatEffectValue(value)}</span>
          ))}
          {Object.keys(effects).length === 0 && <span className="muted">No operational modifiers.</span>}
        </div>
      </div>
      <div className="owned-gear-actions">
        <span className="gear-slot-hint">Recommended slot: <strong>{humanize(recommendedSlot)}</strong></span>
        <button className="btn primary full-width" disabled={loading || !canUse || !canEquip} onClick={onEquip}>
          {selectedTargetName ? `Equip to ${actionLabel}` : 'Select target to equip'}
        </button>
        {canCarry && (
          <button className="btn full-width" disabled={loading || !canUse} onClick={onCarry}>
            {selectedTargetName ? `Carry with ${actionLabel}` : 'Select target to carry'}
          </button>
        )}
      </div>
    </article>
  );
}

function getAssetType(asset: InventoryAsset): 'item' | 'weapon' {
  return asset.class ? 'weapon' : 'item';
}

function getRecommendedSlot(asset: InventoryAsset, assetType: 'item' | 'weapon'): string {
  if (assetType === 'weapon') {
    const weaponClass = String(asset.class || '').toLowerCase();
    if (weaponClass.includes('pistol') || weaponClass.includes('revolver')) return 'sidearm';
    if (weaponClass.includes('knife') || weaponClass.includes('baton') || weaponClass.includes('melee')) return 'melee';
    return 'primary_weapon';
  }
  const allowed = Array.isArray(asset.allowed_slots) ? asset.allowed_slots.filter(Boolean) : [];
  if (allowed.length > 0) return allowed[0];
  const legacySlot = String(asset.equipment_slot || '').trim();
  if (legacySlot === 'weapon') return 'primary_weapon';
  return legacySlot || 'tool';
}

function normalizeEffects(value: unknown): Record<string, number | string> {
  if (!value) return {};
  if (typeof value === 'string') {
    try {
      const decoded = JSON.parse(value);
      return normalizeEffects(decoded);
    } catch {
      return {};
    }
  }
  if (typeof value !== 'object' || Array.isArray(value)) return {};
  const entries = Object.entries(value as Record<string, unknown>)
    .filter(([key, entry]) => Number.isNaN(Number(key)) && (typeof entry === 'number' || typeof entry === 'string'));
  return Object.fromEntries(entries) as Record<string, number | string>;
}

function formatEffectValue(value: string | number): string {
  if (typeof value === 'number') return value > 0 ? `+${value}` : String(value);
  return String(value);
}

function isIllegal(asset: InventoryAsset): boolean {
  return asset.illegal === true || asset.illegal === 1 || String(asset.legality || '').includes('illegal');
}

function humanize(value: string): string {
  return value.split('_').map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(' ');
}

function displayName(member: CrewMember): string {
  const nickname = member.nickname ? ` “${member.nickname}”` : '';
  return `${member.first_name}${nickname} ${member.last_name}`;
}
