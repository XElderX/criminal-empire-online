export type NotificationType =
  | 'success'
  | 'failure'
  | 'warning'
  | 'danger'
  | 'info'
  | 'reward'
  | 'heat'
  | 'police'
  | 'injury'
  | 'arrest'
  | 'death'
  | 'level_up'
  | 'item'
  | 'money'
  | 'travel'
  | 'shop'
  | 'tutorial'
  | 'system';

export type NotificationPriority = 'low' | 'medium' | 'high' | 'critical';

export interface OutcomeBadgeData {
  label: string;
  value?: string | number;
  kind?: 'success' | 'warning' | 'danger' | 'info' | 'reward' | 'heat' | 'money' | 'item';
}

export interface OutcomeSection {
  title: string;
  lines: string[];
}

export interface NextAction {
  label: string;
  description?: string;
  page?: string;
}

export interface ActionOutcome {
  type: NotificationType;
  priority: NotificationPriority;
  title: string;
  message: string;
  source?: string;
  outcomeType?: string;
  dismissible?: boolean;
  badges?: OutcomeBadgeData[];
  sections?: OutcomeSection[];
  nextActions?: NextAction[];
  raw?: unknown;
}

export interface NotificationEntry {
  id: string;
  type: NotificationType;
  priority: NotificationPriority;
  title: string;
  message: string;
  source?: string;
  read: boolean;
  createdAt: string;
  payload?: unknown;
}
