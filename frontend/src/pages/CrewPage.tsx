import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { GameHeader } from '../components/game/GameHeader';
import { CrewMemberCard } from '../components/game/CrewMemberCard';
import { EmptyState } from '../components/game/EmptyState';
import { LoadingState } from '../components/game/LoadingState';
import { CrewProfile } from '../features/crew/components/CrewProfile';
import { formatMoney } from '../features/crew/utils/crewPresentation';
import type { CrewHistoryEntry, CrewMember } from '../types';

interface CrewPageProps {
  onChanged: () => void;
}

interface CrewResponse {
  data: CrewMember[];
  meta?: {
    maximum_capacity: number;
    weekly_salary_total: number;
  };
}

type SortKey = 'name' | 'age' | 'level' | 'loyalty' | 'morale' | 'salary';

export function CrewPage({ onChanged }: CrewPageProps) {
  const [members, setMembers] = useState<CrewMember[]>([]);
  const [maximumCapacity, setMaximumCapacity] = useState(12);
  const [selectedMember, setSelectedMember] = useState<CrewMember | null>(null);
  const [history, setHistory] = useState<CrewHistoryEntry[]>([]);
  const [statusFilter, setStatusFilter] = useState('all');
  const [roleFilter, setRoleFilter] = useState('all');
  const [ageFilter, setAgeFilter] = useState('all');
  const [sortKey, setSortKey] = useState<SortKey>('name');
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loadingId, setLoadingId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);

  async function load(): Promise<void> {
    setLoading(true);
    setError('');

    try {
      const response = await api<CrewResponse>('/my-gang');
      setMembers(response.data);
      setMaximumCapacity(response.meta?.maximum_capacity ?? 12);

      if (selectedMember) {
        const freshMember = response.data.find(
          (member) => member.id === selectedMember.id,
        );
        setSelectedMember(freshMember ?? null);
      }
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  const filteredMembers = useMemo(() => {
    const filtered = members.filter((member) => {
      const statusMatches = statusFilter === 'all'
        || member.status === statusFilter;
      const roleMatches = roleFilter === 'all'
        || member.role_code === roleFilter;
      const ageMatches = ageFilter === 'all'
        || member.life_stage.key === ageFilter;

      return statusMatches && roleMatches && ageMatches;
    });

    return filtered.sort((left, right) => {
      switch (sortKey) {
        case 'age':
          return left.age - right.age;
        case 'level':
          return right.level - left.level;
        case 'loyalty':
          return right.loyalty - left.loyalty;
        case 'morale':
          return right.morale - left.morale;
        case 'salary':
          return right.salary_weekly - left.salary_weekly;
        case 'name':
        default:
          return `${left.first_name} ${left.last_name}`.localeCompare(
            `${right.first_name} ${right.last_name}`,
          );
      }
    });
  }, [members, statusFilter, roleFilter, ageFilter, sortKey]);

  const summary = useMemo(() => ({
    active: members.filter((member) => member.status === 'active').length,
    busy: members.filter((member) => member.status === 'busy').length,
    injured: members.filter((member) => member.status === 'injured').length,
    arrested: members.filter((member) => member.status === 'arrested').length,
    available: members.filter((member) => member.status === 'active').length,
    weeklySalary: members.reduce(
      (total, member) => total + Number(member.salary_weekly),
      0,
    ),
  }), [members]);

  const roleOptions = useMemo(() => (
    Array.from(new Map(
      members.map((member) => [member.role_code, member.role.name]),
    ).entries())
  ), [members]);

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

      setMessage(
        `${response.message} ${formatMoney(response.amount)} was transferred.`,
      );
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
      `Why are you dismissing ${member.first_name} ${member.last_name}?`,
      'No longer needed in the active crew.',
    );

    if (reason === null) {
      return;
    }

    const confirmed = window.confirm(
      'Dismiss this member? Their portrait identity and history will remain '
        + 'in the NPC world, but recruitment fees will not be refunded.',
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
    <section className="page-section crew-page">
      <GameHeader
        eyebrow="Persistent NPC dossiers"
        title="Crew"
        description="Every member keeps the same identity, gender-safe portrait path, biography, equipment, and history while moving through the criminal world."
      />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <div className="crew-summary-grid">
        <Summary label="Crew capacity" value={`${members.length}/${maximumCapacity}`} />
        <Summary label="Weekly salaries" value={formatMoney(summary.weeklySalary)} />
        <Summary label="Active" value={summary.active} />
        <Summary label="Busy" value={summary.busy} />
        <Summary label="Injured" value={summary.injured} />
        <Summary label="Arrested" value={summary.arrested} />
      </div>

      <section className="crew-toolbar card">
        <div className="crew-filter-grid">
          <label>
            Status
            <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
              <option value="all">All statuses</option>
              {Array.from(new Set(members.map((member) => member.status))).map((status) => (
                <option value={status} key={status}>{status}</option>
              ))}
            </select>
          </label>

          <label>
            Role
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
            <select value={sortKey} onChange={(event) => setSortKey(event.target.value as SortKey)}>
              <option value="name">Name</option>
              <option value="age">Age</option>
              <option value="level">Level</option>
              <option value="loyalty">Loyalty</option>
              <option value="morale">Morale</option>
              <option value="salary">Salary</option>
            </select>
          </label>
        </div>

        <div className="crew-view-toggle" aria-label="Crew view mode">
          <button
            className={`btn ${viewMode === 'grid' ? 'primary' : ''}`}
            onClick={() => setViewMode('grid')}
          >
            Grid
          </button>
          <button
            className={`btn ${viewMode === 'list' ? 'primary' : ''}`}
            onClick={() => setViewMode('list')}
          >
            Compact list
          </button>
        </div>
      </section>

      {loading && <LoadingState label="Loading crew dossiers…" />}

      {!loading && members.length === 0 && (
        <div className="card crew-empty-state">
          <div className="crew-empty-portrait" aria-hidden="true">?</div>
          <div>
            <h2>You are still working alone</h2>
            <p>
              Crew members unlock role assignments, specialist skills, and
              more reliable Dirty Job preparation. Recruits cost money and
              expect a weekly salary.
            </p>
            <p className="muted">
              Complete starter jobs, save cash, then open Recruitment.
            </p>
          </div>
        </div>
      )}

      {!loading && members.length > 0 && filteredMembers.length === 0 && (
        <EmptyState
          title="No crew members match the selected filters"
          message="Change role, age, status, or sort filters to find another dossier."
        />
      )}

      {!loading && filteredMembers.length > 0 && (
        <div className={`crew-card-collection crew-card-collection-${viewMode}`}>
          {filteredMembers.map((member) => (
            <CrewMemberCard
              member={member}
              busy={loadingId === member.id}
              onOpen={openProfile}
              onPayOverdue={payOverdue}
              onDismiss={dismiss}
              key={member.id}
            />
          ))}
        </div>
      )}

      {selectedMember && (
        <CrewProfile
          member={selectedMember}
          history={history}
          onClose={() => setSelectedMember(null)}
        />
      )}
    </section>
  );
}

function Summary({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="crew-summary-card">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}
