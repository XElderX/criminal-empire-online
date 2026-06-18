import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { GameHeader } from '../components/game/GameHeader';
import { getTerritoryImage } from '../data/assetManifest';

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
  tax_income?: number;
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
    <section className="page-section territories-page-v036">
      <GameHeader
        eyebrow="City control map"
        title="Territories"
        description="Each district has local pressure, wealth, population, demand, and control status."
      />

      {error && <Notice message={error} kind="error" />}

      <div className="territory-card-grid">
        {territories.map((territory) => (
          <article className="territory-card card" key={territory.id}>
            <div className="territory-image-frame">
              <img src={getTerritoryImage(territory.name)} alt="" />
              <span className="status-badge territory-control-badge">
                {territory.owner_gang || 'Neutral'}
              </span>
            </div>
            <div className="territory-card-body">
              <p className="eyebrow">
                Population {Number(territory.population).toLocaleString()}
              </p>
              <h2>{territory.name}</h2>
              {territory.description && <p>{territory.description}</p>}
              <dl className="details-grid">
                <div><dt>Wealth</dt><dd>{territory.wealth}</dd></div>
                <div><dt>Crime</dt><dd>{territory.crime_rate}</dd></div>
                <div><dt>Police</dt><dd>{territory.police_presence ?? territory.government_presence}</dd></div>
                <div><dt>Unemployment</dt><dd>{territory.unemployment ?? '—'}</dd></div>
                <div><dt>Drug demand</dt><dd>{territory.drug_demand ?? '—'}</dd></div>
                <div><dt>Tax income</dt><dd>${Number(territory.tax_income ?? 0).toLocaleString()}</dd></div>
              </dl>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}
