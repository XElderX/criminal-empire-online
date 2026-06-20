import { getNavigationIcon } from '../data/assetManifest';
import type { PageName, User } from '../types';
import { NavigationGroup, type NavigationGroupData } from './navigation/NavigationGroup';

interface NavigationProps {
  user: User;
  page: PageName;
  onNavigate: (page: PageName) => void;
  onLogout: () => void;
  onOpenTutorial: () => void;
}

const NAVIGATION_GROUPS: NavigationGroupData[] = [
  { key: 'dashboard', label: 'Dashboard', items: [{ page: 'dashboard', label: 'Dashboard' }] },
  {
    key: 'world',
    label: 'World',
    items: [
      { page: 'world map', label: 'World Map' },
      { page: 'crimes', label: 'Crimes' },
      { page: 'jobs', label: 'Street Jobs' },
      { page: 'dirty jobs', label: 'Dirty Jobs' },
      { page: 'territories', label: 'Territories' },
      { page: 'shops', label: 'Shops' },
      { page: 'market', label: 'Drug Market' },
    ],
  },
  {
    key: 'crew',
    label: 'Crew',
    items: [
      { page: 'crew', label: 'Crew Members' },
      { page: 'recruitment', label: 'Recruitment' },
    ],
  },
  {
    key: 'management',
    label: 'Management',
    items: [
      { page: 'equipment', label: 'Inventory / Loadouts' },
      { page: 'warehouse', label: 'Warehouse' },
    ],
  },
  {
    key: 'heat',
    label: 'Heat',
    items: [{ page: 'heat', label: 'Heat & Police' }],
  },
  {
    key: 'guide',
    label: 'Guide',
    items: [{ page: 'guide', label: 'Guide / Help' }],
  },
];

const BOTTOM_PAGES: Array<{ page: PageName; label: string }> = [
  { page: 'dashboard', label: 'Dashboard' },
  { page: 'world map', label: 'Map' },
  { page: 'crimes', label: 'Crimes' },
  { page: 'crew', label: 'Crew' },
  { page: 'equipment', label: 'Inventory' },
];

export function Navigation({ user, page, onNavigate, onLogout, onOpenTutorial }: NavigationProps) {
  const groups = user.role === 'admin'
    ? [...NAVIGATION_GROUPS, { key: 'admin', label: 'Admin', items: [{ page: 'admin' as PageName, label: 'Admin Dashboard' }] }]
    : NAVIGATION_GROUPS;

  return (
    <nav className="nav compact-nav" aria-label="Primary navigation">
      <div className="brand">
        Criminal Empire
        <span className="version-badge">v 0.7</span>
      </div>

      <div className="nav-links nav-dropdown-groups">
        {groups.map((group) => (
          <NavigationGroup key={group.key} group={group} page={page} onNavigate={onNavigate} />
        ))}
      </div>

      <div className="bottom-quick-nav" aria-label="Mobile quick navigation">
        {BOTTOM_PAGES.map((entry) => (
          <button key={entry.page} className={page === entry.page ? 'active' : ''} onClick={() => onNavigate(entry.page)}>
            <img className="nav-icon" src={getNavigationIcon(entry.page)} alt="" />
            <span>{entry.label}</span>
          </button>
        ))}
        <details className="mobile-more-menu">
          <summary>More</summary>
          <div>
            {groups.flatMap((group) => group.items).map((item) => (
              <button key={item.page} onClick={() => onNavigate(item.page)}>{item.label}</button>
            ))}
          </div>
        </details>
      </div>

      <div className="nav-actions">
        <button onClick={onOpenTutorial}>
          <span className="nav-button-content">
            <img className="nav-icon" src={getNavigationIcon('tutorial')} alt="" />
            <span>Tutorial</span>
          </span>
        </button>
        <button onClick={onLogout}>
          <span className="nav-button-content">
            <img className="nav-icon" src={getNavigationIcon('logout')} alt="" />
            <span>Logout</span>
          </span>
        </button>
      </div>
    </nav>
  );
}
