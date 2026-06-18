interface HeatBadgeProps {
  value: string | number | null | undefined;
  max?: number | null;
}

export function HeatBadge({ value, max }: HeatBadgeProps) {
  const label = max === undefined || max === null
    ? `Heat: +${value ?? 0}`
    : `Heat: +${value ?? 0}–+${max}`;

  return <span className="heat-badge">{label}</span>;
}
