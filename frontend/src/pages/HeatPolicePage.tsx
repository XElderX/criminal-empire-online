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

export function HeatPolicePage({ onChanged }: HeatPolicePageProps) {
  const [overview, setOverview] = useState<HeatOverview | null>(null);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [busyCode, setBusyCode] = useState('');
  const [bossFirstName, setBossFirstName] = useState('');
  const [bossLastName, setBossLastName] = useState('');

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
      const response = await api<{ boss: HeatOverview['boss']; message: string }>(
        '/boss/rename',
        {
          method: 'POST',
          body: JSON.stringify({
            first_name: bossFirstName,
            last_name: bossLastName,
          }),
        },
      );

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
      const response = await api<{ message: string; overview: HeatOverview }>(
        '/heat/reduce',
        {
          method: 'POST',
          body: JSON.stringify({ code: option.code }),
        },
      );
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
        <GameHeader
          eyebrow="v0.5 police pressure"
          title="Heat & Police"
          description="Loading boss, gang, crew, district heat, and active investigations…"
        />
        {error && <Notice message={error} kind="error" />}
      </section>
    );
  }

  return (
    <section className="page-section heat-page">
      <GameHeader
        eyebrow="v0.5 heat model"
        title="Heat & Police Pressure"
        description="Heat now belongs to the boss, crew, gang, districts, and investigations. High-heat people can spill pressure onto the wider organization."
      />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <section className="heat-hero-grid">
        <article className="card section-card boss-status-card">
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
                <label>
                  Boss first name
                  <input value={bossFirstName} onChange={(event) => setBossFirstName(event.target.value)} />
                </label>
                <label>
                  Boss surname
                  <input value={bossLastName} onChange={(event) => setBossLastName(event.target.value)} />
                </label>
              </div>
              <button className="btn" disabled={busyCode !== ''} onClick={renameBoss}>
                {busyCode === 'rename-boss' ? 'Saving…' : 'Set boss name'}
              </button>
            </div>
          )}
          {overview.boss.skills && (
            <dl className="details-grid compact-details-grid boss-skill-grid">
              <div><dt>Shooting</dt><dd>{overview.boss.skills.shooting}</dd></div>
              <div><dt>Driving</dt><dd>{overview.boss.skills.driving}</dd></div>
              <div><dt>Stealth</dt><dd>{overview.boss.skills.stealth}</dd></div>
              <div><dt>Intimidation</dt><dd>{overview.boss.skills.intimidation}</dd></div>
              <div><dt>Discipline</dt><dd>{overview.boss.skills.discipline}</dd></div>
              <div><dt>Street Knowledge</dt><dd>{overview.boss.skills.street_knowledge}</dd></div>
              <div><dt>Endurance</dt><dd>{overview.boss.skills.endurance}</dd></div>
            </dl>
          )}
        </article>

        <article className="card section-card gang-heat-card">
          <p className="eyebrow">Gang heat</p>
          <h2>{overview.gang.level.label}</h2>
          <p>{overview.gang.level.description}</p>
          <ProgressBar label="Gang heat" value={overview.gang.heat} max={100} tone="heat" />
          <p className="heat-forecast">{overview.gang.forecast}</p>
          <dl className="details-grid compact-details-grid">
            <div><dt>Display heat</dt><dd><HeatBadge value={overview.display_heat} /></dd></div>
            <div><dt>Highest crew heat</dt><dd>{overview.highest_crew_heat}</dd></div>
            <div><dt>Idle streak</dt><dd>{overview.gang.idle_days_count} day(s)</dd></div>
          </dl>
          <button className="btn" disabled={busyCode === 'process-day'} onClick={processDay}>
            Process quiet day
          </button>
        </article>
      </section>

      {overview.warnings.length > 0 && (
        <section className="card section-card heat-warning-panel">
          <p className="eyebrow">Warnings</p>
          <ul>
            {overview.warnings.map((warning) => <li key={warning}>{warning}</li>)}
          </ul>
        </section>
      )}

      <section className="card section-card">
        <div className="card-heading">
          <div>
            <p className="eyebrow">Reduction actions</p>
            <h2>Lower heat and investigation pressure</h2>
          </div>
        </div>
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
              {option.locked_reasons.length > 0 && (
                <p className="muted warning-text">Locked: {option.locked_reasons.join(' ')}</p>
              )}
              <button className="btn primary full-width" disabled={!option.can_use || busyCode !== ''} onClick={() => useReduction(option)}>
                {busyCode === option.code ? 'Working…' : 'Use action'}
              </button>
            </article>
          ))}
        </div>
      </section>

      <div className="content-grid two-columns">
        <section className="card section-card">
          <h2>Crew personal heat</h2>
          {overview.crew.length === 0 && <EmptyState title="No crew heat" message="Recruit crew to see personal heat and spillover risk here." />}
          <div className="heat-list">
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

        <section className="card section-card">
          <h2>Active investigations</h2>
          {overview.investigations.length === 0 && <EmptyState title="No active investigations" message="Stay quiet to keep it this way." />}
          <div className="heat-list">
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
      </div>

      <div className="content-grid two-columns">
        <section className="card section-card">
          <h2>District heat</h2>
          <div className="heat-list">
            {overview.districts.map((district) => (
              <article key={district.id} className="heat-row-card">
                <div>
                  <strong>{district.name}</strong>
                  <p className="muted">Police presence {district.police_presence} · {district.heat_level.label}</p>
                </div>
                <HeatBadge value={district.district_heat} />
              </article>
            ))}
          </div>
        </section>

        <section className="card section-card">
          <h2>Recent heat logs</h2>
          <div className="timeline compact-timeline">
            {overview.recent_logs.map((log) => (
              <article key={log.id}>
                <span>{log.target_type} · {log.category}</span>
                <strong>{log.amount > 0 ? '+' : ''}{log.amount} heat</strong>
                <p className="muted">{log.description}</p>
              </article>
            ))}
          </div>
        </section>
      </div>
    </section>
  );
}
