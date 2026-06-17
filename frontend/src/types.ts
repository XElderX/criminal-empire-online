export type PageName =
  | 'dashboard'
  | 'jobs'
  | 'dirty jobs'
  | 'recruitment'
  | 'crew'
  | 'equipment'
  | 'warehouse'
  | 'crimes'
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
  level: number;
  experience: number;
  strength: number;
  intelligence: number;
  charisma: number;
  combat: number;
  leadership: number;
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

export interface CrewMember {
  id: number;
  npc_id: number;
  first_name: string;
  last_name: string;
  nickname: string | null;
  age: number;
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
  status: string;
  level: number;
  experience: number;
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
  age: number;
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
  can_hire: boolean;
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
}
