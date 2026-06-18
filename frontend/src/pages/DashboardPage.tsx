import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { GameHeader } from '../components/game/GameHeader';
import { HeatBadge } from '../components/game/HeatBadge';
import { ProgressBar } from '../components/game/ProgressBar';
import { SectionCard } from '../components/game/SectionCard';
import { getJobImage, getTerritoryImage } from '../data/assetManifest';
import type { CrewMember, DirtyJobRun, User } from '../types';

interface DashboardPageProps {
  user: User;
  onChanged: () => void;
}

interface TerritorySummary {
  id: number;
  name: string;
  owner_gang?: string | null;
  tax_income?: number;
  income?: number;
}

interface ActivityLog {
  id?: number;
  action?: string;
  title?: string;
  description?: string;
  created_at?: string;
}

export function DashboardPage({ user, onChanged }: DashboardPageProps) {
  const [activeRuns, setActiveRuns] = useState<DirtyJobRun[]>([]);
  const [crew, setCrew] = useState<CrewMember[]>([]);
  const [territories, setTerritories] = useState<TerritorySummary[]>([]);
  const [activity, setActivity] = useState<ActivityLog[]>([]);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    void loadDashboardPanels();
  }, []);

  async function loadDashboardPanels(): Promise<void> {
    const [dirtyJobs, crewResponse, territoryResponse, activityResponse] = await Promise.allSettled([
      api<{ data: DirtyJobRun[] }>('/dirty-jobs/active'),
      api<{ data: CrewMember[] }>('/my-gang'),
      api<{ data: TerritorySummary[] }>('/territories'),
      api<{ data: ActivityLog[] }>('/crime-logs'),
    ]);

    if (dirtyJobs.status === 'fulfilled') {
      setActiveRuns(dirtyJobs.value.data);
    }

    if (crewResponse.status === 'fulfilled') {
      setCrew(crewResponse.value.data);
    }

    if (territoryResponse.status === 'fulfilled') {
      setTerritories(territoryResponse.value.data);
    }

    if (activityResponse.status === 'fulfilled') {
      setActivity(activityResponse.value.data.slice(0, 5));
    }
  }

  async function layLow(): Promise<void> {
    setMessage('');
    setError('');

    try {
      const response = await api<{
        message: string;
        heat_reduced: number;
        energy_spent: number;
      }>('/heat/lay-low', { method: 'POST' });

      setMessage(
        `${response.message} Heat -${response.heat_reduced}, energy -${response.energy_spent}.`,
      );
      await loadDashboardPanels();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  const activeJob = activeRuns[0];
  const availableCrew = crew.filter((member) => member.status === 'active').length;
  const currentTerritory = useMemo(() => {
    return territories.find((territory) => territory.owner_gang)
      || territories.find((territory) => /dock|south/i.test(territory.name))
      || territories[0];
  }, [territories]);

  const energyPercent = Math.round((user.energy / Math.max(user.max_energy, 1)) * 100);
  const xpTarget = Math.max(user.level * 350, 100);
  const xpIntoLevel = user.experience % xpTarget;

  return (
    <section className="page-stack dashboard-v036">
      <GameHeader
        eyebrow="Noir command board"
        title="Dashboard"
        description="Track your cash, heat, crew, territory pressure, and active jobs from one place."
      />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <article className="card dashboard-hero-card">
        <div className="player-avatar-frame">
          <img src="/assets/crew/default.svg" alt="Player avatar placeholder" />
        </div>
        <div className="dashboard-hero-body">
          <p className="eyebrow">The Boss</p>
          <h2>{user.username}</h2>
          <div className="dashboard-hero-meters">
            <ProgressBar label={`Level ${user.level} XP`} value={xpIntoLevel} max={xpTarget} tone="xp" />
            <ProgressBar label="Energy" value={user.energy} max={user.max_energy} tone="energy" />
          </div>
        </div>
        <div className="dashboard-hero-summary">
          <span>${Number(user.cash).toLocaleString()} cash</span>
          <span>${Number(user.bank_cash).toLocaleString()} bank</span>
          <span>{energyPercent}% ready</span>
        </div>
      </article>

      <div className="dashboard-overview-grid">
        <SectionCard title="Active Dirty Job" eyebrow="Today">
          {activeJob ? (
            <div className="dashboard-picture-panel">
              <img src={getJobImage(activeJob.title || activeJob.category)} alt="" />
              <div>
                <h3>{activeJob.title || `Operation #${activeJob.id}`}</h3>
                <p className="muted">{activeJob.status} · {activeJob.territory_name || 'Unknown district'}</p>
                {activeJob.heat_gained !== undefined && <HeatBadge value={activeJob.heat_gained} />}
              </div>
            </div>
          ) : (
            <p className="muted">No active operation. Open Dirty Jobs to prepare the next move.</p>
          )}
        </SectionCard>

        <SectionCard title="Crew Status" eyebrow="People">
          <dl className="details-grid">
            <div><dt>Total crew</dt><dd>{crew.length}</dd></div>
            <div><dt>Available</dt><dd>{availableCrew}</dd></div>
            <div><dt>Busy</dt><dd>{crew.filter((member) => member.status === 'busy').length}</dd></div>
            <div><dt>Injured</dt><dd>{crew.filter((member) => member.status === 'injured').length}</dd></div>
          </dl>
        </SectionCard>

        <SectionCard title="Current Territory" eyebrow="Map">
          {currentTerritory ? (
            <div className="dashboard-picture-panel">
              <img src={getTerritoryImage(currentTerritory.name)} alt="" />
              <div>
                <h3>{currentTerritory.name}</h3>
                <p className="muted">Controlled by {currentTerritory.owner_gang || 'nobody'}</p>
                <strong className="money-text">
                  Income ${Number(currentTerritory.tax_income ?? currentTerritory.income ?? 0).toLocaleString()}
                </strong>
              </div>
            </div>
          ) : (
            <p className="muted">No district data loaded yet.</p>
          )}
        </SectionCard>

        <SectionCard
          title="Heat Control"
          eyebrow="Police pressure"
          actions={<button className="btn" disabled={user.heat <= 0 || user.energy < 12} onClick={layLow}>Lie low: 12 energy</button>}
        >
          <ProgressBar label="Heat" value={user.heat} max={100} tone="heat" />
          <p className="muted">Higher heat makes consequences sharper. Use quiet periods before major jobs.</p>
        </SectionCard>
      </div>

      <SectionCard title="Recent Activity" eyebrow="Logs">
        {activity.length > 0 ? (
          <div className="timeline compact-timeline">
            {activity.map((entry, index) => (
              <article key={entry.id || index}>
                <span>{entry.created_at ? new Date(entry.created_at).toLocaleString() : 'Recent'}</span>
                <strong>{entry.title || entry.action || 'Activity'}</strong>
                {entry.description && <p>{entry.description}</p>}
              </article>
            ))}
          </div>
        ) : (
          <p className="muted">No recent logs found. Actions will appear here as the city reacts.</p>
        )}
      </SectionCard>
    </section>
  );
}
