import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { CrimePictureCard } from '../components/game/CrimePictureCard';
import { GameHeader } from '../components/game/GameHeader';
import { HeatBadge } from '../components/game/HeatBadge';
import { EmptyState } from '../components/game/EmptyState';
import { getCrimeImage } from '../data/assetManifest';
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
          ? `Action succeeded. You earned $${response.reward}.`
          : `Action failed. Heat increased by ${response.heat_gained}.`,
      );
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoadingId(null);
    }
  }

  return (
    <section className="page-section crimes-page-v036">
      <GameHeader
        eyebrow="Immediate street actions"
        title="Crimes"
        description="Fast street-level actions with picture cards. Dirty Jobs remain the deeper crew and preparation loop."
      />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      {crimes.length === 0 && !error && (
        <EmptyState title="No street actions available" message="Seed crimes or check the API connection." />
      )}

      <div className="card-grid picture-card-grid">
        {crimes.map((crime) => (
          <CrimePictureCard
            key={crime.id}
            image={getCrimeImage(crime.name)}
            title={crime.name}
            eyebrow={`Difficulty ${crime.success_rate}% base chance`}
            description={crime.description}
            actions={(
              <button
                className="btn primary full-width"
                disabled={loadingId === crime.id}
                onClick={() => commit(crime)}
              >
                {loadingId === crime.id ? 'Working…' : 'Commit'}
              </button>
            )}
          >
            <dl className="details-grid">
              <div><dt>Energy</dt><dd>{crime.energy_cost}</dd></div>
              <div><dt>Chance</dt><dd>{crime.success_rate}%</dd></div>
              <div><dt>Reward</dt><dd>${crime.reward_min}–${crime.reward_max}</dd></div>
              <div><dt>Heat</dt><dd><HeatBadge value={crime.heat_gain} /></dd></div>
            </dl>
          </CrimePictureCard>
        ))}
      </div>
    </section>
  );
}
