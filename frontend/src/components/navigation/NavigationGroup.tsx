import type { PageName } from '../../types';

export interface NavigationItem {
  page: PageName;
  label: string;
  adminOnly?: boolean;
}

export interface NavigationGroupData {
  key: string;
  label: string;
  items: NavigationItem[];
}

export function NavigationGroup({
  group,
  page,
  onNavigate,
}: {
  group: NavigationGroupData;
  page: PageName;
  onNavigate: (page: PageName) => void;
}) {
  const activeItem = group.items.find((item) => item.page === page);
  const active = Boolean(activeItem);

  if (group.items.length === 1) {
    const onlyItem = group.items[0];

    return (
      <button
        type="button"
        className={`nav-group-trigger nav-single-link ${active ? 'active' : ''}`}
        onClick={() => onNavigate(onlyItem.page)}
      >
        <span>{group.label}</span>
      </button>
    );
  }

  return (
    <div className={`nav-group ${active ? 'active' : ''}`}>
      <button
        type="button"
        className={`nav-group-trigger ${active ? 'active' : ''}`}
        aria-haspopup="menu"
        aria-expanded="false"
      >
        <span>{group.label}</span>
        {activeItem && <small>{activeItem.label}</small>}
      </button>
      <div className="nav-group-menu" role="menu" aria-label={`${group.label} navigation`}>
        {group.items.map((item) => (
          <button
            type="button"
            key={item.page}
            role="menuitem"
            className={page === item.page ? 'active' : ''}
            onClick={() => onNavigate(item.page)}
          >
            {item.label}
          </button>
        ))}
      </div>
    </div>
  );
}
