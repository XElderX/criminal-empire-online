import { AppTabs } from '../ui/AppTabs';

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
    <AppTabs
      tabs={TABS.map(([key, label]) => ({ key, label }))}
      active={active}
      onChange={onChange}
      ariaLabel="Inventory sections"
    />
  );
}
