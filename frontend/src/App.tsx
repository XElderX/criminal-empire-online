import { useEffect, useState } from 'react';
import { api, clearToken, getToken } from './api/client';
import { GameLayout } from './components/game/GameLayout';
import { PlayerStats } from './components/PlayerStats';
import { TutorialPanel } from './components/TutorialPanel';
import { UpdateNoticeModal } from './components/UpdateNoticeModal';
import { AdminPage } from './pages/AdminPage';
import { AuthPage } from './pages/AuthPage';
import { CrimesPage } from './pages/CrimesPage';
import { CrewPage } from './pages/CrewPage';
import { DashboardPage } from './pages/DashboardPage';
import { DirtyJobsPage } from './pages/DirtyJobsPage';
import { EquipmentPage } from './pages/EquipmentPage';
import { JobsPage } from './pages/JobsPage';
import { HeatPolicePage } from './pages/HeatPolicePage';
import { MarketPage } from './pages/MarketPage';
import { RecruitmentPage } from './pages/RecruitmentPage';
import { TerritoriesPage } from './pages/TerritoriesPage';
import { WarehousePage } from './pages/WarehousePage';
import { WorldMapPage } from './pages/WorldMapPage';
import type { PageName, TutorialState, UpdateNotice, User } from './types';

const ACTIVE_PAGE_STORAGE_KEY = 'criminal-empire-online-active-page';

export function App() {
  const [user, setUser] = useState<User | null>(null);
  const [page, setPage] = useState<PageName>(() => window.location.pathname.startsWith('/world-map') ? 'world map' : loadSavedPage());
  const [tutorialOpen, setTutorialOpen] = useState(false);
  const [updateNotice, setUpdateNotice] = useState<UpdateNotice | null>(null);
  const [updateNoticeBusy, setUpdateNoticeBusy] = useState(false);
  const [tutorialRefreshKey, setTutorialRefreshKey] = useState(0);
  const [booting, setBooting] = useState(true);

  useEffect(() => {
    localStorage.setItem(ACTIVE_PAGE_STORAGE_KEY, page);
  }, [page]);

  useEffect(() => {
    void restoreSession();
  }, []);

  useEffect(() => {
    if (user?.role !== 'admin' && page === 'admin') {
      setPage('dashboard');
    }
  }, [page, user?.role]);

  async function restoreSession(): Promise<void> {
    if (!getToken()) {
      setBooting(false);
      return;
    }

    try {
      const response = await api<{ user: User }>('/me');
      setUser(response.user);
      await openTutorialForNewPlayer();
      await loadPendingUpdateNotice();
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


  async function loadPendingUpdateNotice(): Promise<void> {
    try {
      const response = await api<{ notice: UpdateNotice | null }>('/update-notices/pending');
      setUpdateNotice(response.notice);
    } catch {
      // Update notice failure should not block gameplay.
    }
  }

  async function confirmUpdateNotice(): Promise<void> {
    if (!updateNotice) {
      return;
    }

    setUpdateNoticeBusy(true);

    try {
      await api('/update-notices/acknowledge', {
        method: 'POST',
        body: JSON.stringify({ notice_id: updateNotice.id }),
      });
      setUpdateNotice(null);
    } finally {
      setUpdateNoticeBusy(false);
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
    localStorage.setItem(ACTIVE_PAGE_STORAGE_KEY, 'dashboard');
    setTutorialRefreshKey((current) => current + 1);
    void openTutorialForNewPlayer();
    void loadPendingUpdateNotice();
  }

  function logout(): void {
    clearToken();
    setUser(null);
    setPage('dashboard');
    localStorage.setItem(ACTIVE_PAGE_STORAGE_KEY, 'dashboard');
    setTutorialOpen(false);
  }

  function navigate(nextPage: PageName): void {
    if (nextPage === 'world map') {
      window.history.pushState({}, '', '/world-map');
    } else if (window.location.pathname.startsWith('/world-map')) {
      window.history.pushState({}, '', '/');
    }

    setPage(nextPage);
  }

  if (booting) {
    return (
      <main className="auth-shell">
        <section className="card auth-card">
          <p className="eyebrow">Criminal Empire Online v 0.6.1</p>
          <h1>Loading city state…</h1>
        </section>
      </main>
    );
  }

  if (!user) {
    return <AuthPage onAuthenticated={authenticated} />;
  }

  return (
    <>
      <GameLayout
        user={user}
        page={page}
        onNavigate={navigate}
        onLogout={logout}
        onOpenTutorial={() => setTutorialOpen(true)}
      >
        <PlayerStats user={user} />
        <PageContent
          page={page}
          user={user}
          onChanged={refreshUser}
          onNavigate={navigate}
        />
      </GameLayout>

      {updateNotice && (
        <UpdateNoticeModal
          notice={updateNotice}
          busy={updateNoticeBusy}
          onConfirm={() => void confirmUpdateNotice()}
        />
      )}

      <TutorialPanel
        isOpen={tutorialOpen}
        refreshKey={tutorialRefreshKey}
        onClose={() => setTutorialOpen(false)}
        onNavigate={(targetPage) => {
          navigate(targetPage);
          setTutorialOpen(false);
        }}
      />
    </>
  );
}

function loadSavedPage(): PageName {
  const savedPage = localStorage.getItem(ACTIVE_PAGE_STORAGE_KEY);

  if (
    savedPage === 'dashboard'
    || savedPage === 'jobs'
    || savedPage === 'dirty jobs'
    || savedPage === 'recruitment'
    || savedPage === 'crew'
    || savedPage === 'equipment'
    || savedPage === 'warehouse'
    || savedPage === 'world map'
    || savedPage === 'crimes'
    || savedPage === 'heat'
    || savedPage === 'market'
    || savedPage === 'territories'
    || savedPage === 'admin'
  ) {
    return savedPage;
  }

  return 'dashboard';
}

function PageContent({
  page,
  user,
  onChanged,
  onNavigate,
}: {
  page: PageName;
  user: User;
  onChanged: () => void;
  onNavigate: (page: PageName) => void;
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
    case 'world map':
      return <WorldMapPage onNavigate={onNavigate} onChanged={onChanged} />;
    case 'crimes':
      return <CrimesPage onChanged={onChanged} />;
    case 'heat':
      return <HeatPolicePage onChanged={onChanged} />;
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
