import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { GameHeader } from '../components/game/GameHeader';
import { ItemIconCard } from '../components/game/ItemIconCard';
import { SectionCard } from '../components/game/SectionCard';
import type { CrewMember, InventoryAsset, InventoryResponse, PageName } from '../types';

interface EquipmentPageProps {
  onChanged: () => void;
  onNavigate: (page: PageName) => void;
}

export function EquipmentPage({ onChanged, onNavigate }: EquipmentPageProps) {
  const [inventory, setInventory] = useState<InventoryResponse>({
    items: [],
    weapons: [],
    drugs: [],
  });
  const [crew, setCrew] = useState<CrewMember[]>([]);
  const [selectedMemberId, setSelectedMemberId] = useState<number | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function load(): Promise<void> {
    try {
      const [inventoryResponse, crewResponse] = await Promise.all([
        api<InventoryResponse>('/inventory'),
        api<{ data: CrewMember[] }>('/my-gang'),
      ]);

      setInventory(inventoryResponse);
      setCrew(crewResponse.data);

      if (!selectedMemberId && crewResponse.data.length > 0) {
        setSelectedMemberId(crewResponse.data[0].id);
      }
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  useEffect(() => {
    void load();
  }, []);

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
      const response = await api<{ message: string }>(
        `/my-gang/${selectedMember.id}/equip`,
        {
          method: 'POST',
          body: JSON.stringify({
            asset_type: assetType,
            asset_id: assetId,
          }),
        },
      );

      setMessage(response.message);
      await load();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  async function unequip(equipmentId: number): Promise<void> {
    if (!selectedMember) {
      return;
    }

    setLoading(true);
    setMessage('');
    setError('');

    try {
      const response = await api<{ message: string }>(
        `/my-gang/${selectedMember.id}/equipment/${equipmentId}/unequip`,
        { method: 'POST' },
      );

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

  return (
    <section className="page-section">
      <GameHeader
        eyebrow="Storage locker"
        title="Inventory / Equipment"
        description="Manage owned items, crew loadouts, storage context, and equipment effects. Buying now happens through shops and dealers on the World Map."
      />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <SectionCard className="equipment-loadout-card">
        <div className="section-heading-row">
          <div>
            <h2>Crew loadout</h2>
            <p className="muted">One asset can only be equipped by one member. Buy missing gear from map shops.</p>
          </div>
          <select
            value={selectedMemberId || ''}
            onChange={(event) => setSelectedMemberId(Number(event.target.value))}
          >
            <option value="">Select crew member</option>
            {crew.map((member) => (
              <option value={member.id} key={member.id}>
                {displayName(member)} — {member.status}
              </option>
            ))}
          </select>
        </div>

        {selectedMember && (
          <div className="loadout-grid">
            {selectedMember.equipment.length === 0 && (
              <p className="muted">No items are equipped.</p>
            )}
            {selectedMember.equipment.map((equipment) => (
              <article className="loadout-slot" key={equipment.id}>
                <span>{equipment.equipment_slot}</span>
                <strong>{equipment.name}</strong>
                <small>Durability {equipment.durability}%</small>
                <button
                  className="btn"
                  disabled={loading}
                  onClick={() => unequip(equipment.id)}
                >
                  Unequip
                </button>
              </article>
            ))}
          </div>
        )}
      </SectionCard>

      <SectionCard>
        <div className="section-heading-row">
          <div>
            <h2>Need gear?</h2>
            <p className="muted">Inventory no longer sells global equipment. Travel to local shops, pawn fences, garages, medical counters, or future dealers.</p>
          </div>
          <div className="map-action-grid">
            <button className="btn primary" onClick={() => onNavigate('world map')}>Open World Map</button>
            <button className="btn" onClick={() => onNavigate('shops')}>Known shop shortcuts</button>
          </div>
        </div>
        <div className="location-effect-summary">
          <span className="info-pill">Tool shops: basic tools and bags</span>
          <span className="info-pill">Workwear: clothing and gloves</span>
          <span className="info-pill">Fences: sell small loot</span>
          <span className="info-pill">Powerful items: black-market/future only</span>
        </div>
      </SectionCard>

      <section className="page-subsection">
        <h2>Owned equipment</h2>
        {ownedAssets.length === 0 ? (
          <p className="muted">No owned equipment yet. Use Find Shops to travel to a local shop and buy starter gear.</p>
        ) : (
          <div className="inventory-icon-grid">
            {ownedAssets.map((asset) => {
              const assetType = asset.class ? 'weapon' : 'item';
              const available = asset.available_quantity ?? asset.quantity;

              return (
                <ItemIconCard
                  item={asset}
                  key={`${assetType}-${asset.id}`}
                  footer={(
                    <>
                      <EffectList effects={asset.effects || {}} />
                      <p className="muted">Owned {asset.quantity || 0} · available to equip: {available}</p>
                      <button
                        className="btn primary full-width"
                        disabled={loading || !selectedMember || available < 1}
                        onClick={() => equip(assetType, asset.id)}
                      >
                        Equip to selected member
                      </button>
                    </>
                  )}
                />
              );
            })}
          </div>
        )}
      </section>

      {inventory.drugs.length > 0 && (
        <section className="page-subsection">
          <h2>Carried contraband</h2>
          <p className="muted">Carried illegal goods can increase travel search risk. Use warehouse storage where available.</p>
          <div className="inventory-icon-grid">
            {inventory.drugs.map((drug) => (
              <ItemIconCard key={`drug-${drug.id}`} item={drug} footer={<p className="muted">Quantity {drug.quantity}</p>} />
            ))}
          </div>
        </section>
      )}
    </section>
  );
}

function EffectList({ effects }: { effects: Record<string, number> }) {
  const entries = Object.entries(effects);

  if (entries.length === 0) {
    return <p className="muted">No operational modifiers.</p>;
  }

  return (
    <ul className="effect-list">
      {entries.map(([name, value]) => (
        <li key={name}>
          {humanize(name)}: {value > 0 ? '+' : ''}{value}
        </li>
      ))}
    </ul>
  );
}

function humanize(value: string): string {
  return value
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

function displayName(member: CrewMember): string {
  const nickname = member.nickname ? ` “${member.nickname}”` : '';
  return `${member.first_name}${nickname} ${member.last_name}`;
}
