import { slugify } from '../utils/stringFormat';

const DEFAULT_TERRITORY_IMAGE = '/assets/placeholders/default_territory.svg';

export const territoryImageMap: Record<string, string> = {
  slums: '/assets/territories/slums.svg',
  northside: '/assets/territories/slums.svg',
  downtown: '/assets/territories/downtown.svg',
  industrial_zone: '/assets/territories/industrial_zone.svg',
  industrial: '/assets/territories/industrial_zone.svg',
  docks: '/assets/territories/docks.svg',
  southside_docks: '/assets/territories/docks.svg',
  suburbs: '/assets/territories/suburbs.svg',
  nightlife_district: '/assets/territories/nightlife_district.svg',
  market_district: '/assets/territories/market_district.svg',
  police_district: '/assets/territories/police_district.svg',
  rich_district: '/assets/territories/rich_district.svg',
  old_town: '/assets/territories/old_town.svg',
};

export function getTerritoryImage(name: string | null | undefined): string {
  return territoryImageMap[slugify(name)] || DEFAULT_TERRITORY_IMAGE;
}
