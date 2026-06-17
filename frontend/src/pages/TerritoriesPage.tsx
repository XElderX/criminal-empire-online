import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';

interface Territory {
  id: number;
  name: string;
  description?: string;
  population: number;
  wealth: number;
  crime_rate: number;
  government_presence: number;
  police_presence?: number;
  unemployment?: number;
  drug_demand?: number;
  weapon_demand?: number;
  owner_gang?: string | null;
}

export function TerritoriesPage() {
  const [territories, setTerritories] = useState<Territory[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    api<{ data: Territory[] }>('/territories')
      .then((response) => setTerritories(response.data))
      .catch((requestError) => setError((requestError as Error).message));
  }, []);

  return (
    <section className="page-section">
      <header className="page-header">
        <div>
          <p className="eyebrow">Living city overview</p>
          <h1>Districts</h1>
          <p className="muted">
            Wealth, crime, demand, and police presence affect opportunities,
            rewards, heat, and future NPC activity.
          </p>
        </div>
      </header>

      {error && <Notice message={error} kind="error" />}

      <div className="card-grid">
        {territories.map((territory) => (
          <article className="card" key={territory.id}>
            <div className="card-heading">
              <div>
                <p className="eyebrow">
                  Population {Number(territory.population).toLocaleString()}
                </p>
                <h2>{territory.name}</h2>
              </div>
              <span className="status-badge">
                {territory.owner_gang || 'Uncontrolled'}
              </span>
            </div>
            {territory.description && <p>{territory.description}</p>}
            <dl className="details-grid">
              <div><dt>Wealth</dt><dd>{territory.wealth}</dd></div>
              <div><dt>Crime</dt><dd>{territory.crime_rate}</dd></div>
              <div><dt>Police</dt><dd>{territory.police_presence ?? territory.government_presence}</dd></div>
              <div><dt>Unemployment</dt><dd>{territory.unemployment ?? '—'}</dd></div>
              <div><dt>Drug demand</dt><dd>{territory.drug_demand ?? '—'}</dd></div>
              <div><dt>Weapon demand</dt><dd>{territory.weapon_demand ?? '—'}</dd></div>
            </dl>
          </article>
        ))}
      </div>
    </section>
  );
}
