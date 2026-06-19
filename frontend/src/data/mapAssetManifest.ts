const WORLD_MAP = '/assets/maps/world/world_map.webp';
const WORLD_OVERLAY = '/assets/maps/world/world_map_overlay.svg';
const MAP_FALLBACK = '/assets/maps/placeholders/default_map.svg';
const LOCATION_FALLBACK = '/assets/maps/placeholders/default_location.svg';
const HOTSPOT_FALLBACK = '/assets/maps/placeholders/default_hotspot.svg';

const regionMapAssets: Record<string, string> = {
  'main-city': '/assets/maps/city/main_city_map.webp',
  suburbs: '/assets/maps/suburbs/suburbs_map.webp',
  'industrial-zone': '/assets/maps/industrial/industrial_zone_map.webp',
  docks: '/assets/maps/docks/docks_map.webp',
  'rural-county': '/assets/maps/rural/rural_county_map.webp',
  'forest-hills': '/assets/maps/forest/forest_hills_map.webp',
  shore: '/assets/maps/shore/shore_beach_sea_map.webp',
  'old-town': '/assets/maps/old-town/old_town_map.webp',
  outskirts: '/assets/maps/outskirts/highway_outskirts_map.webp',
};

const regionOverlayAssets: Record<string, string> = {
  'main-city': '/assets/maps/city/main_city_overlay.svg',
  suburbs: '/assets/maps/suburbs/suburbs_overlay.svg',
  'industrial-zone': '/assets/maps/industrial/industrial_zone_overlay.svg',
  docks: '/assets/maps/docks/docks_overlay.svg',
  'rural-county': '/assets/maps/rural/rural_county_overlay.svg',
  'forest-hills': '/assets/maps/forest/forest_hills_overlay.svg',
  shore: '/assets/maps/shore/shore_beach_sea_overlay.svg',
  'old-town': '/assets/maps/old-town/old_town_overlay.svg',
  outskirts: '/assets/maps/outskirts/highway_outskirts_overlay.svg',
};

const hotspotIcons: Record<string, string> = {
  district: '◎',
  safehouse: '⌂',
  garage: '▣',
  black_market: '$',
  police: '◆',
  business: '▤',
  recruitment: '★',
  warehouse: '▦',
  travel: '➤',
  crime: '◇',
  point_of_interest: '✦',
};

export function getWorldMapAsset(): string {
  return WORLD_MAP;
}

export function getWorldMapOverlay(): string {
  return WORLD_OVERLAY;
}

export function getRegionMapAsset(regionSlug: string): string {
  return regionMapAssets[regionSlug] || LOCATION_FALLBACK;
}

export function getRegionOverlayAsset(regionSlug: string): string {
  return regionOverlayAssets[regionSlug] || '';
}

export function getMapPlaceholder(): string {
  return MAP_FALLBACK;
}

export function getLocationPlaceholder(): string {
  return LOCATION_FALLBACK;
}

export function getHotspotIcon(type: string): string {
  return hotspotIcons[type] || hotspotIcons.point_of_interest || HOTSPOT_FALLBACK;
}
