import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';

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
    <section className="page-section">
      <header className="page-header">
        <div>
          <p className="eyebrow">NPC-controlled economy</p>
          <h1>Market</h1>
          <p className="muted">
            Regional supply, demand, and police pressure are simulated without
            requiring another human player.
          </p>
        </div>
      </header>

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
                  <td>{row.name}</td>
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
