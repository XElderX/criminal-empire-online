import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { RecruitmentCard } from '../features/crew/components/RecruitmentCard';
import { displayCrewName } from '../features/crew/utils/crewPresentation';
import type { RecruitmentCandidate } from '../types';

interface RecruitmentPageProps {
  onChanged: () => void;
}

export function RecruitmentPage({ onChanged }: RecruitmentPageProps) {
  const [candidates, setCandidates] = useState<RecruitmentCandidate[]>([]);
  const [roleFilter, setRoleFilter] = useState('all');
  const [ageFilter, setAgeFilter] = useState('all');
  const [sortMode, setSortMode] = useState('fee');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loadingId, setLoadingId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);

  async function load(): Promise<void> {
    setLoading(true);
    setError('');

    try {
      const response = await api<{ data: RecruitmentCandidate[] }>(
        '/recruitment',
      );
      setCandidates(response.data);
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  const visibleCandidates = useMemo(() => {
    const filtered = candidates.filter((candidate) => {
      const roleMatches = roleFilter === 'all'
        || candidate.role_code === roleFilter;
      const ageMatches = ageFilter === 'all'
        || candidate.life_stage.key === ageFilter;

      return roleMatches && ageMatches;
    });

    return filtered.sort((left, right) => {
      if (sortMode === 'salary') {
        return left.salary_weekly - right.salary_weekly;
      }

      if (sortMode === 'level') {
        return right.level - left.level;
      }

      if (sortMode === 'age') {
        return left.age - right.age;
      }

      return left.recruitment_fee - right.recruitment_fee;
    });
  }, [candidates, roleFilter, ageFilter, sortMode]);

  const roleOptions = useMemo(() => (
    Array.from(new Map(
      candidates.map((candidate) => [candidate.role_code, candidate.role.name]),
    ).entries())
  ), [candidates]);

  async function hire(candidate: RecruitmentCandidate): Promise<void> {
    const confirmed = window.confirm(
      `Hire ${displayCrewName(candidate)} for $${candidate.recruitment_fee}? `
        + 'Recruitment fees are not refundable.',
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
    <section className="page-section recruitment-page">
      <header className="page-header">
        <div>
          <p className="eyebrow">NPC recruitment market</p>
          <h1>Recruitment</h1>
          <p className="muted">
            Candidates are persistent people. Their name, gender-compatible
            portrait identity, age, traits, history, and finances remain stable
            after hiring, dismissal, and return to the NPC world.
          </p>
        </div>
      </header>

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <section className="crew-toolbar card">
        <div className="crew-filter-grid recruitment-filter-grid">
          <label>
            Role tendency
            <select value={roleFilter} onChange={(event) => setRoleFilter(event.target.value)}>
              <option value="all">All roles</option>
              {roleOptions.map(([key, label]) => (
                <option value={key} key={key}>{label}</option>
              ))}
            </select>
          </label>

          <label>
            Age stage
            <select value={ageFilter} onChange={(event) => setAgeFilter(event.target.value)}>
              <option value="all">All life stages</option>
              <option value="very_young">Very Young</option>
              <option value="young">Young</option>
              <option value="adult">Adult</option>
              <option value="mature">Mature</option>
              <option value="elder">Elder</option>
            </select>
          </label>

          <label>
            Sort by
            <select value={sortMode} onChange={(event) => setSortMode(event.target.value)}>
              <option value="fee">Lowest recruitment fee</option>
              <option value="salary">Lowest weekly salary</option>
              <option value="level">Highest level</option>
              <option value="age">Youngest</option>
            </select>
          </label>
        </div>
      </section>

      {loading && (
        <div className="recruitment-grid" aria-label="Loading recruitment candidates">
          {Array.from({ length: 3 }).map((_, index) => (
            <div className="crew-card-skeleton" key={index} />
          ))}
        </div>
      )}

      {!loading && visibleCandidates.length > 0 && (
        <div className="recruitment-grid">
          {visibleCandidates.map((candidate) => (
            <RecruitmentCard
              candidate={candidate}
              busy={loadingId === candidate.id}
              onHire={hire}
              key={candidate.id}
            />
          ))}
        </div>
      )}

      {!loading && candidates.length === 0 && (
        <div className="card crew-empty-state">
          <div className="crew-empty-portrait" aria-hidden="true">?</div>
          <div>
            <h2>No candidates are available</h2>
            <p>
              The NPC recruitment pool refreshes through world processing.
              Existing characters keep their portraits when they return.
            </p>
          </div>
        </div>
      )}

      {!loading && candidates.length > 0 && visibleCandidates.length === 0 && (
        <div className="card empty-state">
          No recruitment candidates match the selected filters.
        </div>
      )}
    </section>
  );
}
