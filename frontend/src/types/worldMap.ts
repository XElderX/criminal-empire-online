import type { PageName } from '../types';

export interface MapRiskSummary {
  heat: number;
  police_pressure: number;
  danger_level: number;
  score: number;
  label: string;
  tone: string;
}

export interface MapHotspotAction {
  id: number;
  feature_type: string;
  feature_key: string;
  label: string;
  route_hint?: PageName | string | null;
  min_level: number;
}

export interface TerritoryMapSummary {
  id: number;
  name: string;
  owner_gang?: string | null;
  control_label: string;
  population: number;
  wealth: number;
  crime_rate: number;
  government_presence: number;
  district_heat: number;
}

export interface WorldRegion {
  id: number;
  slug: string;
  name: string;
  description: string;
  region_type: string;
  map_asset: string;
  overlay_asset?: string | null;
  travel_cost_cash: number;
  travel_cost_energy: number;
  base_heat: number;
  police_pressure: number;
  danger_level: number;
  recommended_level: number;
  is_active: boolean;
  sort_order: number;
  riskSummary: MapRiskSummary;
}

export interface WorldLocation {
  id: number;
  region_id: number;
  region_slug?: string | null;
  region_name?: string | null;
  slug: string;
  name: string;
  description: string;
  location_type: string;
  x_percent: number;
  y_percent: number;
  heat_level: number;
  police_pressure: number;
  danger_level: number;
  min_level: number;
  travel_requires_level?: number;
  travel_requires_reputation?: number;
  travel_risk_level?: number;
  travel_event_profile?: string | null;
  local_presence_required_default?: boolean;
  exploration_energy_cost?: number;
  exploration_cooldown_seconds?: number;
  linked_feature_key?: string | null;
  available_actions: string[];
  actions: MapHotspotAction[];
  territory?: TerritoryMapSummary | null;
  is_active: boolean;
  riskSummary: MapRiskSummary;
}

export interface UserLocationState {
  region_id?: number | null;
  location_id?: number | null;
  last_region_id?: number | null;
  last_location_id?: number | null;
  region_slug: string;
  region_name: string;
  location_slug: string;
  location_name: string;
  location_type: string;
  last_travel_at?: string | null;
  travel_cooldown_until?: string | null;
  travel_route_type?: string | null;
  travel_status?: string | null;
  arrived_at?: string | null;
  last_local_action_at?: string | null;
  riskSummary: MapRiskSummary;
}

export interface WorldMapResponse {
  world_name: string;
  regions: WorldRegion[];
  currentLocation: UserLocationState;
  summary: {
    cash: number;
    energy: number;
    max_energy: number;
    display_heat: number;
    world_name: string;
  };
  mapAssets: {
    world_map: string;
    world_overlay?: string | null;
    fallback: string;
  };
  legend: Array<{ type: string; label: string }>;
}

export interface RegionMapResponse {
  world_name: string;
  region: WorldRegion;
  locations: WorldLocation[];
  currentLocation: UserLocationState;
  territorySummary: Record<string, number>;
  activitySummary: Record<string, number>;
  mapAssets: {
    map: string;
    overlay?: string | null;
    fallback: string;
  };
  riskSummary: MapRiskSummary;
}

export interface TravelRouteOption {
  type: string;
  label: string;
  cash_cost: number;
  energy_cost: number;
  event_chance: number;
  police_stop_chance: number;
  rival_event_chance: number;
  travel_risk_score: number;
  warnings: string[];
}

export interface LocationMapResponse {
  location: WorldLocation;
  region: WorldRegion;
  territory?: TerritoryMapSummary | null;
  linkedActions: MapHotspotAction[];
  riskSummary: MapRiskSummary;
  travelInfo: {
    cash_cost: number;
    energy_cost: number;
    route_type?: string;
    route_label?: string;
    event_chance?: number;
    police_stop_chance?: number;
    rival_event_chance?: number;
    travel_risk_score?: number;
    route_options?: TravelRouteOption[];
    warnings: string[];
    locked_reason?: string | null;
  };
  currentLocation: UserLocationState;
}

export interface TravelRequest {
  region_slug?: string;
  location_slug?: string;
  route_type?: string;
}

export interface TravelEventNotice {
  type: string;
  title: string;
  description: string;
  heat_delta?: number;
  discovered_type?: string | null;
  discovered_id?: number | null;
}

export interface TravelResponse {
  success: boolean;
  travelResult?: string;
  message: string;
  fromLocation?: Record<string, unknown> | null;
  toLocation?: Record<string, unknown> | null;
  routeType?: string;
  currentLocation: UserLocationState;
  presence?: Record<string, unknown>;
  event?: TravelEventNotice | null;
  costs: {
    cash: number;
    energy: number;
  };
  warnings: string[];
  unlockedActions?: Record<string, number>;
  localActivitySummary?: Record<string, number>;
  heatChange?: number;
  discoveredOpportunity?: Record<string, unknown> | null;
  historyEntry?: Record<string, unknown> | null;
  updatedPlayerStats: {
    cash: number;
    energy: number;
    heat?: number;
  };
  possibleActions: MapHotspotAction[];
}

export interface LocalActivityGroup {
  key: string;
  title: string;
  availableCount: number;
  lockedCount: number;
  localPresenceSatisfied?: boolean;
  availabilityLabel?: string;
  preview: Array<Record<string, unknown>>;
  route_hint?: string | null;
}

export interface LocationActivitiesResponse {
  location: WorldLocation;
  region: WorldRegion;
  currentLocation: UserLocationState;
  playerIsHere: boolean;
  presence?: Record<string, unknown>;
  travelPurpose?: {
    headline: string;
    unlocks: string[];
    remote: string[];
    warnings: string[];
  };
  remoteActions?: string[];
  localUnlocks?: string[];
  localActivitySummary?: Record<string, number>;
  activityGroups: LocalActivityGroup[];
  quickCrimesPreview: Array<Record<string, unknown>>;
  dirtyJobsPreview: Array<Record<string, unknown>>;
  crimeLeadsPreview: Array<Record<string, unknown>>;
  recruitmentPreview: Array<Record<string, unknown>>;
  businessesPreview: Array<Record<string, unknown>>;
  territorySummary?: TerritoryMapSummary | null;
  heatSummary: MapRiskSummary;
  actions: Array<{ label: string; route_hint: string }>;
  localModifiers: Record<string, unknown>;
}

export interface ExploreHotspotResponse {
  message: string;
  energy_cost: number;
  opportunity: {
    id: number;
    type: string;
    title: string;
    description: string;
  };
  activities: LocationActivitiesResponse;
}

export interface TravelAndExploreResponse {
  travel: TravelResponse;
  exploration: ExploreHotspotResponse | null;
}
