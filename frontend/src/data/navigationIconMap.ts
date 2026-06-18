import type { PageName } from '../types';

const DEFAULT_NAV_ICON = '/assets/icons/nav/dashboard.webp';

const navigationIconMap: Record<string, string> = {
  dashboard: '/assets/icons/nav/dashboard.webp',
  crimes: '/assets/icons/nav/crimes.webp',
  jobs: '/assets/icons/nav/jobs.webp',
  dirty_jobs: '/assets/icons/nav/dirty_jobs.webp',
  crew: '/assets/icons/nav/crew.webp',
  recruitment: '/assets/icons/nav/recruitment.webp',
  equipment: '/assets/icons/nav/equipment.webp',
  inventory: '/assets/icons/nav/inventory.webp',
  warehouse: '/assets/icons/nav/warehouse.webp',
  market: '/assets/icons/nav/market.webp',
  drug_market: '/assets/icons/nav/market.webp',
  territories: '/assets/icons/nav/territories.webp',
  map: '/assets/icons/nav/map.webp',
  admin: '/assets/icons/nav/admin.webp',
  tutorial: '/assets/icons/nav/tutorial.webp',
  logout: '/assets/icons/nav/logout.webp',
};

export function getNavigationIcon(page: PageName | string): string {
  const key = String(page).replace(/\s+/g, '_').toLowerCase();

  return navigationIconMap[key] || DEFAULT_NAV_ICON;
}
