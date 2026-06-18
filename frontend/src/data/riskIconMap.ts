const DEFAULT_RISK_ICON = '/assets/icons/risk/medium.svg';

const riskIconMap: Record<string, string> = {
  very_low: '/assets/icons/risk/very_low.svg',
  low: '/assets/icons/risk/low.svg',
  medium: '/assets/icons/risk/medium.svg',
  high: '/assets/icons/risk/high.svg',
  very_high: '/assets/icons/risk/very_high.svg',
};

export function getRiskIcon(value: string): string {
  const key = value.replace(/\s+/g, '_').toLowerCase();

  return riskIconMap[key] || DEFAULT_RISK_ICON;
}

export function getHeatIcon(value: number): string {
  if (value >= 18) return '/assets/icons/risk/heat_very_high.svg';
  if (value >= 10) return '/assets/icons/risk/heat_high.svg';
  if (value >= 5) return '/assets/icons/risk/heat_medium.svg';

  return '/assets/icons/risk/heat_low.svg';
}
