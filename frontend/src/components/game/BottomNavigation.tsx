import { getNavigationIcon } from '../../data/assetManifest';
import type { PageName, User } from '../../types';

interface BottomNavigationProps {
  user: User;
  page: PageName;
  onNavigate: (page: PageName) => void;
}

const bottomPages: Array<{ page: PageName; label: string }> = [
  { page: 'dashboard', label: 'Dashboard' },
  { page: 'crew', label: 'Crew' },
  { page: 'dirty jobs', label: 'Jobs' },
  { page: 'equipment', label: 'Inventory' },
  { page: 'territories', label: 'Map' },
];

export function BottomNavigation({ user, page, onNavigate }: BottomNavigationProps) {
  const entries = user.role === 'admin'
    ? [...bottomPages, { page: 'admin' as PageName, label: 'Admin' }]
    : bottomPages;

  return (
    <nav className="bottom-nav" aria-label="Primary mobile navigation">
      {entries.map((entry) => (
        <button
          key={entry.page}
          className={page === entry.page ? 'active' : ''}
          onClick={() => onNavigate(entry.page)}
        >
          <img className="bottom-nav-icon" src={getNavigationIcon(entry.page)} alt="" />
          <span>{entry.label}</span>
        </button>
      ))}
    </nav>
  );
}
