import type { UserLocationState } from './worldMap';

export interface ShopSummary {
  id: number;
  slug: string;
  name: string;
  description: string;
  shop_type: string;
  region_slug: string;
  region_name: string;
  location_slug: string;
  location_name: string;
  location_label: string;
  requires_local_presence: boolean;
  local_presence_satisfied: boolean;
  can_view_remotely: boolean;
  is_black_market: boolean;
  is_legal: boolean;
  is_known: boolean;
  heat_risk: number;
  min_level: number;
  min_reputation: number;
  catalog_count?: number | null;
  travel_hint?: string | null;
}

export interface ShopItem {
  id: number;
  item_key: string;
  asset_type: 'item' | 'weapon';
  name: string;
  item_name: string;
  category: string;
  description?: string | null;
  equipment_slot?: string | null;
  effects: Record<string, number>;
  buy_price: number;
  sell_price_multiplier: number;
  stock_quantity?: number | null;
  max_stock?: number | null;
  min_level: number;
  min_reputation: number;
  availability_status: string;
  is_enabled: boolean;
  disabled_reason?: string | null;
  can_buy: boolean;
  can_sell: boolean;
  locked_reasons: string[];
  warnings: string[];
  owned_quantity: number;
  is_illegal: boolean;
}

export interface SellableInventoryItem {
  inventory_id: number;
  item_key: string;
  name: string;
  category: string;
  quantity: number;
  available_quantity: number;
  sell_price: number;
  can_sell: boolean;
  effects: Record<string, number>;
}

export interface ShopsListResponse {
  data: ShopSummary[];
  currentLocation: UserLocationState;
  configVersion: string;
}

export interface ShopDetailResponse {
  shop: ShopSummary;
  items: ShopItem[];
  sellableInventory: SellableInventoryItem[];
  currentLocation: UserLocationState;
  localPresenceRequired: boolean;
  localPresenceSatisfied: boolean;
  message: string;
}

export interface ShopTransactionResponse {
  message: string;
  transaction_id: number;
  item_key: string;
  quantity: number;
  total_price: number;
  cash_remaining?: number;
  cash_after_sale?: number;
  shop: {
    slug: string;
    name: string;
  };
}
