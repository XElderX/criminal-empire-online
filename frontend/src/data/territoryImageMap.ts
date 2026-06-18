import { slugify } from '../utils/stringFormat';

const DEFAULT_TERRITORY_IMAGE = '/assets/placeholders/default_territory.webp';

export const territoryImageMap: Record<string, string> = {
  slums: '/assets/territories/slums.webp',
  northside: '/assets/territories/slums.webp',
  downtown: '/assets/territories/downtown.webp',
  industrial_zone: '/assets/territories/industrial_zone.webp',
  industrial: '/assets/territories/industrial_zone.webp',
  docks: '/assets/territories/docks.webp',
  southside_docks: '/assets/territories/docks.webp',
  suburbs: '/assets/territories/suburbs.webp',
  nightlife_district: '/assets/territories/nightlife_district.webp',
  market_district: '/assets/territories/market_district.webp',
  police_district: '/assets/territories/police_district.webp',
  rich_district: '/assets/territories/rich_district.webp',
  old_town: '/assets/territories/old_town.webp',
};

export function getTerritoryImage(name: string | null | undefined): string {
  return territoryImageMap[slugify(name)] || DEFAULT_TERRITORY_IMAGE;
}
