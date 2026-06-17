import { useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import type { User } from '../types';

interface DashboardPageProps {
  user: User;
  onChanged: () => void;
}

export function DashboardPage({ user, onChanged }: DashboardPageProps) {
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  async function layLow(): Promise<void> {
    setMessage('');
    setError('');

    try {
      const response = await api<{
        message: string;
        heat_reduced: number;
        energy_spent: number;
      }>('/heat/lay-low', { method: 'POST' });

      setMessage(
        `${response.message} Heat -${response.heat_reduced}, energy -${response.energy_spent}.`,
      );
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  return (
    <section className="page-stack">
      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <article className="card hero-card">
        <div>
          <p className="eyebrow">Current stage</p>
          <h2>Nobody with a plan</h2>
          <p>
            You started with $500, no crew, no property, and no criminal name.
            Complete small NPC jobs, recruit carefully, equip your people, and
            prepare operations before taking risks.
          </p>
        </div>
        <div className="hero-summary">
          <span>Level {user.level}</span>
          <span>Reputation {user.reputation}</span>
          <span>Heat {user.heat}</span>
        </div>
      </article>

      <div className="content-grid two-columns">
        <article className="card">
          <h3>Heat and police attention</h3>
          <p className="muted">
            Firearms, witnesses, failed operations, repeated activity, and weak
            preparation all increase heat. Higher heat reduces success and makes
            consequences worse.
          </p>
          <button
            className="btn"
            disabled={user.heat <= 0 || user.energy < 12}
            onClick={layLow}
          >
            Lie low: 12 energy
          </button>
        </article>

        <article className="card">
          <h3>Progression targets</h3>
          <ol className="compact-list">
            <li>Complete safe work and petty crimes.</li>
            <li>Recruit an affordable first crew member.</li>
            <li>Buy tools and create a useful loadout.</li>
            <li>Prepare and execute Dirty Jobs.</li>
            <li>Save $7,500 or more for a first warehouse.</li>
          </ol>
        </article>
      </div>
    </section>
  );
}
