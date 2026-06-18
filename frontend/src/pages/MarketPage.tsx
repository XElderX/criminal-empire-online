import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { GameHeader } from '../components/game/GameHeader';
import { getItemIcon } from '../data/assetManifest';

interface DrugMarketRow {
  region: string;
  name: string;
  price: number;
  supply: number;
  demand: number;
  police_pressure: number;
}

export function MarketPage() {
  const [rows, setRows] = useState<DrugMarketRow[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    api<{ data: DrugMarketRow[] }>('/drug-market')
      .then((response) => setRows(response.data))
      .catch((requestError) => setError((requestError as Error).message));
  }, []);

  return (
    <section className="page-section drug-market-page-v036">
      <GameHeader
        eyebrow="Simulated economy"
        title="Drug Market"
        description="NPC demand, supply, and police pressure shape regional prices. Product rows now use the same local contraband icon system as inventory and warehouse storage."
      />

      {error && <Notice message={error} kind="error" />}

      <section className="card table-card">
        <div className="table-scroll">
          <table className="table">
            <thead>
              <tr>
                <th>District</th>
                <th>Product</th>
                <th>Price</th>
                <th>Supply</th>
                <th>Demand</th>
                <th>Police</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row, index) => (
                <tr key={`${row.region}-${row.name}-${index}`}>
                  <td>{row.region}</td>
                  <td>
                    <span className="asset-cell">
                      <img src={getItemIcon(row.name, 'contraband')} alt="" />
                      <span>{row.name}</span>
                    </span>
                  </td>
                  <td>${Number(row.price).toLocaleString()}</td>
                  <td>{row.supply}</td>
                  <td>{row.demand}</td>
                  <td>{row.police_pressure}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </section>
  );
}
