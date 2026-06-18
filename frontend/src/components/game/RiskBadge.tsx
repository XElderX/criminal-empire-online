import { getRiskIcon } from '../../data/assetManifest';

interface RiskBadgeProps {
  value: string | number | null | undefined;
}

export function RiskBadge({ value }: RiskBadgeProps) {
  const label = normalizeRisk(value);

  return (
    <span className={`risk-badge risk-${label.toLowerCase().replace(/\s+/g, '-')}`}>
      <img className="badge-icon" src={getRiskIcon(label)} alt="" />
      Risk: {label}
    </span>
  );
}

function normalizeRisk(value: string | number | null | undefined): string {
  if (typeof value === 'number') {
    if (value >= 80) return 'Very high';
    if (value >= 60) return 'High';
    if (value >= 35) return 'Medium';
    return 'Low';
  }

  const normalized = String(value || 'medium').replace(/_/g, ' ');
  return normalized.charAt(0).toUpperCase() + normalized.slice(1);
}
