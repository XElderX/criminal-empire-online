import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { getItemIcon } from '../data/assetManifest';
import type { User } from '../types';

interface AdminPageProps {
  currentUser: User;
  onChanged: () => void;
}

interface AuditLog {
  id: number;
  action: string;
  created_at: string;
}

interface AdminUserSummary {
  id: number;
  username: string;
  role: string;
  cash: number;
  bank_cash: number;
  energy: number;
  max_energy: number;
}

type AdminAssetType = 'item' | 'weapon' | 'drug';
type AdminAssetFilter = AdminAssetType | 'all';

interface AdminCatalogAsset {
  id: number;
  code: string | null;
  name: string;
  category: string | null;
  equipment_slot: string | null;
  price: number;
  illegal: number;
  active: number;
  asset_type: AdminAssetType;
  equipmentable: number;
}

interface AdminCatalogResponse {
  users: AdminUserSummary[];
  assets: AdminCatalogAsset[];
}

export function AdminPage({ currentUser, onChanged }: AdminPageProps) {
  const [stats, setStats] = useState<Record<string, unknown> | null>(null);
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [users, setUsers] = useState<AdminUserSummary[]>([]);
  const [assets, setAssets] = useState<AdminCatalogAsset[]>([]);
  const [targetUserId, setTargetUserId] = useState(String(currentUser.id));
  const [cashAmount, setCashAmount] = useState('0');
  const [grantType, setGrantType] = useState<AdminAssetType>('item');
  const [grantAssetId, setGrantAssetId] = useState('');
  const [grantQuantity, setGrantQuantity] = useState('1');
  const [catalogTypeFilter, setCatalogTypeFilter] = useState<AdminAssetFilter>('all');
  const [catalogSearch, setCatalogSearch] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  async function load(): Promise<void> {
    try {
      const [dashboardResponse, auditResponse, catalogResponse] = await Promise.all([
        api<{ stats: Record<string, unknown> }>('/admin/dashboard'),
        api<{ data: AuditLog[] }>('/admin/audit'),
        api<AdminCatalogResponse>('/admin/item-catalog'),
      ]);

      setStats(dashboardResponse.stats);
      setLogs(auditResponse.data);
      setUsers(catalogResponse.users);
      setAssets(catalogResponse.assets);
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  const grantOptions = useMemo(
    () => assets.filter((asset) => asset.asset_type === grantType),
    [assets, grantType],
  );

  useEffect(() => {
    if (grantOptions.length === 0) {
      setGrantAssetId('');
      return;
    }

    const hasSelected = grantOptions.some((asset) => String(asset.id) === grantAssetId);

    if (!hasSelected) {
      setGrantAssetId(String(grantOptions[0].id));
    }
  }, [grantAssetId, grantOptions]);

  const filteredAssets = useMemo(() => {
    const search = catalogSearch.trim().toLowerCase();

    return assets.filter((asset) => {
      if (catalogTypeFilter !== 'all' && asset.asset_type !== catalogTypeFilter) {
        return false;
      }

      if (search === '') {
        return true;
      }

      const haystack = [
        asset.name,
        asset.code || '',
        asset.category || '',
        asset.equipment_slot || '',
        asset.asset_type,
        String(asset.id),
      ].join(' ').toLowerCase();

      return haystack.includes(search);
    });
  }, [assets, catalogSearch, catalogTypeFilter]);

  const assetCounts = useMemo(() => {
    return {
      item: assets.filter((asset) => asset.asset_type === 'item').length,
      weapon: assets.filter((asset) => asset.asset_type === 'weapon').length,
      drug: assets.filter((asset) => asset.asset_type === 'drug').length,
    };
  }, [assets]);

  const selectedUser = users.find((user) => String(user.id) === targetUserId) || null;

  async function refillEnergy(): Promise<void> {
    await runAdminAction(async () => {
      const response = await api<{ message: string }>(
        `/admin/users/${targetUserId}/energy/refill`,
        { method: 'POST' },
      );
      return response.message;
    });
  }

  async function setCash(): Promise<void> {
    await runAdminAction(async () => {
      const response = await api<{ message: string }>(
        `/admin/users/${targetUserId}/cash/set`,
        {
          method: 'POST',
          body: JSON.stringify({ amount: Number(cashAmount) }),
        },
      );
      return response.message;
    });
  }

  async function grantAsset(): Promise<void> {
    await runAdminAction(async () => {
      const response = await api<{ message: string; asset_name: string; new_quantity: number }>(
        `/admin/users/${targetUserId}/grant-asset`,
        {
          method: 'POST',
          body: JSON.stringify({
            asset_type: grantType,
            asset_id: Number(grantAssetId),
            quantity: Number(grantQuantity),
          }),
        },
      );

      return `${response.message} ${response.asset_name} total: ${response.new_quantity}.`;
    });
  }

  async function runAdminAction(action: () => Promise<string>): Promise<void> {
    setMessage('');
    setError('');

    try {
      setMessage(await action());
      await load();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  return (
    <section className="page-section">
      <header className="page-header">
        <div>
          <p className="eyebrow">Development administration</p>
          <h1>Admin</h1>
          <p className="muted">
            Review every obtainable or equipmentable asset, copy ids quickly, and grant items directly to player inventories.
          </p>
        </div>
      </header>

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <div className="content-grid two-columns admin-tools-grid">
        <section className="card section-card">
          <h2>Player tools</h2>
          <div className="admin-form-grid">
            <label>
              User
              <select value={targetUserId} onChange={(event) => setTargetUserId(event.target.value)}>
                {users.map((user) => (
                  <option key={user.id} value={user.id}>
                    #{user.id} · {user.username}
                  </option>
                ))}
              </select>
            </label>

            <label>
              Cash amount
              <input
                type="number"
                min="0"
                value={cashAmount}
                onChange={(event) => setCashAmount(event.target.value)}
              />
            </label>
          </div>

          {selectedUser && (
            <div className="admin-user-summary muted">
              <strong>
                #{selectedUser.id} · {selectedUser.username}
              </strong>
              <span>Role: {selectedUser.role}</span>
              <span>Cash: ${selectedUser.cash.toLocaleString()}</span>
              <span>
                Energy: {selectedUser.energy}/{selectedUser.max_energy}
              </span>
            </div>
          )}

          <div className="admin-action-row">
            <button className="btn" onClick={refillEnergy}>
              Refill energy
            </button>
            <button className="btn" onClick={setCash}>
              Set cash
            </button>
          </div>
        </section>

        <section className="card section-card">
          <h2>Grant inventory asset</h2>
          <div className="admin-form-grid">
            <label>
              Asset type
              <select value={grantType} onChange={(event) => setGrantType(event.target.value as AdminAssetType)}>
                <option value="item">Items</option>
                <option value="weapon">Weapons</option>
                <option value="drug">Drugs</option>
              </select>
            </label>

            <label>
              Quantity
              <input
                type="number"
                min="1"
                max="10000"
                value={grantQuantity}
                onChange={(event) => setGrantQuantity(event.target.value)}
              />
            </label>

            <label className="admin-full-width">
              Asset
              <select value={grantAssetId} onChange={(event) => setGrantAssetId(event.target.value)}>
                {grantOptions.map((asset) => (
                  <option key={`${asset.asset_type}-${asset.id}`} value={asset.id}>
                    [{asset.asset_type}] #{asset.id} · {asset.name}
                  </option>
                ))}
              </select>
            </label>
          </div>

          <button className="btn primary" onClick={grantAsset} disabled={!grantAssetId}>
            Add to inventory
          </button>

          <div className="admin-catalog-summary muted">
            <span>Items: {assetCounts.item}</span>
            <span>Weapons: {assetCounts.weapon}</span>
            <span>Drugs: {assetCounts.drug}</span>
          </div>
        </section>
      </div>

      <div className="content-grid two-columns">
        <section className="card section-card">
          <h2>World summary</h2>
          <pre>{JSON.stringify(stats, null, 2)}</pre>
        </section>

        <section className="card section-card">
          <h2>Users</h2>
          <div className="admin-user-list">
            {users.map((user) => (
              <article key={user.id} className="list-row compact-list-row">
                <div>
                  <strong>
                    #{user.id} · {user.username}
                  </strong>
                  <p className="muted">
                    {user.role} · cash ${user.cash.toLocaleString()} · energy {user.energy}/{user.max_energy}
                  </p>
                </div>
              </article>
            ))}
          </div>
        </section>
      </div>

      <section className="card section-card">
        <div className="card-heading">
          <div>
            <p className="eyebrow">Reference list</p>
            <h2>Obtainable and equipmentable assets</h2>
          </div>
        </div>

        <div className="admin-filter-row">
          <label>
            Type filter
            <select
              value={catalogTypeFilter}
              onChange={(event) => setCatalogTypeFilter(event.target.value as AdminAssetFilter)}
            >
              <option value="all">All</option>
              <option value="item">Items</option>
              <option value="weapon">Weapons</option>
              <option value="drug">Drugs</option>
            </select>
          </label>

          <label>
            Search
            <input
              type="text"
              value={catalogSearch}
              onChange={(event) => setCatalogSearch(event.target.value)}
              placeholder="Search by id, name, code, category, or slot"
            />
          </label>
        </div>

        <div className="admin-asset-table-wrap">
          <table className="admin-asset-table">
            <thead>
              <tr>
                <th>Asset</th>
                <th>ID</th>
                <th>Type</th>
                <th>Category</th>
                <th>Slot</th>
                <th>Price</th>
              </tr>
            </thead>
            <tbody>
              {filteredAssets.map((asset) => (
                <tr key={`${asset.asset_type}-${asset.id}`}>
                  <td>
                    <div className="admin-asset-name">
                      <img src={getItemIcon(asset.name, asset.category || asset.asset_type)} alt="" />
                      <div>
                        <strong>{asset.name}</strong>
                        <small>
                          {asset.code ? asset.code : asset.asset_type === 'weapon' ? 'weapon' : 'inventory asset'}
                        </small>
                      </div>
                    </div>
                  </td>
                  <td>#{asset.id}</td>
                  <td>{asset.asset_type}</td>
                  <td>{asset.category || '—'}</td>
                  <td>{asset.equipment_slot || '—'}</td>
                  <td>${Number(asset.price || 0).toLocaleString()}</td>
                </tr>
              ))}
            </tbody>
          </table>

          {filteredAssets.length === 0 && <p className="muted">No assets matched the current filter.</p>}
        </div>
      </section>

      <section className="card section-card">
        <h2>Audit log</h2>
        <div className="timeline compact-timeline">
          {logs.map((log) => (
            <article key={log.id}>
              <span>{new Date(log.created_at).toLocaleString()}</span>
              <strong>{log.action}</strong>
            </article>
          ))}
        </div>
      </section>
    </section>
  );
}
