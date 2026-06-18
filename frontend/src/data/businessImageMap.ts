import { slugify } from '../utils/stringFormat';

const DEFAULT_BUSINESS_IMAGE = '/assets/businesses/corner_store.webp';

export const businessImageMap: Record<string, string> = {
  corner_store: '/assets/businesses/corner_store.webp',
  bar: '/assets/businesses/bar.webp',
  nightclub: '/assets/businesses/nightclub.webp',
  garage: '/assets/businesses/garage.webp',
  laundromat: '/assets/businesses/laundromat.webp',
  pawn_shop: '/assets/businesses/pawn_shop.webp',
  taxi_company: '/assets/businesses/taxi_company.webp',
  warehouse: '/assets/businesses/warehouse.webp',
  construction: '/assets/businesses/construction.webp',
  security_company: '/assets/businesses/security_company.webp',
  restaurant: '/assets/businesses/restaurant.webp',
  internet_cafe: '/assets/businesses/internet_cafe.webp',
};

export function getBusinessImage(name: string | null | undefined): string {
  return businessImageMap[slugify(name)] || DEFAULT_BUSINESS_IMAGE;
}
