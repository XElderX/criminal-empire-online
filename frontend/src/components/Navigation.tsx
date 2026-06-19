import { getNavigationIcon } from '../data/assetManifest';
import type { PageName, User } from '../types';

interface NavigationProps {
  user: User;
  page: PageName;
  onNavigate: (page: PageName) => void;
  onLogout: () => void;
  onOpenTutorial: () => void;
}

const PLAYER_PAGES: Array<{ page: PageName; label: string }> = [
  { page: 'dashboard', label: 'Dashboard' },
  { page: 'world map', label: 'World Map' },
  { page: 'crimes', label: 'Crimes' },
  { page: 'heat', label: 'Heat & Police' },
  { page: 'dirty jobs', label: 'Dirty Jobs' },
  { page: 'crew', label: 'Crew' },
  { page: 'recruitment', label: 'Recruitment' },
  { page: 'equipment', label: 'Inventory' },
  { page: 'warehouse', label: 'Warehouse' },
  { page: 'jobs', label: 'Street Jobs' },
  { page: 'market', label: 'Drug Market' },
  { page: 'territories', label: 'Territories' },
];

export function Navigation({
  user,
  page,
  onNavigate,
  onLogout,
  onOpenTutorial,
}: NavigationProps) {
  return (
    <nav className="nav">
      <div className="brand">
        Criminal Empire
        <span className="version-badge">v 0.6.1</span>
      </div>

      <div className="nav-links">
        {PLAYER_PAGES.map((entry) => (
          <button
            key={entry.page}
            className={page === entry.page ? 'active' : ''}
            onClick={() => onNavigate(entry.page)}
          >
            <span className="nav-button-content">
              <img className="nav-icon" src={getNavigationIcon(entry.page)} alt="" />
              <span>{entry.label}</span>
            </span>
          </button>
        ))}

        {user.role === 'admin' && (
          <button
            className={page === 'admin' ? 'active' : ''}
            onClick={() => onNavigate('admin')}
          >
            <span className="nav-button-content">
              <img className="nav-icon" src={getNavigationIcon('admin')} alt="" />
              <span>Admin</span>
            </span>
          </button>
        )}
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
