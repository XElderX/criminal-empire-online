import { slugify } from '../utils/stringFormat';

const DEFAULT_CRIME_IMAGE = '/assets/placeholders/default_crime.webp';

const crimeImageMap: Record<string, string> = {
  pickpocket: '/assets/crimes/crime_pickpocket.webp',
  wallet_snatch: '/assets/crimes/crime_wallet_snatch.webp',
  shoplifting: '/assets/crimes/crime_shoplifting.webp',
  car_break_in: '/assets/crimes/crime_car_break_in.webp',
  vehicle_theft: '/assets/crimes/crime_vehicle_theft.webp',
  bike_theft: '/assets/crimes/crime_bike_theft.webp',
  street_scam: '/assets/crimes/crime_street_scam.webp',
  protection_collection: '/assets/crimes/crime_protection_collection.webp',
  store_robbery: '/assets/crimes/crime_store_robbery.webp',
  cargo_theft: '/assets/crimes/crime_cargo_theft.webp',
  heist_planning: '/assets/crimes/crime_heist_planning.webp',
  armored_van_hit: '/assets/crimes/crime_armored_van_hit.webp',
  armored_truck_heist: '/assets/crimes/crime_armored_truck_heist.webp',
  bank_setup: '/assets/crimes/crime_bank_setup.webp',
};

export function getCrimeImage(name: string | null | undefined): string {
  return crimeImageMap[slugify(name)] || DEFAULT_CRIME_IMAGE;
}
