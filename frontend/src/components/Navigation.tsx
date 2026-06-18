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
  { page: 'crimes', label: 'Crimes' },
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
        <span className="version-badge">v0.3.6</span>
      </div>

      <div className="nav-links">
        {PLAYER_PAGES.map((entry) => (
          <button
            key={entry.page}
            className={page === entry.page ? 'active' : ''}
            onClick={() => onNavigate(entry.page)}
          >
            {entry.label}
          </button>
        ))}

        {user.role === 'admin' && (
          <button
            className={page === 'admin' ? 'active' : ''}
            onClick={() => onNavigate('admin')}
          >
            Admin
          </button>
        )}
      </div>

      <div className="nav-actions">
        <button onClick={onOpenTutorial}>Tutorial</button>
        <button onClick={onLogout}>Logout</button>
      </div>
    </nav>
  );
}
