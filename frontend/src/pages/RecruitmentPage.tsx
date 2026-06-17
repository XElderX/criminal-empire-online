import { useEffect, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import type { RecruitmentCandidate } from '../types';

interface RecruitmentPageProps {
  onChanged: () => void;
}

export function RecruitmentPage({ onChanged }: RecruitmentPageProps) {
  const [candidates, setCandidates] = useState<RecruitmentCandidate[]>([]);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loadingId, setLoadingId] = useState<number | null>(null);

  async function load(): Promise<void> {
    try {
      const response = await api<{ data: RecruitmentCandidate[] }>('/recruitment');
      setCandidates(response.data);
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  async function hire(candidate: RecruitmentCandidate): Promise<void> {
    const confirmed = window.confirm(
      `Hire ${displayName(candidate)} for $${candidate.recruitment_fee}? Recruitment fees are not refundable.`,
    );

    if (!confirmed) {
      return;
    }

    setLoadingId(candidate.id);
    setMessage('');
    setError('');

    try {
      const response = await api<{ message: string }>(
        `/recruitment/${candidate.id}/hire`,
        { method: 'POST' },
      );

      setMessage(response.message);
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
          <p className="eyebrow">NPC recruitment market</p>
          <h1>Recruitment</h1>
          <p className="muted">
            Street recruits are persistent characters with their own history,
            money, traits, wages, and ambitions.
          </p>
        </div>
      </header>

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <div className="card-grid">
        {candidates.map((candidate) => (
          <article className="card profile-card" key={candidate.id}>
            <div className="card-heading">
              <div>
                <p className="eyebrow">
                  {candidate.occupation} · age {candidate.age}
                </p>
                <h2>{displayName(candidate)}</h2>
              </div>
              <span className="status-badge">{candidate.territory_name}</span>
            </div>

            <p>{candidate.biography}</p>
            {candidate.background && (
              <p className="muted">Background: {candidate.background}</p>
            )}

            <dl className="details-grid">
              <div>
                <dt>Recruitment fee</dt>
                <dd>${candidate.recruitment_fee}</dd>
              </div>
              <div>
                <dt>Weekly salary</dt>
                <dd>${candidate.salary_weekly}</dd>
              </div>
              <div>
                <dt>Personal cash</dt>
                <dd>${candidate.personal_cash}</dd>
              </div>
              <div>
                <dt>Morale / loyalty</dt>
                <dd>{candidate.morale} / {candidate.loyalty}</dd>
              </div>
            </dl>

            <StatTable candidate={candidate} />

            <div className="tag-row">
              {candidate.traits.map((trait) => (
                <span
                  className={`tag ${trait.polarity === 'negative' ? 'tag-negative' : ''}`}
                  title={trait.description}
                  key={trait.code}
                >
                  {trait.name}
                </span>
              ))}
            </div>

            <button
              className="btn primary full-width"
              disabled={!candidate.can_hire || loadingId === candidate.id}
              onClick={() => hire(candidate)}
            >
              {loadingId === candidate.id ? 'Hiring…' : 'Hire recruit'}
            </button>
          </article>
        ))}
      </div>

      {candidates.length === 0 && (
        <div className="card empty-state">
          No candidates are available. The NPC recruitment pool refreshes
          through world processing.
        </div>
      )}
    </section>
  );
}

function StatTable({ candidate }: { candidate: RecruitmentCandidate }) {
  const stats = [
    ['Strength', candidate.strength],
    ['Shooting', candidate.shooting],
    ['Driving', candidate.driving],
    ['Intelligence', candidate.intelligence],
    ['Stealth', candidate.stealth],
    ['Intimidation', candidate.intimidation],
    ['Discipline', candidate.discipline],
    ['Street knowledge', candidate.street_knowledge],
    ['Endurance', candidate.endurance],
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

function displayName(candidate: RecruitmentCandidate): string {
  const nickname = candidate.nickname ? ` “${candidate.nickname}”` : '';
  return `${candidate.first_name}${nickname} ${candidate.last_name}`;
}
