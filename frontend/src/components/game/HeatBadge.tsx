import { getHeatIcon } from '../../data/assetManifest';

interface HeatBadgeProps {
  value: string | number | null | undefined;
  max?: number | null;
}

export function HeatBadge({ value, max }: HeatBadgeProps) {
  const numericValue = Number(value ?? 0);
  const numericMax = max === undefined || max === null ? numericValue : Number(max);
  const label = max === undefined || max === null
    ? `Heat: +${value ?? 0}`
    : `Heat: +${value ?? 0}–+${max}`;

  return (
    <span className="heat-badge">
      <img className="badge-icon" src={getHeatIcon(numericMax)} alt="" />
      {label}
    </span>
  );
}
