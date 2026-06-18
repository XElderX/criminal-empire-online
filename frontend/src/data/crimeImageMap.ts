import { slugify } from '../utils/stringFormat';

const DEFAULT_CRIME_IMAGE = '/assets/placeholders/default_crime.svg';

export const crimeImageMap: Record<string, string> = {
  pickpocket: '/assets/crimes/crime_pickpocket.svg',
  wallet_snatch: '/assets/crimes/crime_wallet_snatch.svg',
  shoplifting: '/assets/crimes/crime_shoplifting.svg',
  car_break_in: '/assets/crimes/crime_car_break_in.svg',
  bike_theft: '/assets/crimes/crime_bike_theft.svg',
  street_scam: '/assets/crimes/crime_street_scam.svg',
  store_robbery: '/assets/crimes/crime_store_robbery.svg',
  cargo_theft: '/assets/crimes/crime_cargo_theft.svg',
  heist_planning: '/assets/crimes/crime_heist_planning.svg',
};

export function getCrimeImage(name: string | null | undefined): string {
  return crimeImageMap[slugify(name)] || DEFAULT_CRIME_IMAGE;
}
