import type { MapRiskSummary } from '../../types/worldMap';

export function LocationRiskBadge({ risk }: { risk: MapRiskSummary }) {
  return (
    <span className={`location-risk-badge ${risk.tone}`}>
      {risk.label} · heat {risk.heat} · police {risk.police_pressure} · danger {risk.danger_level}
    </span>
  );
}
