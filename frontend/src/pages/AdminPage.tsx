import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { getItemIcon } from '../data/assetManifest';
import type { AdminNpcDetailResponse, AdminNpcListResponse, AdminNpcSummary, User } from '../types';

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
  inventory_quantity: number;
  inventory_owner_count: number;
  storage_quantity: number;
  storage_location_count: number;
  total_quantity: number;
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
    const itemAssets = assets.filter((asset) => asset.asset_type === 'item');
    const weaponAssets = assets.filter((asset) => asset.asset_type === 'weapon');
    const drugAssets = assets.filter((asset) => asset.asset_type === 'drug');

    return {
      item: itemAssets.length,
      weapon: weaponAssets.length,
      drug: drugAssets.length,
      itemQuantity: itemAssets.reduce((sum, asset) => sum + Number(asset.total_quantity || 0), 0),
      weaponQuantity: weaponAssets.reduce((sum, asset) => sum + Number(asset.total_quantity || 0), 0),
      drugQuantity: drugAssets.reduce((sum, asset) => sum + Number(asset.total_quantity || 0), 0),
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

      return `${response.message} ${response.asset_name} total in user inventory: ${response.new_quantity}.`;
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
            Review all obtainable and equipmentable assets with images, IDs, and total counts in the game, then grant inventory assets directly to any player.
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
              <span>Bank: ${selectedUser.bank_cash.toLocaleString()}</span>
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
            <span>Item defs: {assetCounts.item}</span>
            <span>Weapon defs: {assetCounts.weapon}</span>
            <span>Drug defs: {assetCounts.drug}</span>
          </div>
          <div className="admin-catalog-summary muted">
            <span>Total item qty in game: {assetCounts.itemQuantity}</span>
            <span>Total weapon qty in game: {assetCounts.weaponQuantity}</span>
            <span>Total drug qty in game: {assetCounts.drugQuantity}</span>
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
                    {user.role} · cash ${user.cash.toLocaleString()} · bank ${user.bank_cash.toLocaleString()} · energy {user.energy}/{user.max_energy}
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
            <p className="muted">Each row shows the image, internal id, type, and the current total count present across player inventories and warehouse storage.</p>
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
                <th>Inv qty</th>
                <th>Storage qty</th>
                <th>Total in game</th>
                <th>Owners</th>
                <th>Warehouses</th>
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
                  <td>{Number(asset.inventory_quantity || 0).toLocaleString()}</td>
                  <td>{Number(asset.storage_quantity || 0).toLocaleString()}</td>
                  <td><strong>{Number(asset.total_quantity || 0).toLocaleString()}</strong></td>
                  <td>{Number(asset.inventory_owner_count || 0).toLocaleString()}</td>
                  <td>{Number(asset.storage_location_count || 0).toLocaleString()}</td>
                  <td>${Number(asset.price || 0).toLocaleString()}</td>
                </tr>
              ))}
            </tbody>
          </table>

          {filteredAssets.length === 0 && <p className="muted">No assets matched the current filter.</p>}
        </div>
      </section>

      <AdminNpcBrowser />

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


function AdminNpcBrowser() {
  const [npcs, setNpcs] = useState<AdminNpcSummary[]>([]);
  const [total, setTotal] = useState(0);
  const [statuses, setStatuses] = useState<string[]>([]);
  const [roles, setRoles] = useState<string[]>([]);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [alive, setAlive] = useState('');
  const [role, setRole] = useState('');
  const [flag, setFlag] = useState('');
  const [sort, setSort] = useState('last_seen');
  const [detail, setDetail] = useState<AdminNpcDetailResponse['npc'] | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    void loadNpcs();
  }, [search, status, alive, role, flag, sort]);

  async function loadNpcs(): Promise<void> {
    setLoading(true);
    setError('');

    try {
      const params = new URLSearchParams();
      if (search.trim() !== '') params.set('search', search.trim());
      if (status !== '') params.set('status', status);
      if (alive !== '') params.set('alive', alive);
      if (role !== '') params.set('role', role);
      if (flag !== '') params.set('flag', flag);
      if (sort !== '') params.set('sort', sort);

      const response = await api<AdminNpcListResponse>(`/admin/npcs?${params.toString()}`);
      setNpcs(response.data);
      setTotal(response.pagination.total);
      setStatuses(response.filters.statuses);
      setRoles(response.filters.roles);
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  async function openDetail(npcId: number): Promise<void> {
    setError('');

    try {
      const response = await api<AdminNpcDetailResponse>(`/admin/npcs/${npcId}`);
      setDetail(response.npc);
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  return (
    <section className="card section-card admin-npc-browser">
      <div className="card-heading">
        <div>
          <p className="eyebrow">v0.4 NPC world</p>
          <h2>Admin NPC browser</h2>
          <p className="muted">
            Inspect persistent contacts, witnesses, rivals, police-linked NPCs, and dead historical records. Dead NPCs stay visible but cannot act.
          </p>
        </div>
        <span className="version-badge">{loading ? 'loading' : `${total} NPCs`}</span>
      </div>

      {error && <Notice message={error} kind="error" />}

      <div className="admin-filter-row npc-filter-row">
        <label>
          Search
          <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Name, nickname, notes, district" />
        </label>
        <label>
          Status
          <select value={status} onChange={(event) => setStatus(event.target.value)}>
            <option value="">All</option>
            {statuses.map((entry) => <option key={entry} value={entry}>{entry}</option>)}
          </select>
        </label>
        <label>
          Alive
          <select value={alive} onChange={(event) => setAlive(event.target.value)}>
            <option value="">All</option>
            <option value="1">Alive</option>
            <option value="0">Dead</option>
          </select>
        </label>
        <label>
          Role
          <select value={role} onChange={(event) => setRole(event.target.value)}>
            <option value="">All</option>
            {roles.map((entry) => <option key={entry} value={entry}>{entry}</option>)}
          </select>
        </label>
        <label>
          Flag
          <select value={flag} onChange={(event) => setFlag(event.target.value)}>
            <option value="">All</option>
            <option value="contact">Contact</option>
            <option value="informant">Informant</option>
            <option value="witness">Witness</option>
            <option value="rival">Rival</option>
            <option value="police">Police</option>
            <option value="recruitable">Recruitable</option>
          </select>
        </label>
        <label>
          Sort
          <select value={sort} onChange={(event) => setSort(event.target.value)}>
            <option value="last_seen">Last seen</option>
            <option value="name">Name</option>
            <option value="age">Age</option>
            <option value="status">Status</option>
            <option value="district">District</option>
            <option value="money">Money</option>
            <option value="reputation">Reputation</option>
            <option value="created">Created</option>
          </select>
        </label>
      </div>

      {npcs.length === 0 && !loading && <p className="muted">No NPCs match the current filters.</p>}

      <div className="admin-npc-grid">
        {npcs.map((npc) => (
          <article key={npc.id} className={`admin-npc-card ${npc.is_dead ? 'dead' : ''}`}>
            <div className="admin-npc-portrait-wrap">
              <img src={npc.portrait.thumbnail_url} alt="" />
              {npc.is_dead && <span className="dead-watermark">DEAD</span>}
            </div>
            <div className="admin-npc-body">
              <div className="card-heading small-heading">
                <div>
                  <h3>{npc.display_name}</h3>
                  <p className="muted">{npc.full_name} · age {npc.age} · {npc.life_stage.label}</p>
                </div>
                <span className={`status-badge ${npc.is_dead ? 'danger' : ''}`}>{npc.status}</span>
              </div>
              <p className="muted">{npc.role} · {npc.affiliation || 'no affiliation'} · {npc.territory_name || 'unknown district'}</p>
              <p>{npc.current_activity || npc.notes || 'No current activity recorded.'}</p>
              {npc.is_dead && (
                <p className="dead-note">Dead: {npc.death_game_date || 'date unknown'} · {npc.death_category || 'unknown cause'}</p>
              )}
              <button className="btn" onClick={() => openDetail(npc.id)}>View details</button>
            </div>
          </article>
        ))}
      </div>

      {detail && (
        <section className={`admin-npc-detail ${detail.is_dead ? 'dead' : ''}`}>
          <div className="card-heading">
            <div>
              <p className="eyebrow">NPC detail</p>
              <h2>{detail.display_name}</h2>
              <p className="muted">{detail.role} · {detail.affiliation || 'unknown'} · {detail.territory_name || 'unknown district'}</p>
            </div>
            <button className="btn" onClick={() => setDetail(null)}>Close</button>
          </div>

          <div className="content-grid two-columns">
            <div className="admin-npc-detail-card">
              <img src={detail.portrait.url} alt="" />
              {detail.is_dead && <span className="dead-watermark detail-watermark">DEAD</span>}
              <p>{detail.biography || detail.notes || 'No biography recorded.'}</p>
              {detail.is_dead && <p className="dead-note">{detail.death_notes || 'Dead NPC retained for historical inspection.'}</p>}
            </div>

            <div>
              <h3>Stats and flags</h3>
              <dl className="details-grid">
                {Object.entries(detail.stats).map(([key, value]) => (
                  <div key={key}><dt>{key.replace('_', ' ')}</dt><dd>{value}</dd></div>
                ))}
              </dl>
              <p className="muted">Flags: {Object.entries(detail.flags).filter(([, value]) => value).map(([key]) => key).join(', ') || 'none'}</p>
            </div>
          </div>

          <div className="content-grid two-columns">
            <section>
              <h3>Timeline</h3>
              <div className="timeline compact-timeline">
                {detail.timeline.map((entry, index) => (
                  <article key={index}>
                    <span>{String(entry.event_type || 'event')}</span>
                    <strong>{String(entry.title || 'Timeline entry')}</strong>
                    <p className="muted">{String(entry.description || '')}</p>
                  </article>
                ))}
              </div>
            </section>

            <section>
              <h3>Relationships</h3>
              <div className="timeline compact-timeline">
                {detail.relationships.map((entry, index) => (
                  <article key={index}>
                    <span>{String(entry.relationship_type || 'relationship')}</span>
                    <strong>Trust {String(entry.trust ?? 0)} · suspicion {String(entry.suspicion ?? 0)}</strong>
                    <p className="muted">{String(entry.notes || '')}</p>
                  </article>
                ))}
              </div>
            </section>
          </div>
        </section>
      )}
    </section>
  );
}
