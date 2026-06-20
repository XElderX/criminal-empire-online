import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { EmptyState } from '../components/game/EmptyState';
import { GameHeader } from '../components/game/GameHeader';
import { HeatBadge } from '../components/game/HeatBadge';
import { ProgressBar } from '../components/game/ProgressBar';
import type { HeatOverview, HeatReductionOption } from '../types';

interface HeatPolicePageProps {
  onChanged: () => void;
}

type HeatTab = 'overview' | 'boss' | 'crew' | 'investigations' | 'reduction' | 'districts' | 'logs' | 'events';

export function HeatPolicePage({ onChanged }: HeatPolicePageProps) {
  const [overview, setOverview] = useState<HeatOverview | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [busyCode, setBusyCode] = useState('');
  const [bossFirstName, setBossFirstName] = useState('');
  const [bossLastName, setBossLastName] = useState('');
  const [activeTab, setActiveTab] = useState<HeatTab>('overview');

  useEffect(() => {
    void load();
  }, []);

  async function load(): Promise<void> {
    setError('');

    try {
      const nextOverview = await api<HeatOverview>('/heat');
      setOverview(nextOverview);
      setBossFirstName(nextOverview.boss.first_name || '');
      setBossLastName(nextOverview.boss.last_name || '');
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  async function renameBoss(): Promise<void> {
    setBusyCode('rename-boss');
    setMessage('');
    setError('');

    try {
      const response = await api<{ boss: HeatOverview['boss']; message: string }>('/boss/rename', {
        method: 'POST',
        body: JSON.stringify({ first_name: bossFirstName, last_name: bossLastName }),
      });

      setOverview((current) => current ? { ...current, boss: response.boss } : current);
      setMessage(response.message);
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setBusyCode('');
    }
  }

  async function useReduction(option: HeatReductionOption): Promise<void> {
    setBusyCode(option.code);
    setMessage('');
    setError('');

    try {
      const response = await api<{ message: string; overview: HeatOverview }>('/heat/reduce', {
        method: 'POST',
        body: JSON.stringify({ code: option.code }),
      });
      setOverview(response.overview);
      setMessage(response.message);
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setBusyCode('');
    }
  }

  async function processDay(): Promise<void> {
    setBusyCode('process-day');
    setMessage('');
    setError('');

    try {
      const response = await api<{ message: string }>('/heat/process-day', { method: 'POST' });
      setMessage(response.message);
      await load();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setBusyCode('');
    }
  }

  if (!overview) {
    return (
      <section className="page-section heat-page">
        <GameHeader eyebrow="v0.7.2 heat command" title="Heat & Police" description="Loading boss, gang, crew, district heat, and active investigations…" />
        {error && <Notice message={error} kind="error" />}
      </section>
    );
  }

  return (
    <section className="page-section heat-page">
      <GameHeader
        eyebrow="heat command"
        title="Heat & Police Pressure"
        description="Use the subtabs to manage boss heat, crew heat, investigations, reduction actions, district pressure, and logs without one giant wall of panels."
      />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <div className="page-tabs heat-tabs" role="tablist" aria-label="Heat sections">
        {([
          ['overview', 'Overview'],
          ['boss', 'Boss'],
          ['crew', 'Crew'],
          ['investigations', 'Investigations'],
          ['reduction', 'Reduce Heat'],
          ['districts', 'Districts'],
          ['logs', 'Heat Logs'],
          ['events', 'Police Events'],
        ] as Array<[HeatTab, string]>).map(([tab, label]) => (
          <button key={tab} className={activeTab === tab ? 'active' : ''} onClick={() => setActiveTab(tab)}>{label}</button>
        ))}
      </div>

      {activeTab === 'overview' && <OverviewTab overview={overview} onProcessDay={processDay} busyCode={busyCode} />}
      {activeTab === 'boss' && (
        <BossTab
          overview={overview}
          bossFirstName={bossFirstName}
          bossLastName={bossLastName}
          busyCode={busyCode}
          onFirstName={setBossFirstName}
          onLastName={setBossLastName}
          onRename={renameBoss}
        />
      )}
      {activeTab === 'crew' && <CrewHeatTab overview={overview} />}
      {activeTab === 'investigations' && <InvestigationsTab overview={overview} />}
      {activeTab === 'reduction' && <ReductionTab overview={overview} busyCode={busyCode} onUse={useReduction} />}
      {activeTab === 'districts' && <DistrictsTab overview={overview} />}
      {activeTab === 'logs' && <HeatLogsTab overview={overview} />}
      {activeTab === 'events' && <PoliceEventsTab overview={overview} />}
    </section>
  );
}

function OverviewTab({ overview, onProcessDay, busyCode }: { overview: HeatOverview; onProcessDay: () => void; busyCode: string }) {
  return (
    <div className="heat-tab-workspace">
      <section className="heat-overview-cards">
        <article className="card section-card gang-heat-card">
          <p className="eyebrow">Gang heat</p>
          <h2>{overview.gang.level.label}</h2>
          <p>{overview.gang.level.description}</p>
          <ProgressBar label="Gang heat" value={overview.gang.heat} max={100} tone="heat" />
          <p className="heat-forecast">{overview.gang.forecast}</p>
          <dl className="details-grid compact-details-grid">
            <div><dt>Display heat</dt><dd><HeatBadge value={overview.display_heat} /></dd></div>
            <div><dt>Highest crew heat</dt><dd>{overview.highest_crew_heat}</dd></div>
            <div><dt>Active investigations</dt><dd>{overview.investigations.length}</dd></div>
            <div><dt>Idle streak</dt><dd>{overview.gang.idle_days_count} day(s)</dd></div>
          </dl>
          <button className="btn" disabled={busyCode === 'process-day'} onClick={onProcessDay}>Process quiet day</button>
        </article>

        <article className="card section-card">
          <p className="eyebrow">Recommended focus</p>
          <h2>Next best actions</h2>
          {overview.warnings.length === 0 ? <p className="muted">No urgent warnings. Keep crew quiet and avoid high-police districts.</p> : (
            <ul className="clean-list">{overview.warnings.slice(0, 5).map((warning) => <li key={warning}>{warning}</li>)}</ul>
          )}
        </article>
      </section>
    </div>
  );
}

function BossTab({ overview, bossFirstName, bossLastName, busyCode, onFirstName, onLastName, onRename }: {
  overview: HeatOverview;
  bossFirstName: string;
  bossLastName: string;
  busyCode: string;
  onFirstName: (value: string) => void;
  onLastName: (value: string) => void;
  onRename: () => void;
}) {
  return (
    <section className="card section-card boss-status-card heat-tab-workspace">
      <p className="eyebrow">Boss character</p>
      <h2>{overview.boss.name}</h2>
      <p className="muted">Rank: {overview.boss.rank} · Status: {overview.boss.status}</p>
      <ProgressBar label="Boss health" value={overview.boss.health} max={overview.boss.max_health} tone="money" />
      <ProgressBar label="Boss personal heat" value={overview.boss.personal_heat} max={100} tone="heat" />
      <dl className="details-grid compact-details-grid">
        <div><dt>Level</dt><dd>{overview.boss.level}</dd></div>
        <div><dt>XP</dt><dd>{overview.boss.experience}</dd></div>
        <div><dt>Alive</dt><dd>{overview.boss.alive ? 'Yes' : 'Dead'}</dd></div>
        <div><dt>Injury</dt><dd>{overview.boss.injury_status || 'None'}</dd></div>
      </dl>
      {overview.boss.can_rename_initial_name && (
        <div className="boss-rename-panel">
          <p className="muted">This account still uses the original default boss name. You can set it once.</p>
          <div className="admin-form-grid">
            <label>Boss first name<input value={bossFirstName} onChange={(event) => onFirstName(event.target.value)} /></label>
            <label>Boss surname<input value={bossLastName} onChange={(event) => onLastName(event.target.value)} /></label>
          </div>
          <button className="btn" disabled={busyCode !== ''} onClick={onRename}>{busyCode === 'rename-boss' ? 'Saving…' : 'Set boss name'}</button>
        </div>
      )}
    </section>
  );
}

function CrewHeatTab({ overview }: { overview: HeatOverview }) {
  return (
    <section className="card section-card heat-tab-workspace">
      <h2>Crew personal heat</h2>
      {overview.crew.length === 0 && <EmptyState title="No crew heat" message="Recruit crew to see personal heat and spillover risk here." />}
      <div className="heat-list clean-heat-list">
        {overview.crew.map((member) => (
          <article key={member.id} className="heat-row-card">
            <div>
              <strong>{member.first_name} {member.nickname ? `“${member.nickname}”` : ''} {member.last_name}</strong>
              <p className="muted">{member.status} · {member.heat_level.label} · {member.recommendation}</p>
            </div>
            <HeatBadge value={member.personal_heat} />
          </article>
        ))}
      </div>
    </section>
  );
}

function InvestigationsTab({ overview }: { overview: HeatOverview }) {
  return (
    <section className="card section-card heat-tab-workspace">
      <h2>Active investigations</h2>
      {overview.investigations.length === 0 && <EmptyState title="No active investigations" message="Stay quiet to keep it this way." />}
      <div className="heat-list clean-heat-list">
        {overview.investigations.map((investigation) => (
          <article key={investigation.id} className="heat-row-card investigation-card">
            <div>
              <strong>{investigation.status.replace(/_/g, ' ')}</strong>
              <p className="muted">Target: {investigation.target_type} #{investigation.target_id || 'gang'} · Officer: {investigation.lead_officer || 'Unknown'}</p>
            </div>
            <dl className="mini-stat-pair">
              <div><dt>Suspicion</dt><dd>{investigation.suspicion}</dd></div>
              <div><dt>Evidence</dt><dd>{investigation.evidence_strength}</dd></div>
            </dl>
          </article>
        ))}
      </div>
    </section>
  );
}

function ReductionTab({ overview, busyCode, onUse }: { overview: HeatOverview; busyCode: string; onUse: (option: HeatReductionOption) => void }) {
  return (
    <section className="card section-card heat-tab-workspace">
      <div className="card-heading"><div><p className="eyebrow">Reduction actions</p><h2>Lower heat and investigation pressure</h2></div></div>
      <div className="heat-action-grid">
        {overview.reduction_options.map((option) => (
          <article key={option.code} className={`heat-action-card ${option.can_use ? '' : 'locked'}`}>
            <h3>{option.name}</h3>
            <p>{option.description}</p>
            <dl className="details-grid compact-details-grid">
              <div><dt>Heat</dt><dd>-{option.heat_reduction_min} to -{option.heat_reduction_max}</dd></div>
              <div><dt>Investigation</dt><dd>-{option.investigation_reduction_min} to -{option.investigation_reduction_max}</dd></div>
              <div><dt>Cash</dt><dd>${option.cash_cost}</dd></div>
              <div><dt>Energy</dt><dd>{option.energy_cost}</dd></div>
              <div><dt>Risk</dt><dd>{option.risk_percent}%</dd></div>
            </dl>
            {option.locked_reasons.length > 0 && <p className="muted warning-text">Locked: {option.locked_reasons.join(' ')}</p>}
            <button className="btn primary full-width" disabled={!option.can_use || busyCode !== ''} onClick={() => onUse(option)}>{busyCode === option.code ? 'Working…' : 'Use action'}</button>
          </article>
        ))}
      </div>
    </section>
  );
}

function DistrictsTab({ overview }: { overview: HeatOverview }) {
  return (
    <section className="card section-card heat-tab-workspace">
      <h2>District / Map Heat</h2>
      <div className="heat-list clean-heat-list">
        {overview.districts.map((district) => (
          <article key={district.id} className="heat-row-card">
            <div><strong>{district.name}</strong><p className="muted">Police presence {district.police_presence} · {district.heat_level.label}</p></div>
            <HeatBadge value={district.district_heat} />
          </article>
        ))}
      </div>
    </section>
  );
}

function HeatLogsTab({ overview }: { overview: HeatOverview }) {
  return (
    <section className="card section-card heat-tab-workspace">
      <h2>Recent heat logs</h2>
      <p className="muted">Latest heat entries are shown compactly here. Full paginated heat logs remain capped by the backend.</p>
      <div className="compact-log-list">
        {overview.recent_logs.slice(0, 30).map((log) => (
          <article key={log.id} className="compact-log-row">
            <span className="log-delta">{log.amount > 0 ? '+' : ''}{log.amount}</span>
            <div><strong>{log.category}</strong><p className="muted">{log.description}</p></div>
            <small>{log.target_type}</small>
          </article>
        ))}
      </div>
    </section>
  );
}

function PoliceEventsTab({ overview }: { overview: HeatOverview }) {
  return (
    <section className="card section-card heat-tab-workspace">
      <h2>Police events</h2>
      {overview.recent_logs.length === 0 ? <EmptyState title="No recent events" message="Travel, crimes, dirty jobs, and heat reductions can create police-pressure events." /> : (
        <div className="compact-log-list">
          {overview.recent_logs.filter((log) => String(log.category || '').includes('police') || String(log.description || '').toLowerCase().includes('police')).slice(0, 30).map((log) => (
            <article key={log.id} className="compact-log-row">
              <span className="log-delta">{log.amount > 0 ? '+' : ''}{log.amount}</span>
              <div><strong>{log.category}</strong><p className="muted">{log.description}</p></div>
              <small>{log.target_type}</small>
            </article>
          ))}
        </div>
      )}
    </section>
  );
}
