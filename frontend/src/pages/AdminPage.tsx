import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { GameHeader } from '../components/game/GameHeader';
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

export function AdminPage({ currentUser, onChanged }: AdminPageProps) {
  const [stats, setStats] = useState<Record<string, unknown> | null>(null);
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [targetUserId, setTargetUserId] = useState(String(currentUser.id));
  const [cashAmount, setCashAmount] = useState('0');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  async function load(): Promise<void> {
    try {
      const [dashboardResponse, auditResponse] = await Promise.all([
        api<{ stats: Record<string, unknown> }>('/admin/dashboard'),
        api<{ data: AuditLog[] }>('/admin/audit'),
      ]);
      setStats(dashboardResponse.stats);
      setLogs(auditResponse.data);
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  useEffect(() => {
    void load();
  }, []);

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
        </div>
      </header>

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <div className="content-grid two-columns">
        <section className="card">
          <h2>Player tools</h2>
          <label>
            User ID
            <input
              type="number"
              min="1"
              value={targetUserId}
              onChange={(event) => setTargetUserId(event.target.value)}
            />
          </label>
          <button className="btn" onClick={refillEnergy}>
            Refill energy
          </button>

          <label>
            Cash amount
            <input
              type="number"
              min="0"
              value={cashAmount}
              onChange={(event) => setCashAmount(event.target.value)}
            />
          </label>
          <button className="btn" onClick={setCash}>
            Set cash
          </button>
        </section>

        <section className="card">
          <h2>World summary</h2>
          <pre>{JSON.stringify(stats, null, 2)}</pre>
        </section>
      </div>

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
