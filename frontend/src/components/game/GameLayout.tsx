import type { ReactNode } from 'react';
import { Navigation } from '../Navigation';
import type { PageName, User } from '../../types';
import { BottomNavigation } from './BottomNavigation';

interface GameLayoutProps {
  user: User;
  page: PageName;
  children: ReactNode;
  onNavigate: (page: PageName) => void;
  onLogout: () => void;
  onOpenTutorial: () => void;
}

export function GameLayout({
  user,
  page,
  children,
  onNavigate,
  onLogout,
  onOpenTutorial,
}: GameLayoutProps) {
  return (
    <div className="app-shell game-shell">
      <Navigation
        user={user}
        page={page}
        onNavigate={onNavigate}
        onLogout={onLogout}
        onOpenTutorial={onOpenTutorial}
      />
      <main className="container game-container">{children}</main>
      <BottomNavigation user={user} page={page} onNavigate={onNavigate} />
    </div>
  );
}
