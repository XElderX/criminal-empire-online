export type InventoryTab = 'overview' | 'boss' | 'crew' | 'owned' | 'warehouse' | 'effects' | 'logs';

const TABS: Array<[InventoryTab, string]> = [
  ['overview', 'Overview'],
  ['boss', 'Boss'],
  ['crew', 'Crew Loadouts'],
  ['owned', 'Owned Items'],
  ['warehouse', 'Storage'],
  ['effects', 'Effects'],
  ['logs', 'Logs'],
];

export function InventoryTabs({ active, onChange }: { active: InventoryTab; onChange: (tab: InventoryTab) => void }) {
  return (
    <div className="page-tabs inventory-tabs" role="tablist" aria-label="Inventory sections">
      {TABS.map(([key, label]) => (
        <button type="button" key={key} className={active === key ? 'active' : ''} onClick={() => onChange(key)}>
          {label}
        </button>
      ))}
    </div>
  );
}
