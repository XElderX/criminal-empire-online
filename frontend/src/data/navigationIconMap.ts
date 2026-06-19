import type { PageName } from '../types';

const DEFAULT_NAV_ICON = '/assets/icons/nav/dashboard.webp';

const navigationIconMap: Record<string, string> = {
  dashboard: '/assets/icons/nav/dashboard.webp',
  heat: '/assets/icons/nav/heat.webp',
  police: '/assets/icons/nav/heat.webp',
  crimes: '/assets/icons/nav/crimes.webp',
  jobs: '/assets/icons/nav/dirty_jobs.webp',
  dirty_jobs: '/assets/icons/nav/dirty_jobs.webp',
  crew: '/assets/icons/nav/crew.webp',
  recruitment: '/assets/icons/nav/crew.webp',
  equipment: '/assets/icons/nav/inventory.webp',
  inventory: '/assets/icons/nav/inventory.webp',
  warehouse: '/assets/icons/nav/inventory.webp',
  market: '/assets/icons/nav/drug_market.webp',
  drug_market: '/assets/icons/nav/drug_market.webp',
  businesses: '/assets/icons/nav/businesses.webp',
  gangs: '/assets/icons/nav/gangs.webp',
  territories: '/assets/icons/nav/territories.webp',
  map: '/assets/icons/nav/territories.webp',
  admin: '/assets/icons/nav/admin.webp',
  messages: '/assets/icons/nav/messages.webp',
  settings: '/assets/icons/nav/settings.webp',
  tutorial: '/assets/icons/nav/dashboard.webp',
  logout: '/assets/icons/nav/settings.webp',
  help: '/assets/icons/nav/settings.webp',
  contacts: '/assets/icons/nav/crew.webp',
  black_market: '/assets/icons/nav/drug_market.webp',
  achievements: '/assets/icons/nav/admin.webp',
  save: '/assets/icons/nav/admin.webp',
  upgrades: '/assets/icons/nav/businesses.webp',
};

export function getNavigationIcon(page: PageName | string): string {
  const key = String(page).replace(/\s+/g, '_').toLowerCase();

  return navigationIconMap[key] || DEFAULT_NAV_ICON;
}
