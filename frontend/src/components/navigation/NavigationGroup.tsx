import type { PageName } from '../../types';

export interface NavigationItem { page: PageName; label: string; adminOnly?: boolean }
export interface NavigationGroupData { key: string; label: string; items: NavigationItem[] }

export function NavigationGroup({ group, page, onNavigate }: { group: NavigationGroupData; page: PageName; onNavigate: (page: PageName) => void }) {
  const active = group.items.some((item) => item.page === page);
  return (
    <details className="nav-group" open={active}>
      <summary className={active ? 'active' : ''}>{group.label}</summary>
      <div className="nav-group-menu">
        {group.items.map((item) => <button key={item.page} className={page === item.page ? 'active' : ''} onClick={() => onNavigate(item.page)}>{item.label}</button>)}
      </div>
    </details>
  );
}
