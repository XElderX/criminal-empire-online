export type InventoryTab = 'loadout' | 'owned' | 'warehouse' | 'effects' | 'logs';

const TABS: Array<[InventoryTab, string]> = [
  ['loadout', 'Loadout Builder'],
  ['owned', 'All Owned Items'],
  ['warehouse', 'Storage'],
  ['effects', 'Item Effects Guide'],
  ['logs', 'Inventory Logs'],
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
