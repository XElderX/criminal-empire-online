const DEFAULT_RISK_ICON = '/assets/icons/risk/medium.webp';

const riskIconMap: Record<string, string> = {
  very_low: '/assets/icons/risk/very_low.webp',
  low: '/assets/icons/risk/low.webp',
  medium: '/assets/icons/risk/medium.webp',
  high: '/assets/icons/risk/high.webp',
  very_high: '/assets/icons/risk/very_high.webp',
};

export function getRiskIcon(value: string): string {
  const key = value.replace(/\s+/g, '_').toLowerCase();

  return riskIconMap[key] || DEFAULT_RISK_ICON;
}

export function getHeatIcon(value: number): string {
  if (value >= 18) return '/assets/icons/risk/heat_very_high.webp';
  if (value >= 10) return '/assets/icons/risk/heat_high.webp';
  if (value >= 5) return '/assets/icons/risk/heat_medium.webp';

  return '/assets/icons/risk/heat_low.webp';
}
