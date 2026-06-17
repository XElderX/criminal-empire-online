import { useEffect, useState } from 'react';
import { api, clearToken, getToken } from './api/client';
import { Navigation } from './components/Navigation';
import { PlayerStats } from './components/PlayerStats';
import { TutorialPanel } from './components/TutorialPanel';
import { AdminPage } from './pages/AdminPage';
import { AuthPage } from './pages/AuthPage';
import { CrimesPage } from './pages/CrimesPage';
import { CrewPage } from './pages/CrewPage';
import { DashboardPage } from './pages/DashboardPage';
import { DirtyJobsPage } from './pages/DirtyJobsPage';
import { EquipmentPage } from './pages/EquipmentPage';
import { JobsPage } from './pages/JobsPage';
import { MarketPage } from './pages/MarketPage';
import { RecruitmentPage } from './pages/RecruitmentPage';
import { TerritoriesPage } from './pages/TerritoriesPage';
import { WarehousePage } from './pages/WarehousePage';
import type { PageName, TutorialState, User } from './types';

export function App() {
  const [user, setUser] = useState<User | null>(null);
  const [page, setPage] = useState<PageName>('dashboard');
  const [tutorialOpen, setTutorialOpen] = useState(false);
  const [tutorialRefreshKey, setTutorialRefreshKey] = useState(0);
  const [booting, setBooting] = useState(true);

  useEffect(() => {
    void restoreSession();
  }, []);

  async function restoreSession(): Promise<void> {
    if (!getToken()) {
      setBooting(false);
      return;
    }

    try {
      const response = await api<{ user: User }>('/me');
      setUser(response.user);
      await openTutorialForNewPlayer();
    } catch {
      clearToken();
    } finally {
      setBooting(false);
    }
  }

  async function openTutorialForNewPlayer(): Promise<void> {
    try {
      const response = await api<{ tutorial: TutorialState }>('/tutorial');

      if (response.tutorial.status === 'active') {
        setTutorialOpen(true);
      }
    } catch {
      // The application remains usable even when tutorial state cannot load.
    }
  }

  async function refreshUser(): Promise<void> {
    try {
      const response = await api<{ user: User }>('/me');
      setUser(response.user);
      setTutorialRefreshKey((current) => current + 1);
    } catch {
      clearToken();
      setUser(null);
    }
  }

  function authenticated(authenticatedUser: User): void {
    setUser(authenticatedUser);
    setPage('dashboard');
    setTutorialRefreshKey((current) => current + 1);
    void openTutorialForNewPlayer();
  }

  function logout(): void {
    clearToken();
    setUser(null);
    setPage('dashboard');
    setTutorialOpen(false);
  }

  function navigate(nextPage: PageName): void {
    setPage(nextPage);
  }

  if (booting) {
    return (
      <main className="auth-shell">
        <section className="card auth-card">
          <p className="eyebrow">Criminal Empire Online v0.3</p>
          <h1>Loading city state…</h1>
        </section>
      </main>
    );
  }

  if (!user) {
    return <AuthPage onAuthenticated={authenticated} />;
  }

  return (
    <div className="app-shell">
      <Navigation
        user={user}
        page={page}
        onNavigate={navigate}
        onLogout={logout}
        onOpenTutorial={() => setTutorialOpen(true)}
      />

      <main className="container">
        <PlayerStats user={user} />
        <PageContent
          page={page}
          user={user}
          onChanged={refreshUser}
        />
      </main>

      <TutorialPanel
        isOpen={tutorialOpen}
        refreshKey={tutorialRefreshKey}
        onClose={() => setTutorialOpen(false)}
        onNavigate={(targetPage) => {
          navigate(targetPage);
          setTutorialOpen(false);
        }}
      />
    </div>
  );
}

function PageContent({
  page,
  user,
  onChanged,
}: {
  page: PageName;
  user: User;
  onChanged: () => void;
}) {
  switch (page) {
    case 'dashboard':
      return <DashboardPage user={user} onChanged={onChanged} />;
    case 'jobs':
      return <JobsPage onChanged={onChanged} />;
    case 'dirty jobs':
      return <DirtyJobsPage onChanged={onChanged} />;
    case 'recruitment':
      return <RecruitmentPage onChanged={onChanged} />;
    case 'crew':
      return <CrewPage onChanged={onChanged} />;
    case 'equipment':
      return <EquipmentPage onChanged={onChanged} />;
    case 'warehouse':
      return <WarehousePage onChanged={onChanged} />;
    case 'crimes':
      return <CrimesPage onChanged={onChanged} />;
    case 'market':
      return <MarketPage />;
    case 'territories':
      return <TerritoriesPage />;
    case 'admin':
      return <AdminPage currentUser={user} onChanged={onChanged} />;
    default:
      return <DashboardPage user={user} onChanged={onChanged} />;
  }
}
