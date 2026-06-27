import type { ActionOutcome, NextAction, NotificationPriority, NotificationType, OutcomeBadgeData, OutcomeSection } from './types';

interface ApiFeedbackDetail {
  path?: string;
  method?: string;
  ok?: boolean;
  data?: unknown;
  error?: string;
}

export function buildOutcomeFromApiResponse(detail: unknown): ActionOutcome | null {
  const feedback = detail as ApiFeedbackDetail;
  const path = String(feedback?.path || '');
  const method = String(feedback?.method || 'GET').toUpperCase();
  const data = feedback?.data as Record<string, unknown> | undefined;

  if (!feedback || method === 'GET') return null;
  if (path.includes('/tutorial/objective')) return null;
  if (path.includes('/update-notices/acknowledge')) return null;

  if (feedback.ok === false) {
    return normalizeOutcome({
      type: 'failure',
      priority: path.includes('/admin') ? 'medium' : 'high',
      title: 'Action blocked',
      message: feedback.error || 'The action could not be completed.',
      source: sourceFromPath(path),
      badges: [{ label: 'Failed', kind: 'danger' }],
      sections: [{ title: 'Reason', lines: [feedback.error || 'The server rejected this request.'] }],
      nextActions: [{ label: 'Check requirements', description: 'Review cash, energy, location, crew status, cooldowns, and item requirements.' }],
    });
  }

  if (!data) return null;

  const explicit = data.outcome_payload || data.outcomePayload || data.notification_payload || data.notificationPayload;
  if (isRecord(explicit)) {
    return normalizeOutcome({
      type: normalizeType(explicit.type, inferType(path, data)),
      priority: normalizePriority(explicit.priority, inferPriority(path, data)),
      title: String(explicit.title || inferTitle(path, data)),
      message: String(explicit.message || data.message || 'Action completed.'),
      source: String(explicit.source || sourceFromPath(path)),
      outcomeType: String(explicit.outcome_type || explicit.outcomeType || ''),
      badges: asBadges(explicit.badges) || badgesFromData(path, data),
      sections: asSections(explicit.sections) || sectionsFromData(path, data),
      nextActions: asNextActions(explicit.next_actions || explicit.nextActions) || nextActionsFromPath(path, data),
      raw: data,
    });
  }

  if (!data.message && !data.run && !data.success && !data.transaction_id && !data.loadout && !data.travelResult) return null;

  return normalizeOutcome({
    type: inferType(path, data),
    priority: inferPriority(path, data),
    title: inferTitle(path, data),
    message: String(data.message || fallbackMessage(path, data)),
    source: sourceFromPath(path),
    badges: badgesFromData(path, data),
    sections: sectionsFromData(path, data),
    nextActions: nextActionsFromPath(path, data),
    raw: data,
  });
}

export function normalizeOutcome(outcome: ActionOutcome): ActionOutcome {
  return {
    type: outcome.type || 'info',
    priority: outcome.priority || 'medium',
    title: outcome.title || 'Action complete',
    message: outcome.message || 'The action finished.',
    source: outcome.source || 'Game action',
    dismissible: outcome.dismissible !== false,
    badges: outcome.badges || [],
    sections: outcome.sections || [],
    nextActions: outcome.nextActions || [],
    raw: outcome.raw,
  };
}

function inferType(path: string, data: Record<string, unknown>): NotificationType {
  const outcome = String(data.outcome || (isRecord(data.run) ? data.run.outcome : '') || '').toLowerCase();
  if (path.includes('heat')) return 'heat';
  if (path.includes('travel') || path.includes('world-map')) return 'travel';
  if (path.includes('shop')) return 'shop';
  if (path.includes('loadouts') || path.includes('inventory')) return 'item';
  if (path.includes('dirty-job')) return outcome.includes('fail') ? 'danger' : 'reward';
  if (path.includes('quick-crimes') || path.includes('crime')) return outcome.includes('fail') ? 'danger' : 'reward';
  if (path.includes('recruitment') || path.includes('my-gang')) return 'success';
  if (data.success === false) return 'failure';
  return 'success';
}

function inferPriority(path: string, data: Record<string, unknown>): NotificationPriority {
  const heat = numberValue(data.heat_gained ?? data.heatChange ?? (isRecord(data.run) ? data.run.heat_gained : undefined));
  const outcome = String(data.outcome || (isRecord(data.run) ? data.run.outcome : '') || '').toLowerCase();
  if (outcome.includes('critical_failure') || outcome.includes('death') || outcome.includes('arrest')) return 'critical';
  if (path.includes('dirty-job') || path.includes('quick-crimes') || path.includes('/crimes/') || path.includes('/crime-opportunities/')) return 'high';
  if (path.includes('travel') && isRecord(data.event)) return 'high';
  if (heat >= 8) return 'high';
  if (path.includes('shop') || path.includes('loadouts') || path.includes('world-map')) return 'medium';
  return 'medium';
}

function inferTitle(path: string, data: Record<string, unknown>): string {
  if (path.includes('quick-crimes')) return data.outcome ? `Quick Crime — ${humanize(String(data.outcome))}` : 'Quick Crime Report';
  if (path.includes('dirty-job')) return data.outcome ? `Dirty Job — ${humanize(String(data.outcome))}` : 'Dirty Job Report';
  if (path.includes('/crimes/')) return data.success === false ? 'Street Action Failed' : 'Street Action Result';
  if (path.includes('travel-and-explore')) return 'Travel & Explore Report';
  if (path.includes('travel')) return 'Travel Report';
  if (path.includes('world-map/locations') && path.includes('explore')) return 'Exploration Report';
  if (path.includes('shops') && path.includes('buy')) return 'Purchase Complete';
  if (path.includes('shops') && path.includes('sell')) return 'Sale Complete';
  if (path.includes('loadouts') && path.includes('equip')) return 'Gear Equipped';
  if (path.includes('loadouts') && path.includes('unequip')) return 'Gear Unequipped';
  if (path.includes('loadouts') && path.includes('carry')) return 'Item Carried';
  if (path.includes('heat')) return 'Heat & Police Update';
  return 'Action Report';
}

function fallbackMessage(path: string, data: Record<string, unknown>): string {
  if (data.success === false) return 'The action failed. Review the changed heat, cooldown, and requirements.';
  if (path.includes('shop')) return 'The shop transaction finished.';
  if (path.includes('loadouts')) return 'The loadout was updated.';
  return 'The action finished.';
}

function sourceFromPath(path: string): string {
  if (path.includes('quick-crimes')) return 'Quick Crimes';
  if (path.includes('dirty-job')) return 'Dirty Jobs';
  if (path.includes('/crimes') || path.includes('/crime-')) return 'Crimes';
  if (path.includes('travel') || path.includes('world-map')) return 'World Map';
  if (path.includes('shops')) return 'Shops';
  if (path.includes('loadouts') || path.includes('inventory')) return 'Inventory / Loadouts';
  if (path.includes('heat')) return 'Heat & Police';
  if (path.includes('warehouse')) return 'Warehouse';
  if (path.includes('recruitment')) return 'Recruitment';
  if (path.includes('jobs')) return 'Street Jobs';
  return 'System';
}

function badgesFromData(path: string, data: Record<string, unknown>): OutcomeBadgeData[] {
  const badges: OutcomeBadgeData[] = [];
  const run = isRecord(data.run) ? data.run : undefined;
  const reward = numberValue(data.reward ?? data.cash_reward ?? run?.cash_reward);
  const dirty = numberValue(data.dirty_cash_reward ?? data.dirty_money_delta ?? run?.dirty_cash_reward);
  const price = numberValue(data.total_price);
  const xp = numberValue(data.experience_gained ?? run?.experience_gained);
  const heat = numberValue(data.heat_gained ?? data.heatChange ?? run?.heat_gained);
  const energy = numberValue(isRecord(data.costs) ? data.costs.energy : undefined);

  if (data.success === true || String(data.status || '').includes('completed')) badges.push({ label: 'Success', kind: 'success' });
  if (data.success === false || String(data.status || '').includes('failed')) badges.push({ label: 'Failure', kind: 'danger' });
  if (reward > 0) badges.push({ label: 'Cash', value: `+$${reward}`, kind: 'money' });
  if (dirty > 0) badges.push({ label: 'Dirty Money', value: `+$${dirty}`, kind: 'money' });
  if (price > 0 && path.includes('buy')) badges.push({ label: 'Spent', value: `$${price}`, kind: 'money' });
  if (xp > 0) badges.push({ label: 'XP', value: `+${xp}`, kind: 'reward' });
  if (heat !== 0) badges.push({ label: 'Heat', value: heat > 0 ? `+${heat}` : `${heat}`, kind: heat > 0 ? 'heat' : 'success' });
  if (energy > 0) badges.push({ label: 'Energy', value: `-${energy}`, kind: 'warning' });
  if (data.payment_type) badges.push({ label: 'Payment', value: String(data.payment_type).replace(/_/g, ' '), kind: 'info' });
  if (data.quantity) badges.push({ label: 'Qty', value: Number(data.quantity), kind: 'item' });
  return badges;
}

function sectionsFromData(path: string, data: Record<string, unknown>): OutcomeSection[] {
  const sections: OutcomeSection[] = [];
  const changes = compactLines([
    moneyLine('Cash', numberValue(data.reward ?? data.cash_reward)),
    moneyLine('Dirty money', numberValue(data.dirty_cash_reward)),
    signedLine('Heat', numberValue(data.heat_gained ?? data.heatChange)),
    signedLine('XP', numberValue(data.experience_gained)),
    data.cooldown_seconds ? `Cooldown started: ${data.cooldown_seconds}s` : '',
    data.total_price ? `Transaction total: $${data.total_price}` : '',
    data.payment_type ? `Payment type: ${String(data.payment_type).replace(/_/g, ' ')}` : '',
  ]);
  if (changes.length > 0) sections.push({ title: 'What changed', lines: changes });

  const run = isRecord(data.run) ? data.run : undefined;
  const resultLines = compactLines([
    data.result_text ? String(data.result_text) : '',
    run?.result_text ? String(run.result_text) : '',
    data.travelResult ? `Travel result: ${String(data.travelResult).replace(/_/g, ' ')}` : '',
    isRecord(data.event) ? `Event: ${String(data.event.title || data.event.type || 'Local event')}` : '',
    isRecord(data.shop) ? `Shop: ${String(data.shop.name || data.shop.slug)}` : '',
    data.item_key ? `Item: ${String(data.item_key).replace(/_/g, ' ')}` : '',
  ]);
  if (resultLines.length > 0) sections.push({ title: 'Report', lines: resultLines });

  const warnings = Array.isArray(data.warnings) ? data.warnings.map(String) : [];
  if (warnings.length > 0) sections.push({ title: 'Warnings', lines: warnings });

  if (sections.length === 0 && data.message) sections.push({ title: 'Summary', lines: [String(data.message)] });
  return sections;
}

function nextActionsFromPath(path: string, data: Record<string, unknown>): NextAction[] {
  if (path.includes('quick-crimes') || path.includes('/crimes/')) {
    return [
      { label: 'Check heat', description: 'Open Heat & Police if heat climbed or police pressure changed.' },
      { label: 'Manage loot', description: 'Sell loot at a fence or store items in inventory/warehouse.' },
    ];
  }
  if (path.includes('dirty-job')) return [{ label: 'Review crew', description: 'Check assigned crew heat, injuries, and loadouts before the next operation.' }];
  if (path.includes('travel')) return [{ label: 'View local actions', description: 'Use the hotspot panel to start nearby crimes, shops, jobs, or exploration.' }];
  if (path.includes('shop')) return [{ label: 'Equip or carry item', description: 'Open Inventory / Loadouts and assign the item to the right crew member.' }];
  if (path.includes('loadouts')) return [{ label: 'Review risk sliders', description: 'Check stealth, suspicion, mobility, and utility after every gear change.' }];
  if (String(data.message || '').toLowerCase().includes('energy')) return [{ label: 'Recover energy', description: 'Wait for world processing or choose lower-cost actions.' }];
  return [];
}

function asBadges(value: unknown): OutcomeBadgeData[] | null {
  return Array.isArray(value) ? value.filter(isRecord).map((entry) => ({ label: String(entry.label || ''), value: entry.value as string | number | undefined, kind: entry.kind as OutcomeBadgeData['kind'] })).filter((entry) => entry.label) : null;
}

function asSections(value: unknown): OutcomeSection[] | null {
  return Array.isArray(value) ? value.filter(isRecord).map((entry) => ({ title: String(entry.title || ''), lines: Array.isArray(entry.lines) ? entry.lines.map(String) : [] })).filter((entry) => entry.title && entry.lines.length > 0) : null;
}

function asNextActions(value: unknown): NextAction[] | null {
  return Array.isArray(value) ? value.filter(isRecord).map((entry) => ({ label: String(entry.label || ''), description: entry.description ? String(entry.description) : undefined, page: entry.page ? String(entry.page) : undefined })).filter((entry) => entry.label) : null;
}

function normalizeType(value: unknown, fallback: NotificationType): NotificationType {
  const allowed = new Set(['success','failure','warning','danger','info','reward','heat','police','injury','arrest','death','level_up','item','money','travel','shop','tutorial','system']);
  return allowed.has(String(value)) ? String(value) as NotificationType : fallback;
}

function normalizePriority(value: unknown, fallback: NotificationPriority): NotificationPriority {
  return ['low', 'medium', 'high', 'critical'].includes(String(value)) ? String(value) as NotificationPriority : fallback;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function numberValue(value: unknown): number {
  const num = Number(value ?? 0);
  return Number.isFinite(num) ? num : 0;
}

function signedLine(label: string, value: number): string {
  if (!value) return '';
  return `${label}: ${value > 0 ? '+' : ''}${value}`;
}

function moneyLine(label: string, value: number): string {
  if (!value) return '';
  return `${label}: ${value > 0 ? '+$' : '-$'}${Math.abs(value)}`;
}

function compactLines(lines: string[]): string[] {
  return lines.map((line) => line.trim()).filter(Boolean);
}

function humanize(value: string): string {
  return value.replace(/_/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());
}
