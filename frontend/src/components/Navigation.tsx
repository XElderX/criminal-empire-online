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
  { page: 'jobs', label: 'Starter Jobs' },
  { page: 'dirty jobs', label: 'Dirty Jobs' },
  { page: 'recruitment', label: 'Recruitment' },
  { page: 'crew', label: 'Crew' },
  { page: 'equipment', label: 'Equipment' },
  { page: 'warehouse', label: 'Warehouse' },
  { page: 'crimes', label: 'Crimes' },
  { page: 'market', label: 'Market' },
  { page: 'territories', label: 'Districts' },
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
        <span className="version-badge">v0.3.5</span>
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
