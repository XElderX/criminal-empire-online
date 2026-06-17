import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import type {
  InventoryAsset,
  InventoryResponse,
  PropertyListing,
  Warehouse,
  WarehouseOverview,
  WarehouseStorageRow,
  WarehouseUpgrade,
} from '../types';

interface WarehousePageProps {
  onChanged: () => void;
}

export function WarehousePage({ onChanged }: WarehousePageProps) {
  const [overview, setOverview] = useState<WarehouseOverview>({
    warehouses: [],
    listings: [],
    upgrade_catalog: [],
  });
  const [inventory, setInventory] = useState<InventoryResponse>({
    items: [],
    weapons: [],
    drugs: [],
  });
  const [selectedWarehouseId, setSelectedWarehouseId] = useState<number | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function load(): Promise<void> {
    try {
      const [warehouseResponse, inventoryResponse] = await Promise.all([
        api<WarehouseOverview>('/warehouses'),
        api<InventoryResponse>('/inventory'),
      ]);

      const normalizedOverview = normalizeOverview(warehouseResponse);
      const normalizedInventory = normalizeInventory(inventoryResponse);

      setOverview(normalizedOverview);
      setInventory(normalizedInventory);

      const warehouseExists = normalizedOverview.warehouses.some(
        (warehouse) => warehouse.id === selectedWarehouseId,
      );

      if (
        normalizedOverview.warehouses.length > 0
        && (!selectedWarehouseId || !warehouseExists)
      ) {
        setSelectedWarehouseId(normalizedOverview.warehouses[0].id);
      } else if (normalizedOverview.warehouses.length === 0) {
        setSelectedWarehouseId(null);
      }
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  const selectedWarehouse = useMemo(
    () =>
      overview.warehouses.find(
        (warehouse) => warehouse.id === selectedWarehouseId,
      ) || null,
    [overview.warehouses, selectedWarehouseId],
  );

  async function purchase(listing: PropertyListing): Promise<void> {
    const confirmed = window.confirm(
      `Purchase ${listing.name} for $${listing.purchase_price}?`,
    );

    if (!confirmed) {
      return;
    }

    await perform(async () => {
      const response = await api<{ message: string }>(
        `/warehouse-listings/${listing.id}/purchase`,
        { method: 'POST' },
      );

      return response.message;
    });
  }

  async function transfer(
    direction: 'deposit' | 'withdraw',
    assetType: 'item' | 'weapon' | 'drug',
    assetId: number,
    quantity: number,
  ): Promise<void> {
    if (!selectedWarehouse) {
      setError('Purchase or select a warehouse first.');
      return;
    }

    await perform(async () => {
      const response = await api<{ message: string }>(
        `/warehouses/${selectedWarehouse.id}/transfer`,
        {
          method: 'POST',
          body: JSON.stringify({
            direction,
            asset_type: assetType,
            asset_id: assetId,
            quantity,
          }),
        },
      );

      return response.message;
    });
  }

  async function installUpgrade(upgrade: WarehouseUpgrade): Promise<void> {
    if (!selectedWarehouse) {
      return;
    }

    const confirmed = window.confirm(
      `Install ${upgrade.name} for $${upgrade.price}?`,
    );

    if (!confirmed) {
      return;
    }

    await perform(async () => {
      const response = await api<{ message: string }>(
        `/warehouses/${selectedWarehouse.id}/upgrades/${upgrade.id}`,
        { method: 'POST' },
      );

      return response.message;
    });
  }

  async function perform(action: () => Promise<string>): Promise<void> {
    setLoading(true);
    setMessage('');
    setError('');

    try {
      setMessage(await action());
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
      <header className="page-header">
        <div>
          <p className="eyebrow">First player-owned building</p>
          <h1>Warehouse</h1>
          <p className="muted">
            Store equipment, weapons, drugs, stolen goods, vehicle parts, and
            full vehicles. Transfers are transactional and capacity-limited.
          </p>
        </div>
      </header>

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      {overview.warehouses.length === 0 && (
        <section className="page-subsection">
          <h2>NPC property listings</h2>
          <p className="muted">
            An entry-level warehouse is a medium-term objective. New players
            still begin with $500 and must build toward ownership.
          </p>
          <div className="card-grid">
            {overview.listings.map((listing) => (
              <ListingCard
                listing={listing}
                loading={loading}
                onPurchase={() => purchase(listing)}
                key={listing.id}
              />
            ))}
          </div>
        </section>
      )}

      {overview.warehouses.length > 0 && (
        <>
          <section className="card section-card">
            <div className="section-heading-row">
              <div>
                <h2>Your properties</h2>
                <p className="muted">Select a warehouse to manage its storage.</p>
              </div>
              <select
                value={selectedWarehouseId || ''}
                onChange={(event) => setSelectedWarehouseId(Number(event.target.value))}
              >
                {overview.warehouses.map((warehouse) => (
                  <option value={warehouse.id} key={warehouse.id}>
                    {warehouse.name} — {warehouse.territory_name}
                  </option>
                ))}
              </select>
            </div>
          </section>

          {selectedWarehouse && (
            <WarehouseWorkspace
              warehouse={selectedWarehouse}
              inventory={inventory}
              upgradeCatalog={overview.upgrade_catalog}
              loading={loading}
              onTransfer={transfer}
              onInstallUpgrade={installUpgrade}
            />
          )}

          {overview.listings.length > 0 && (
            <section className="page-subsection">
              <h2>Additional listings</h2>
              <div className="card-grid">
                {overview.listings.map((listing) => (
                  <ListingCard
                    listing={listing}
                    loading={loading}
                    onPurchase={() => purchase(listing)}
                    key={listing.id}
                  />
                ))}
              </div>
            </section>
          )}
        </>
      )}
    </section>
  );
}

function WarehouseWorkspace({
  warehouse,
  inventory,
  upgradeCatalog,
  loading,
  onTransfer,
  onInstallUpgrade,
}: {
  warehouse: Warehouse;
  inventory: InventoryResponse;
  upgradeCatalog: WarehouseUpgrade[];
  loading: boolean;
  onTransfer: (
    direction: 'deposit' | 'withdraw',
    assetType: 'item' | 'weapon' | 'drug',
    assetId: number,
    quantity: number,
  ) => void;
  onInstallUpgrade: (upgrade: WarehouseUpgrade) => void;
}) {
  const installedIds = new Set(warehouse.upgrades.map((upgrade) => upgrade.id));

  return (
    <>
      <section className="card warehouse-summary">
        <div className="card-heading">
          <div>
            <p className="eyebrow">{warehouse.territory_name}</p>
            <h2>{warehouse.name}</h2>
          </div>
          <span className={`status-badge status-${warehouse.status}`}>
            {warehouse.status}
          </span>
        </div>

        <div className="meter-grid">
          <CapacityMeter
            label="General storage"
            used={warehouse.used_storage_capacity}
            maximum={warehouse.storage_capacity}
          />
          <CapacityMeter
            label="Vehicle slots"
            used={warehouse.used_vehicle_slots}
            maximum={warehouse.vehicle_capacity}
          />
          <CapacityMeter
            label="Security"
            used={warehouse.security_rating}
            maximum={100}
          />
        </div>

        <dl className="details-grid">
          <div><dt>Weekly operating cost</dt><dd>${warehouse.weekly_operating_cost}</dd></div>
          <div><dt>Operating debt</dt><dd>${warehouse.operating_debt}</dd></div>
          <div><dt>Condition</dt><dd>{warehouse.condition_rating}/100</dd></div>
          <div><dt>Heat visibility</dt><dd>{warehouse.heat_visibility}/100</dd></div>
        </dl>
      </section>

      <section className="card section-card">
        <h2>Deposit personal inventory</h2>
        <p className="muted">
          Warehouse-stored gear cannot be used in crew loadouts until withdrawn.
        </p>
        <div className="storage-table">
          {(['items', 'weapons', 'drugs'] as const).flatMap((group) =>
            inventory[group].map((asset) => {
              const assetType = group === 'items'
                ? 'item'
                : group === 'weapons'
                  ? 'weapon'
                  : 'drug';
              const available = asset.available_quantity ?? asset.quantity;

              return (
                <StorageTransferRow
                  key={`${assetType}-${asset.id}`}
                  name={asset.name}
                  subtitle={`${humanize(assetType)} · available ${available}`}
                  disabled={loading || available < 1}
                  buttonLabel="Deposit one"
                  onClick={() => onTransfer('deposit', assetType, asset.id, 1)}
                />
              );
            }),
          )}
          {inventory.items.length + inventory.weapons.length + inventory.drugs.length === 0 && (
            <p className="muted">Personal inventory is empty.</p>
          )}
        </div>
      </section>

      <section className="card section-card">
        <h2>Stored assets</h2>
        <div className="storage-table">
          {warehouse.storage.map((row) => (
            <StoredAssetRow
              row={row}
              loading={loading}
              onWithdraw={() =>
                onTransfer('withdraw', row.asset_type, row.asset_id, 1)
              }
              key={row.id}
            />
          ))}
          {warehouse.storage.length === 0 && (
            <p className="muted">The warehouse is empty.</p>
          )}
        </div>
      </section>

      <section className="card section-card">
        <h2>Stored vehicles</h2>
        {warehouse.vehicles.length === 0 && (
          <p className="muted">
            No vehicles are stored. Vehicle Dirty Jobs can create persistent
            stolen vehicle records.
          </p>
        )}
        <div className="card-grid compact-grid">
          {warehouse.vehicles.map((vehicle) => (
            <article className="sub-card" key={vehicle.id}>
              <h3>{vehicle.name}</h3>
              <p>
                Condition {vehicle.condition_rating}/100 · evidence{' '}
                {vehicle.evidence_level}/100
              </p>
              <p className="muted">
                Estimated value ${vehicle.estimated_value} ·{' '}
                {vehicle.stolen ? 'stolen' : 'legal'}
              </p>
            </article>
          ))}
        </div>
      </section>

      <section className="card section-card">
        <h2>Security and capacity upgrades</h2>
        <div className="card-grid compact-grid">
          {upgradeCatalog.map((upgrade) => {
            const installed = installedIds.has(upgrade.id);

            return (
              <article className="sub-card" key={upgrade.id}>
                <h3>{upgrade.name}</h3>
                <p>{upgrade.description}</p>
                <p className="muted">${upgrade.price}</p>
                <button
                  className="btn"
                  disabled={loading || installed}
                  onClick={() => onInstallUpgrade(upgrade)}
                >
                  {installed ? 'Installed' : 'Install upgrade'}
                </button>
              </article>
            );
          })}
        </div>
      </section>

      <section className="card section-card">
        <h2>Recent storage log</h2>
        <div className="timeline compact-timeline">
          {warehouse.recent_logs.map((entry) => (
            <article key={entry.id}>
              <span>{new Date(entry.created_at).toLocaleString()}</span>
              <strong>{humanize(entry.direction)}</strong>
              <p>{entry.description}</p>
            </article>
          ))}
          {warehouse.recent_logs.length === 0 && (
            <p className="muted">No storage movements recorded.</p>
          )}
        </div>
      </section>
    </>
  );
}

function ListingCard({
  listing,
  loading,
  onPurchase,
}: {
  listing: PropertyListing;
  loading: boolean;
  onPurchase: () => void;
}) {
  return (
    <article className="card">
      <div className="card-heading">
        <div>
          <p className="eyebrow">{listing.territory_name}</p>
          <h2>{listing.name}</h2>
        </div>
        <strong>${listing.purchase_price.toLocaleString()}</strong>
      </div>
      <p>{listing.description}</p>
      <dl className="details-grid">
        <div><dt>Storage</dt><dd>{listing.storage_capacity} units</dd></div>
        <div><dt>Vehicles</dt><dd>{listing.vehicle_capacity} slots</dd></div>
        <div><dt>Security</dt><dd>{listing.security_rating}/100</dd></div>
        <div><dt>Weekly cost</dt><dd>${listing.weekly_operating_cost}</dd></div>
      </dl>
      <button
        className="btn primary full-width"
        disabled={loading || listing.can_purchase === false}
        onClick={onPurchase}
      >
        Purchase warehouse
      </button>
    </article>
  );
}

function CapacityMeter({
  label,
  used,
  maximum,
}: {
  label: string;
  used: number;
  maximum: number;
}) {
  const percentage = maximum > 0 ? Math.min(100, (used / maximum) * 100) : 0;

  return (
    <div className="meter">
      <div>
        <span>{label}</span>
        <strong>{Number(used).toFixed(1)} / {maximum}</strong>
      </div>
      <div className="progress-track">
        <span style={{ width: `${percentage}%` }} />
      </div>
    </div>
  );
}

function StorageTransferRow({
  name,
  subtitle,
  buttonLabel,
  disabled,
  onClick,
}: {
  name: string;
  subtitle: string;
  buttonLabel: string;
  disabled: boolean;
  onClick: () => void;
}) {
  return (
    <article className="list-row">
      <div>
        <strong>{name}</strong>
        <p className="muted">{subtitle}</p>
      </div>
      <button className="btn" disabled={disabled} onClick={onClick}>
        {buttonLabel}
      </button>
    </article>
  );
}

function StoredAssetRow({
  row,
  loading,
  onWithdraw,
}: {
  row: WarehouseStorageRow;
  loading: boolean;
  onWithdraw: () => void;
}) {
  const available = row.quantity - row.reserved_quantity;

  return (
    <StorageTransferRow
      name={row.name}
      subtitle={`${humanize(row.asset_type)} · stored ${row.quantity} · reserved ${row.reserved_quantity}`}
      buttonLabel="Withdraw one"
      disabled={loading || available < 1}
      onClick={onWithdraw}
    />
  );
}

function humanize(value: string): string {
  if (typeof value !== 'string' || value.trim() === '') {
    return 'Unknown';
  }

  return value
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

function normalizeOverview(overview: WarehouseOverview): WarehouseOverview {
  return {
    warehouses: Array.isArray(overview.warehouses)
      ? overview.warehouses.map(normalizeWarehouse)
      : [],
    listings: Array.isArray(overview.listings) ? overview.listings : [],
    upgrade_catalog: Array.isArray(overview.upgrade_catalog)
      ? overview.upgrade_catalog.map((upgrade) => ({
          ...upgrade,
          effects: upgrade.effects || {},
        }))
      : [],
  };
}

function normalizeInventory(inventory: InventoryResponse): InventoryResponse {
  return {
    items: Array.isArray(inventory.items) ? inventory.items : [],
    weapons: Array.isArray(inventory.weapons) ? inventory.weapons : [],
    drugs: Array.isArray(inventory.drugs) ? inventory.drugs : [],
  };
}

function normalizeWarehouse(warehouse: Warehouse): Warehouse {
  return {
    ...warehouse,
    storage: Array.isArray(warehouse.storage) ? warehouse.storage : [],
    vehicles: Array.isArray(warehouse.vehicles) ? warehouse.vehicles : [],
    upgrades: Array.isArray(warehouse.upgrades) ? warehouse.upgrades : [],
    recent_logs: Array.isArray(warehouse.recent_logs) ? warehouse.recent_logs : [],
  };
}
