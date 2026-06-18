import { slugify } from '../utils/stringFormat';

export const DEFAULT_ITEM_ICON = '/assets/placeholders/default_item.svg';

export const itemIconMap: Record<string, string> = {
  basic_pistol: '/assets/icons/items/weapons/basic_pistol.svg',
  pistol: '/assets/icons/items/weapons/basic_pistol.svg',
  heavy_pistol: '/assets/icons/items/weapons/heavy_pistol.svg',
  revolver: '/assets/icons/items/weapons/revolver.svg',
  compact_smg: '/assets/icons/items/weapons/compact_smg.svg',
  smg: '/assets/icons/items/weapons/compact_smg.svg',
  black_market_rifle: '/assets/icons/items/weapons/black_market_rifle.svg',
  rifle: '/assets/icons/items/weapons/black_market_rifle.svg',
  assault_rifle: '/assets/icons/items/weapons/black_market_rifle.svg',
  shotgun: '/assets/icons/items/weapons/shotgun.svg',
  knife: '/assets/icons/items/weapons/knife.svg',
  switch_knife: '/assets/icons/items/weapons/knife.svg',
  machete: '/assets/icons/items/weapons/machete.svg',
  baseball_bat: '/assets/icons/items/weapons/baseball_bat.svg',
  brass_knuckles: '/assets/icons/items/weapons/brass_knuckles.svg',
  gloves: '/assets/icons/items/protection/gloves.svg',
  mask: '/assets/icons/items/protection/mask.svg',
  hoodie: '/assets/icons/items/protection/hoodie.svg',
  bulletproof_vest: '/assets/icons/items/protection/bulletproof_vest.svg',
  body_armor: '/assets/icons/items/protection/body_armor.svg',
  helmet: '/assets/icons/items/protection/helmet.svg',
  leather_jacket: '/assets/icons/items/protection/leather_jacket.svg',
  boots: '/assets/icons/items/protection/boots.svg',
  lockpick_kit: '/assets/icons/items/tools/lockpick_kit.svg',
  crowbar: '/assets/icons/items/tools/crowbar.svg',
  bolt_cutters: '/assets/icons/items/tools/bolt_cutters.svg',
  drill: '/assets/icons/items/tools/drill.svg',
  safe_cracker: '/assets/icons/items/tools/safe_cracker.svg',
  rope: '/assets/icons/items/tools/rope.svg',
  duct_tape: '/assets/icons/items/tools/duct_tape.svg',
  flashlight: '/assets/icons/items/tools/flashlight.svg',
  burner_phone: '/assets/icons/items/tools/burner_phone.svg',
  phone: '/assets/icons/items/tools/burner_phone.svg',
  radio: '/assets/icons/items/tools/radio.svg',
  laptop: '/assets/icons/items/tools/laptop.svg',
  usb_stick: '/assets/icons/items/tools/usb_stick.svg',
  signal_jammer: '/assets/icons/items/tools/signal_jammer.svg',
  camera: '/assets/icons/items/tools/camera.svg',
  fake_id_kit: '/assets/icons/items/tools/fake_id_kit.svg',
  fake_id: '/assets/icons/items/tools/fake_id_kit.svg',
  license_plate: '/assets/icons/items/tools/license_plate.svg',
  duffel_bag: '/assets/icons/items/tools/duffel_bag.svg',
  car_key: '/assets/icons/items/vehicles/car_key.svg',
  getaway_car: '/assets/icons/items/vehicles/getaway_car.svg',
  van: '/assets/icons/items/vehicles/van.svg',
  motorcycle: '/assets/icons/items/vehicles/motorcycle.svg',
  truck: '/assets/icons/items/vehicles/truck.svg',
  garage_key: '/assets/icons/items/vehicles/garage_key.svg',
  fuel_can: '/assets/icons/items/vehicles/fuel_can.svg',
  spare_plates: '/assets/icons/items/vehicles/spare_plates.svg',
  cash_bundle: '/assets/icons/items/valuables/cash_bundle.svg',
  bank_card: '/assets/icons/items/valuables/bank_card.svg',
  gold_watch: '/assets/icons/items/valuables/gold_watch.svg',
  gold_bar: '/assets/icons/items/valuables/gold_bar.svg',
  jewelry: '/assets/icons/items/valuables/jewelry.svg',
  safe_box: '/assets/icons/items/valuables/safe_box.svg',
  ledger_book: '/assets/icons/items/valuables/ledger_book.svg',
  contract: '/assets/icons/items/valuables/contract.svg',
  weed: '/assets/icons/items/contraband/weed.svg',
  cocaine: '/assets/icons/items/contraband/cocaine.svg',
  heroin: '/assets/icons/items/contraband/heroin.svg',
  meth: '/assets/icons/items/contraband/meth.svg',
  pills: '/assets/icons/items/contraband/pills.svg',
  package: '/assets/icons/items/contraband/package.svg',
  contraband_crate: '/assets/icons/items/contraband/contraband_crate.svg',
};

export function normalizeItemName(value: string | null | undefined): string {
  return slugify(value);
}

export function getItemIcon(
  name: string | null | undefined,
  category?: string | null,
): string {
  const nameKey = normalizeItemName(name);
  const categoryKey = normalizeItemName(category);

  return itemIconMap[nameKey] || itemIconMap[categoryKey] || DEFAULT_ITEM_ICON;
}
