import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import type { Crime } from '../types';

interface CrimesPageProps {
  onChanged: () => void;
}

export function CrimesPage({ onChanged }: CrimesPageProps) {
  const [crimes, setCrimes] = useState<Crime[]>([]);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loadingId, setLoadingId] = useState<number | null>(null);

  useEffect(() => {
    api<{ data: Crime[] }>('/crimes')
      .then((response) => setCrimes(response.data))
      .catch((requestError) => setError((requestError as Error).message));
  }, []);

  async function commit(crime: Crime): Promise<void> {
    setLoadingId(crime.id);
    setMessage('');
    setError('');

    try {
      const response = await api<{
        success: boolean;
        reward: number;
        heat_gained: number;
      }>(`/crimes/${crime.id}/commit`, { method: 'POST' });

      setMessage(
        response.success
          ? `Crime succeeded. You earned $${response.reward}.`
          : `Crime failed. Heat increased by ${response.heat_gained}.`,
      );
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoadingId(null);
    }
  }

  return (
    <section className="page-section">
      <header className="page-header">
        <div>
          <p className="eyebrow">Immediate street actions</p>
          <h1>Crimes</h1>
          <p className="muted">
            These remain simple actions. Dirty Jobs provide the deeper
            preparation, crew, equipment, and story loop.
          </p>
        </div>
      </header>

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <div className="card-grid">
        {crimes.map((crime) => (
          <article className="card" key={crime.id}>
            <h2>{crime.name}</h2>
            <p>{crime.description}</p>
            <dl className="details-grid">
              <div><dt>Energy</dt><dd>{crime.energy_cost}</dd></div>
              <div><dt>Base chance</dt><dd>{crime.success_rate}%</dd></div>
              <div><dt>Heat</dt><dd>+{crime.heat_gain}</dd></div>
              <div><dt>Reward</dt><dd>${crime.reward_min}–${crime.reward_max}</dd></div>
            </dl>
            <button
              className="btn primary full-width"
              disabled={loadingId === crime.id}
              onClick={() => commit(crime)}
            >
              Commit crime
            </button>
          </article>
        ))}
      </div>
    </section>
  );
}
