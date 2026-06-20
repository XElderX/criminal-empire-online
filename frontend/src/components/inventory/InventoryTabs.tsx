export type InventoryTab = 'overview' | 'boss' | 'crew' | 'owned' | 'warehouse' | 'effects' | 'logs';

const TABS: Array<[InventoryTab, string]> = [
  ['overview', 'Overview'],
  ['boss', 'Boss Loadout'],
  ['crew', 'Crew Loadouts'],
  ['owned', 'Owned Items'],
  ['warehouse', 'Warehouse / Storage'],
  ['effects', 'Item Effects'],
  ['logs', 'Transactions / Logs'],
];

export function InventoryTabs({ active, onChange }: { active: InventoryTab; onChange: (tab: InventoryTab) => void }) {
  return <div className="page-tabs" role="tablist">{TABS.map(([key, label]) => <button key={key} className={active === key ? 'active' : ''} onClick={() => onChange(key)}>{label}</button>)}</div>;
}
