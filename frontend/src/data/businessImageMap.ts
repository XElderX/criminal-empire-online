import { slugify } from '../utils/stringFormat';

const DEFAULT_BUSINESS_IMAGE = '/assets/businesses/corner_store.svg';

export const businessImageMap: Record<string, string> = {
  corner_store: '/assets/businesses/corner_store.svg',
  bar: '/assets/businesses/bar.svg',
  nightclub: '/assets/businesses/nightclub.svg',
  garage: '/assets/businesses/garage.svg',
  laundromat: '/assets/businesses/laundromat.svg',
  pawn_shop: '/assets/businesses/pawn_shop.svg',
  taxi_company: '/assets/businesses/taxi_company.svg',
  warehouse: '/assets/businesses/warehouse.svg',
  construction: '/assets/businesses/construction.svg',
  security_company: '/assets/businesses/security_company.svg',
  restaurant: '/assets/businesses/restaurant.svg',
  internet_cafe: '/assets/businesses/internet_cafe.svg',
};

export function getBusinessImage(name: string | null | undefined): string {
  return businessImageMap[slugify(name)] || DEFAULT_BUSINESS_IMAGE;
}
