import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { GameHeader } from '../components/game/GameHeader';
import { ItemIconCard } from '../components/game/ItemIconCard';
import { SectionCard } from '../components/game/SectionCard';
import type { CrewMember, InventoryAsset, InventoryResponse } from '../types';

interface EquipmentPageProps {
  onChanged: () => void;
}

interface ShopResponse {
  data: InventoryAsset[];
}

export function EquipmentPage({ onChanged }: EquipmentPageProps) {
  const [shop, setShop] = useState<InventoryAsset[]>([]);
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
      const [shopResponse, inventoryResponse, crewResponse] = await Promise.all([
        api<ShopResponse>('/items'),
        api<InventoryResponse>('/inventory'),
        api<{ data: CrewMember[] }>('/my-gang'),
      ]);

      setShop(shopResponse.data);
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

  async function buy(itemId: number): Promise<void> {
    setLoading(true);
    setMessage('');
    setError('');

    try {
      const response = await api<{ message: string }>(`/items/${itemId}/buy`, {
        method: 'POST',
        body: JSON.stringify({ quantity: 1 }),
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
        `/my-gang/${selectedMember.id}/equipment/${equipmentId}`,
        { method: 'DELETE' },
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

  return (
    <section className="page-section">
      <GameHeader
        eyebrow="Storage locker"
        title="Inventory / Equipment"
        description="Local item icons, crew loadouts, and category cards for tools, protection, vehicles, valuables, and contraband."
      />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <SectionCard className="equipment-loadout-card">
        <div className="section-heading-row">
          <div>
            <h2>Crew loadout</h2>
            <p className="muted">One asset can only be equipped by one member.</p>
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

      <section className="page-subsection">
        <h2>Owned equipment</h2>
        <div className="inventory-icon-grid">
          {[...inventory.items, ...inventory.weapons].map((asset) => {
            const assetType = asset.class ? 'weapon' : 'item';
            const available = asset.available_quantity ?? asset.quantity;

            return (
              <ItemIconCard
                item={asset}
                key={`${assetType}-${asset.id}`}
                footer={(
                  <>
                    <EffectList effects={asset.effects || {}} />
                    <p className="muted">Available to equip: {available}</p>
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
      </section>

      <section className="page-subsection">
        <h2>Equipment shop</h2>
        <div className="inventory-icon-grid">
          {shop.map((item) => (
            <ItemIconCard
              item={item}
              key={item.id}
              footer={(
                <>
                  <strong className="money-text">${item.price}</strong>
                  <EffectList effects={item.effects || {}} />
                  <p className="muted">
                    Owned {item.quantity || 0} · durability {item.base_durability || 100}%
                  </p>
                  <button
                    className="btn primary full-width"
                    disabled={loading || item.can_buy === false}
                    onClick={() => buy(item.id)}
                  >
                    Buy one
                  </button>
                </>
              )}
            />
          ))}
        </div>
      </section>
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
