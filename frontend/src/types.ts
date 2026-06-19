export type PageName =
  | 'dashboard'
  | 'jobs'
  | 'dirty jobs'
  | 'recruitment'
  | 'crew'
  | 'equipment'
  | 'warehouse'
  | 'world map'
  | 'crimes'
  | 'heat'
  | 'market'
  | 'territories'
  | 'admin';

export interface User {
  id: number;
  username: string;
  email: string;
  role: string;
  cash: number;
  bank_cash: number;
  dirty_money: number;
  reputation: number;
  energy: number;
  max_energy: number;
  heat: number;
  boss_personal_heat?: number;
  gang_heat?: number;
  boss_health?: number;
  boss_max_health?: number;
  boss_status?: string;
  boss_rank?: string;
  boss_alive?: number;
  level: number;
  experience: number;
  strength: number;
  intelligence: number;
  charisma: number;
  combat: number;
  leadership: number;
}


export interface HeatLevelInfo {
  key: string;
  label: string;
  description: string;
}

export interface BossProfile {
  id: number;
  name: string;
  first_name: string;
  last_name: string;
  username: string;
  can_rename_initial_name: boolean;
  level: number;
  experience: number;
  rank: string;
  health: number;
  max_health: number;
  status: string;
  injury_status?: string | null;
  arrested_until?: string | null;
  alive: boolean;
  dead_at?: string | null;
  personal_heat: number;
  gang_heat: number;
  display_heat: number;
  successor_member_id?: number | null;
  age?: number;
  gender?: string | null;
  role_code?: string;
  skills?: {
    strength: number;
    shooting: number;
    driving: number;
    intelligence: number;
    stealth: number;
    intimidation: number;
    discipline: number;
    street_knowledge: number;
    endurance: number;
  };
}

export interface HeatCrewSummary {
  id: number;
  npc_id: number;
  first_name: string;
  last_name: string;
  nickname?: string | null;
  status: string;
  personal_heat: number;
  heat_level: HeatLevelInfo;
  under_investigation: boolean;
  sent_away_until?: string | null;
  revenge_risk: number;
  revenge_status: string;
  recommendation: string;
}

export interface HeatDistrictSummary {
  id: number;
  name: string;
  police_presence: number;
  district_heat: number;
  heat_level: HeatLevelInfo;
}

export interface PoliceInvestigation {
  id: number;
  user_id?: number | null;
  target_type: string;
  target_id?: number | null;
  status: string;
  suspicion: number;
  evidence_strength: number;
  investigation_level: number;
  lead_officer?: string | null;
  notes?: string | null;
  opened_at: string;
  last_advanced_at?: string | null;
}

export interface HeatLogEntry {
  id: number;
  target_type: string;
  target_id?: number | null;
  amount: number;
  category: string;
  source_type: string;
  source_id?: number | null;
  description: string;
  can_spillover: number | boolean;
  evidence_linked: number | boolean;
  created_at: string;
}

export interface HeatReductionOption {
  code: string;
  name: string;
  description: string;
  target_type: string;
  heat_reduction_min: number;
  heat_reduction_max: number;
  investigation_reduction_min: number;
  investigation_reduction_max: number;
  cash_cost: number;
  energy_cost: number;
  cooldown_seconds: number;
  risk_percent: number;
  locked_reasons: string[];
  can_use: boolean;
}

export interface HeatOverview {
  boss: BossProfile;
  gang: {
    heat: number;
    level: HeatLevelInfo;
    forecast: string;
    idle_days_count: number;
    last_heat_generating_action_at?: string | null;
  };
  display_heat: number;
  display_heat_level: HeatLevelInfo;
  crew: HeatCrewSummary[];
  highest_crew_heat: number;
  districts: HeatDistrictSummary[];
  investigations: PoliceInvestigation[];
  recent_logs: HeatLogEntry[];
  reduction_options: HeatReductionOption[];
  warnings: string[];
}

export interface UpdateNotice {
  id: number;
  version: string;
  title: string;
  body: string;
}

export interface TutorialStep {
  code: string;
  title: string;
  page: PageName;
  objective: string;
  requires_acknowledgement: boolean;
  completed: boolean;
}

export interface TutorialState {
  status: 'active' | 'completed' | 'skipped';
  current_step_code: string;
  current_step: TutorialStep | null;
  completed_steps: string[];
  steps: TutorialStep[];
  progress: {
    completed: number;
    total: number;
  };
  reward_granted?: number;
  help_mode?: boolean;
}

export interface CrewTrait {
  code: string;
  name: string;
  polarity: string;
  description: string;
  effects: Record<string, number>;
}

export interface CrewEquipment {
  id: number;
  name: string;
  asset_type: 'item' | 'weapon';
  asset_id: number;
  equipment_slot: string;
  durability: number;
  effects: Record<string, number>;
}

export interface CrewHistoryEntry {
  id: number;
  event_type?: string;
  title: string;
  description: string;
  metadata?: Record<string, unknown>;
  created_at: string;
}

export interface CrewLifeStage {
  key: 'very_young' | 'young' | 'adult' | 'mature' | 'elder';
  label: string;
  minimum_age: number;
  maximum_age: number;
  age_range: string;
  age: number;
  recruitable: boolean;
  outside_standard_range: boolean;
}

export interface CrewPortraitData {
  identity_key: string | null;
  gender: 'male' | 'female' | null;
  stage: CrewLifeStage['key'];
  stage_label: string;
  age_range: string;
  resolved_asset_stage: CrewLifeStage['key'] | null;
  url: string;
  thumbnail_url: string;
  fallback_url: string;
  focal_x: number;
  focal_y: number;
  uses_fallback: boolean;
  uses_stage_fallback: boolean;
}

export interface CrewRolePresentation {
  key: string;
  name: string;
  description: string;
  stats: string[];
  accent: string;
  icon: string;
}

export interface CrewMember {
  id: number;
  member_type?: 'boss' | 'crew';
  is_boss?: boolean;
  actor_type?: 'boss' | 'crew';
  actor_id?: number;
  npc_id: number;
  first_name: string;
  last_name: string;
  nickname: string | null;
  gender: 'male' | 'female' | string | null;
  age: number;
  portrait: CrewPortraitData;
  life_stage: CrewLifeStage;
  role_code: string;
  role: CrewRolePresentation;
  biography: string;
  background: string;
  occupation: string;
  territory_name: string;
  personal_cash: number;
  salary_weekly: number;
  unpaid_salary: number;
  health: number;
  max_health: number;
  morale: number;
  loyalty: number;
  personal_heat?: number;
  under_investigation?: number | boolean;
  sent_away_until?: string | null;
  revenge_risk?: number;
  revenge_status?: string;
  status: string;
  level: number;
  experience: number;
  experience_for_next_level: number;
  experience_into_level: number;
  experience_progress_percent: number;
  reputation_label: string;
  strength: number;
  shooting: number;
  driving: number;
  intelligence: number;
  stealth: number;
  intimidation: number;
  discipline: number;
  street_knowledge: number;
  endurance: number;
  jobs_completed: number;
  jobs_failed: number;
  arrests: number;
  injuries: number;
  total_earnings: number;
  dismissed_at?: string | null;
  dismissal_reason?: string | null;
  recovery_until?: string | null;
  arrested_until?: string | null;
  traits: CrewTrait[];
  equipment: CrewEquipment[];
  recent_history: CrewHistoryEntry[];
  history?: CrewHistoryEntry[];
}

export interface InventoryAsset {
  id: number;
  inventory_id?: number;
  name: string;
  description?: string;
  category?: string;
  class?: string;
  equipment_slot?: string;
  quantity: number;
  available_quantity?: number;
  equipped_quantity?: number;
  effects?: Record<string, number>;
  requirements?: Record<string, number>;
  price?: number;
  storage_units?: number;
  base_durability?: number;
  can_buy?: boolean;
}

export interface InventoryResponse {
  items: InventoryAsset[];
  weapons: InventoryAsset[];
  drugs: InventoryAsset[];
}

export interface StarterJob {
  opportunity_id: number;
  job_id: number;
  category: 'legal' | 'criminal';
  title: string;
  description: string;
  territory_name: string;
  giver_first_name?: string;
  giver_last_name?: string;
  giver_nickname?: string;
  reward_min: number;
  reward_max: number;
  duration_seconds_effective: number;
  energy_cost: number;
  difficulty: number;
  heat_min: number;
  heat_max: number;
  can_start: boolean;
  requirement_messages?: string[];
}

export interface StarterJobRun {
  id: number;
  title: string;
  status: string;
  completes_at: string;
  result?: Record<string, unknown> | null;
}

export interface RecruitmentCandidate {
  id: number;
  first_name: string;
  last_name: string;
  nickname: string | null;
  gender: 'male' | 'female' | string | null;
  age: number;
  portrait: CrewPortraitData;
  life_stage: CrewLifeStage;
  role_code: string;
  role: CrewRolePresentation;
  occupation: string;
  territory_name: string;
  biography: string;
  background?: string;
  recruitment_fee: number;
  salary_weekly: number;
  personal_cash: number;
  health: number;
  max_health: number;
  morale: number;
  loyalty: number;
  strength: number;
  shooting: number;
  driving: number;
  intelligence: number;
  stealth: number;
  intimidation: number;
  discipline: number;
  street_knowledge: number;
  endurance: number;
  level: number;
  experience: number;
  experience_for_next_level: number;
  experience_into_level: number;
  experience_progress_percent: number;
  reputation_label: string;
  can_hire: boolean;
  hire_block_reasons: string[];
  traits: CrewTrait[];
}

export interface DirtyJobOpportunity {
  id: number;
  opportunity_id?: number;
  code: string;
  category: string;
  tier: number;
  title: string;
  short_description: string;
  introduction: string;
  briefing?: string;
  target_name?: string;
  territory_name: string;
  police_presence: number;
  contact_name: string;
  contact_type?: string;
  contact_trust?: number;
  estimated_reward_min: number;
  estimated_reward_max: number;
  reward_min: number;
  reward_max: number;
  energy_cost: number;
  heat_min: number;
  heat_max: number;
  min_level: number;
  min_reputation: number;
  min_crew_size: number;
  required_roles: string[];
  required_items: string[];
  requires_warehouse: boolean | number;
  can_accept: boolean;
  requirement_messages: string[];
  expires_at: string;
}

export interface PreparationOption {
  code: string;
  name: string;
  description: string;
  cash_cost?: number;
  energy_cost?: number;
  time_seconds?: number;
  effects?: Record<string, number>;
}

export interface DirtyJobRoleDefinition {
  name: string;
  description: string;
  stats: string[];
}

export interface DirtyJobAssignment {
  id: number;
  gang_member_id: number;
  role_code: string;
  first_name?: string;
  last_name?: string;
  nickname?: string | null;
}

export interface DirtyJobEventChoice {
  code: string;
  label: string;
  description?: string;
}

export interface DirtyJobEvent {
  title?: string;
  text?: string;
  description?: string;
  choices?: DirtyJobEventChoice[];
}

export interface DirtyJobRun {
  id: number;
  opportunity_id: number;
  status: string;
  accepted_at?: string;
  execution_started_at?: string | null;
  completes_at?: string | null;
  seconds_remaining?: number;
  outcome?: string | null;
  cash_reward?: number;
  dirty_cash_reward?: number;
  heat_gained?: number;
  experience_gained?: number;
  reputation_gained?: number;
  selected_decision_code?: string | null;
  result?: {
    outcome?: string;
    result_text?: string;
    physical_rewards?: Array<Record<string, unknown>>;
    crew_consequences?: Array<Record<string, unknown>>;
    equipment_consequences?: Array<Record<string, unknown>>;
  } | null;
  preparations?: Array<Record<string, unknown>>;
  assignments?: DirtyJobAssignment[];
  equipment?: Array<Record<string, unknown>>;
  event?: DirtyJobEvent | null;
  title?: string;
  category?: string;
  territory_name?: string;
}

export interface DirtyJobDetail {
  opportunity: DirtyJobOpportunity & {
    preparation_options?: PreparationOption[];
    event_definition?: DirtyJobEvent;
    execution_seconds?: number;
  };
  run: DirtyJobRun | null;
  crew_roles: Record<string, DirtyJobRoleDefinition>;
}

export interface PropertyListing {
  id: number;
  name: string;
  description: string;
  territory_name: string;
  purchase_price: number;
  storage_capacity: number;
  vehicle_capacity: number;
  item_capacity?: number;
  drug_capacity?: number;
  security_rating: number;
  condition_rating: number;
  weekly_operating_cost: number;
  heat_visibility: number;
  seller_name?: string;
  can_purchase?: boolean;
}

export interface WarehouseStorageRow {
  id: number;
  asset_type: 'item' | 'weapon' | 'drug';
  asset_id: number;
  quantity: number;
  reserved_quantity: number;
  storage_units_each: number;
  name: string;
  item_category?: string | null;
}

export interface Vehicle {
  id: number;
  name: string;
  model_name?: string;
  condition_rating: number;
  estimated_value: number;
  stolen: boolean | number;
  evidence_level: number;
  status: string;
}

export interface WarehouseUpgrade {
  id: number;
  code: string;
  name: string;
  description: string;
  price: number;
  effects: Record<string, number>;
  installed_at?: string;
}

export interface StorageLog {
  id: number;
  direction: string;
  asset_type?: string;
  asset_id?: number;
  quantity?: number;
  description: string;
  created_at: string;
}

export interface Warehouse {
  id: number;
  name: string;
  territory_name: string;
  status: string;
  security_rating: number;
  condition_rating: number;
  storage_capacity: number;
  used_storage_capacity: number;
  available_storage_capacity: number;
  vehicle_capacity: number;
  used_vehicle_slots: number;
  available_vehicle_slots: number;
  weekly_operating_cost: number;
  operating_debt: number;
  heat_visibility: number;
  storage: WarehouseStorageRow[];
  vehicles: Vehicle[];
  upgrades: WarehouseUpgrade[];
  recent_logs: StorageLog[];
}

export interface WarehouseOverview {
  warehouses: Warehouse[];
  listings: PropertyListing[];
  upgrade_catalog: WarehouseUpgrade[];
}

export interface Crime {
  id: number;
  name: string;
  description: string;
  energy_cost: number;
  success_rate: number;
  heat_gain: number;
  reward_min: number;
  reward_max: number;
  cooldown_seconds?: number;
  cooldown?: QuickCrimeCooldown;
}

export interface CrimeDiscoveryLocation {
  id: number;
  code: string;
  name: string;
  description: string;
  energy_cost: number;
  cash_cost: number;
  min_level: number;
  risk_level: string;
  can_explore: boolean;
  blocked_reason?: string | null;
}

export interface CrimePreparationRecord {
  id: number;
  code: string;
  name: string;
  description: string;
  cash_cost: number;
  energy_cost: number;
  effects: Record<string, number>;
  applied_at?: string;
}

export interface CrimePreparationOption {
  code: string;
  name: string;
  description: string;
  cash_cost: number;
  energy_cost: number;
  effects: Record<string, number>;
}

export interface CrimeOpportunityAssignment {
  id: number;
  gang_member_id: number;
  role_code: string;
  first_name?: string;
  last_name?: string;
  nickname?: string | null;
  status?: string;
}

export interface CrimeOpportunityEquipment {
  id: number;
  asset_type: 'item' | 'weapon';
  asset_id: number;
  name: string;
  category?: string | null;
  quantity: number;
  effects: Record<string, number>;
}

export interface CrimeOpportunity {
  id: number;
  code: string;
  category: string;
  tier: number;
  title: string;
  briefing: string;
  target_name?: string | null;
  information_level: 'rumor' | 'lead' | 'confirmed' | 'trap';
  status: string;
  source_type: string;
  source_description?: string | null;
  quality: string;
  reliability: number;
  estimated_reward_min: number;
  estimated_reward_max: number;
  estimated_heat_min: number;
  estimated_heat_max: number;
  energy_cost: number;
  min_crew: number;
  max_crew: number;
  recommended_roles: string[];
  required_items: string[];
  relevant_stats: string[];
  location_name: string;
  territory_name?: string | null;
  source_name?: string;
  expires_at?: string | null;
  is_expired: boolean;
  can_investigate: boolean;
  can_prepare: boolean;
  can_execute: boolean;
  preparations: CrimePreparationRecord[];
  assignments: CrimeOpportunityAssignment[];
  equipment: CrimeOpportunityEquipment[];
  preparation_options: CrimePreparationOption[];
}

export interface CrimeCrewOption {
  id: number;
  actor_type?: 'boss' | 'crew';
  actor_id?: number;
  is_boss?: boolean;
  npc_id: number;
  role_code: string;
  status: string;
  first_name: string;
  last_name: string;
  nickname?: string | null;
  strength: number;
  shooting: number;
  driving: number;
  intelligence: number;
  stealth: number;
  intimidation: number;
  discipline: number;
  street_knowledge: number;
  endurance: number;
  loyalty: number;
  morale: number;
  personal_heat?: number;
}

export interface CrimeEquipmentOption {
  asset_type: 'item' | 'weapon';
  asset_id: number;
  name: string;
  category?: string | null;
  effects: Record<string, number> | string;
  quantity: number;
  available_quantity: number;
}

export interface CrimeEventChoice {
  code: string;
  label: string;
  description?: string;
}

export interface CrimeRunEvent {
  id: number;
  event_code: string;
  title: string;
  description: string;
  status: string;
  choices: CrimeEventChoice[];
}

export interface CrimeRun {
  id: number;
  opportunity_id: number;
  status: string;
  outcome?: string | null;
  success_chance: number;
  disaster_chance: number;
  police_chance: number;
  reward_dirty_cash: number;
  heat_gained: number;
  result?: {
    outcome?: string;
    title?: string;
    description?: string;
    decision_code?: string | null;
    crew_consequences?: Array<Record<string, unknown>>;
  } | null;
  event?: CrimeRunEvent | null;
  started_at?: string;
  completed_at?: string | null;
}

export interface NpcContact {
  id: number;
  npc_id: number;
  relationship_type: string;
  trust: number;
  fear: number;
  respect: number;
  suspicion: number;
  full_name: string;
  nickname?: string | null;
  role: string;
  status: string;
  alive: number;
  territory_name?: string | null;
  portrait?: CrewPortraitData;
}

export interface CrimeOverview {
  legacy_crimes: Crime[];
  locations: CrimeDiscoveryLocation[];
  opportunities: CrimeOpportunity[];
  active_runs: CrimeRun[];
  history: CrimeRun[];
  contacts: NpcContact[];
  crew: CrimeCrewOption[];
  equipment: CrimeEquipmentOption[];
  preparation_options: CrimePreparationOption[];
}


export interface QuickCrimePreparationOption {
  code: string;
  name: string;
  description: string;
  cash_cost: number;
  energy_cost: number;
  effects: Record<string, number>;
}

export interface QuickCrimePreparedAction {
  id: number;
  code: string;
  name: string;
  description: string;
  cash_cost: number;
  energy_cost: number;
  effects: Record<string, number>;
  applied_at?: string;
}

export interface QuickCrimeCooldown {
  active: boolean;
  remaining_seconds: number;
  available_at?: string | null;
}

export interface QuickCrimeMissingItem {
  tag: string;
  label: string;
  type: string;
}

export interface QuickCrimeTemplate {
  id: number;
  code: string;
  title: string;
  category: string;
  description: string;
  tier: number;
  min_level: number;
  energy_cost: number;
  max_heat?: number | null;
  cooldown_seconds: number;
  base_success_rate: number;
  base_event_chance: number;
  base_disaster_chance: number;
  reward_min: number;
  reward_max: number;
  heat_min: number;
  heat_max: number;
  xp_min: number;
  xp_max: number;
  required_all_item_tags: string[];
  required_any_item_tags: string[];
  recommended_item_tags: string[];
  required_crew_count: number;
  recommended_crew_roles: string[];
  relevant_stats: string[];
  preparation_options: QuickCrimePreparationOption[];
  can_start: boolean;
  locked_reasons: string[];
  missing_items: QuickCrimeMissingItem[];
  cooldown: QuickCrimeCooldown;
  prepared: QuickCrimePreparedAction[];
}

export interface QuickCrimeEventChoice {
  code: string;
  label: string;
  description?: string;
  effects?: Record<string, number>;
}

export interface QuickCrimeEvent {
  id: number;
  event_code: string;
  title: string;
  description: string;
  status: string;
  choices: QuickCrimeEventChoice[];
}

export interface QuickCrimeResultPayload {
  outcome?: string;
  title?: string;
  description?: string;
  decision_code?: string | null;
  cash_gained?: number;
  loot?: Array<{ item_code: string; quantity: number }>;
  xp?: Record<string, unknown>;
  crew_xp?: Array<Record<string, unknown>>;
  skill_gains?: Array<Record<string, unknown>>;
  cooldown_started?: boolean;
}

export interface QuickCrimeRun {
  id: number;
  template_id: number;
  status: string;
  outcome?: string | null;
  success_chance: number;
  event_chance: number;
  disaster_chance: number;
  reward_cash: number;
  reward_dirty_cash: number;
  heat_gained: number;
  experience_gained: number;
  result?: QuickCrimeResultPayload | null;
  event?: QuickCrimeEvent | null;
  started_at?: string | null;
  resolved_at?: string | null;
}

export interface QuickCrimeOverview {
  data: QuickCrimeTemplate[];
  active_runs: QuickCrimeRun[];
  history: QuickCrimeRun[];
  progression: Record<string, unknown>;
}


export interface AdminNpcSummary {
  id: number;
  full_name: string;
  display_name: string;
  nickname?: string | null;
  age: number;
  gender?: string | null;
  role: string;
  occupation?: string | null;
  biography?: string | null;
  organization?: string | null;
  affiliation?: string | null;
  status: string;
  alive: number;
  is_dead: boolean;
  health: number;
  personal_cash: number;
  reputation: number;
  wealth_class: string;
  territory_name?: string | null;
  business_name?: string | null;
  current_activity?: string | null;
  death_category?: string | null;
  death_game_date?: string | null;
  death_notes?: string | null;
  last_seen_at?: string | null;
  source_event?: string | null;
  notes?: string | null;
  portrait: CrewPortraitData;
  life_stage: CrewLifeStage;
  flags: Record<string, boolean>;
  stats: Record<string, number>;
}

export interface AdminNpcListResponse {
  data: AdminNpcSummary[];
  pagination: {
    total: number;
    limit: number;
    offset: number;
  };
  filters: {
    statuses: string[];
    roles: string[];
    districts: string[];
  };
}

export interface AdminNpcDetailResponse {
  npc: AdminNpcSummary & {
    relationships: Array<Record<string, unknown>>;
    timeline: Array<Record<string, unknown>>;
    crime_involvement: Array<Record<string, unknown>>;
    status_logs: Array<Record<string, unknown>>;
  };
}
