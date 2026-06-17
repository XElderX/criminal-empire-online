import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import type { CrewHistoryEntry, CrewMember } from '../types';

interface CrewPageProps {
  onChanged: () => void;
}

export function CrewPage({ onChanged }: CrewPageProps) {
  const [members, setMembers] = useState<CrewMember[]>([]);
  const [selectedMember, setSelectedMember] = useState<CrewMember | null>(null);
  const [history, setHistory] = useState<CrewHistoryEntry[]>([]);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loadingId, setLoadingId] = useState<number | null>(null);

  async function load(): Promise<void> {
    try {
      const response = await api<{ data: CrewMember[] }>('/my-gang');
      setMembers(response.data);

      if (selectedMember) {
        const fresh = response.data.find((member) => member.id === selectedMember.id);
        setSelectedMember(fresh || null);
      }
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  async function openProfile(member: CrewMember): Promise<void> {
    setError('');

    try {
      const [memberResponse, historyResponse] = await Promise.all([
        api<{ member: CrewMember }>(`/my-gang/${member.id}`),
        api<{ data: CrewHistoryEntry[] }>(`/my-gang/${member.id}/history`),
      ]);

      setSelectedMember(memberResponse.member);
      setHistory(historyResponse.data);
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  async function payOverdue(member: CrewMember): Promise<void> {
    setLoadingId(member.id);
    setMessage('');
    setError('');

    try {
      const response = await api<{ message: string; amount: number }>(
        `/my-gang/${member.id}/pay-overdue`,
        { method: 'POST' },
      );

      setMessage(`${response.message} $${response.amount} was transferred.`);
      await load();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoadingId(null);
    }
  }

  async function dismiss(member: CrewMember): Promise<void> {
    const reason = window.prompt(
      `Why are you dismissing ${displayName(member)}?`,
      'No longer needed in the active crew.',
    );

    if (reason === null) {
      return;
    }

    const confirmed = window.confirm(
      'Dismiss this member? Their identity and history will remain in the NPC world, but recruitment fees will not be refunded.',
    );

    if (!confirmed) {
      return;
    }

    setLoadingId(member.id);
    setMessage('');
    setError('');

    try {
      const response = await api<{ message: string }>(
        `/my-gang/${member.id}/dismiss`,
        {
          method: 'POST',
          body: JSON.stringify({ reason }),
        },
      );

      setMessage(response.message);
      setSelectedMember(null);
      setHistory([]);
      await load();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoadingId(null);
    }
  }

  return (
    <section className="page-section">
      <header className="page-header">
        <div>
          <p className="eyebrow">Persistent NPC characters</p>
          <h1>My Crew</h1>
          <p className="muted">
            Manage health, morale, loyalty, wages, loadouts, and personal
            histories. Busy, injured, recovering, and arrested members cannot
            take every assignment.
          </p>
        </div>
      </header>

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      {members.length === 0 && (
        <div className="card empty-state">
          You are still working alone. Complete starter jobs and visit the
          recruitment market when you can afford your first street recruit.
        </div>
      )}

      <div className="card-grid">
        {members.map((member) => (
          <article className="card profile-card" key={member.id}>
            <div className="card-heading">
              <div>
                <p className="eyebrow">
                  {member.occupation} · age {member.age}
                </p>
                <h2>{displayName(member)}</h2>
              </div>
              <span className={`status-badge status-${member.status}`}>
                {member.status}
              </span>
            </div>

            <p>{member.biography}</p>
            <p className="muted">
              From {member.territory_name} · {member.background}
            </p>

            <div className="meter-grid">
              <Meter label="Health" value={member.health} maximum={member.max_health} />
              <Meter label="Morale" value={member.morale} maximum={100} />
              <Meter label="Loyalty" value={member.loyalty} maximum={100} />
            </div>

            <dl className="details-grid">
              <div>
                <dt>Personal cash</dt>
                <dd>${member.personal_cash}</dd>
              </div>
              <div>
                <dt>Weekly salary</dt>
                <dd>${member.salary_weekly}</dd>
              </div>
              <div>
                <dt>Unpaid wages</dt>
                <dd className={member.unpaid_salary > 0 ? 'danger' : ''}>
                  ${member.unpaid_salary}
                </dd>
              </div>
              <div>
                <dt>Level / XP</dt>
                <dd>{member.level} / {member.experience}</dd>
              </div>
            </dl>

            <div className="tag-row">
              {member.traits.map((trait) => (
                <span
                  className={`tag ${trait.polarity === 'negative' ? 'tag-negative' : ''}`}
                  title={trait.description}
                  key={trait.code}
                >
                  {trait.name}
                </span>
              ))}
            </div>

            <div className="loadout-summary">
              <strong>Loadout</strong>
              {member.equipment.length === 0 ? (
                <span className="muted">No equipment assigned</span>
              ) : (
                member.equipment.map((equipment) => (
                  <span key={equipment.id}>
                    {equipment.equipment_slot}: {equipment.name} ({equipment.durability}%)
                  </span>
                ))
              )}
            </div>

            <div className="button-row">
              <button className="btn" onClick={() => openProfile(member)}>
                Profile and history
              </button>
              {member.unpaid_salary > 0 && (
                <button
                  className="btn primary"
                  disabled={loadingId === member.id}
                  onClick={() => payOverdue(member)}
                >
                  Pay overdue
                </button>
              )}
              <button
                className="btn danger-button"
                disabled={loadingId === member.id || member.status === 'busy'}
                onClick={() => dismiss(member)}
              >
                Dismiss
              </button>
            </div>
          </article>
        ))}
      </div>

      {selectedMember && (
        <div className="modal-backdrop" role="presentation">
          <section className="modal card" role="dialog" aria-modal="true">
            <header className="modal-header">
              <div>
                <p className="eyebrow">Crew dossier</p>
                <h2>{displayName(selectedMember)}</h2>
              </div>
              <button
                className="icon-button"
                onClick={() => setSelectedMember(null)}
                aria-label="Close profile"
              >
                ×
              </button>
            </header>

            <h3>Operational stats</h3>
            <CrewStats member={selectedMember} />

            <h3>Career record</h3>
            <dl className="details-grid">
              <div><dt>Jobs completed</dt><dd>{selectedMember.jobs_completed}</dd></div>
              <div><dt>Jobs failed</dt><dd>{selectedMember.jobs_failed}</dd></div>
              <div><dt>Arrests</dt><dd>{selectedMember.arrests}</dd></div>
              <div><dt>Injuries</dt><dd>{selectedMember.injuries}</dd></div>
              <div><dt>Total earnings</dt><dd>${selectedMember.total_earnings}</dd></div>
            </dl>

            <h3>Timeline</h3>
            <div className="timeline">
              {history.length === 0 && (
                <p className="muted">No recorded history yet.</p>
              )}
              {history.map((entry) => (
                <article key={entry.id}>
                  <span>{new Date(entry.created_at).toLocaleString()}</span>
                  <strong>{entry.title}</strong>
                  <p>{entry.description}</p>
                </article>
              ))}
            </div>
          </section>
        </div>
      )}
    </section>
  );
}

function Meter({
  label,
  value,
  maximum,
}: {
  label: string;
  value: number;
  maximum: number;
}) {
  const percentage = Math.max(0, Math.min(100, (value / maximum) * 100));

  return (
    <div className="meter">
      <div>
        <span>{label}</span>
        <strong>{value}/{maximum}</strong>
      </div>
      <div className="progress-track">
        <span style={{ width: `${percentage}%` }} />
      </div>
    </div>
  );
}

function CrewStats({ member }: { member: CrewMember }) {
  const stats = [
    ['Strength', member.strength],
    ['Shooting', member.shooting],
    ['Driving', member.driving],
    ['Intelligence', member.intelligence],
    ['Stealth', member.stealth],
    ['Intimidation', member.intimidation],
    ['Discipline', member.discipline],
    ['Street knowledge', member.street_knowledge],
    ['Endurance', member.endurance],
  ];

  return (
    <div className="compact-stats">
      {stats.map(([name, value]) => (
        <div key={name}>
          <span>{name}</span>
          <strong>{value}</strong>
        </div>
      ))}
    </div>
  );
}

function displayName(member: CrewMember): string {
  const nickname = member.nickname ? ` “${member.nickname}”` : '';
  return `${member.first_name}${nickname} ${member.last_name}`;
}
