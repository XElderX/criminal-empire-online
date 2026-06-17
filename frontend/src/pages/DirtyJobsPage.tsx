import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import type {
  CrewMember,
  DirtyJobDetail,
  DirtyJobEventChoice,
  DirtyJobOpportunity,
  DirtyJobRun,
  PreparationOption,
} from '../types';

interface DirtyJobsPageProps {
  onChanged: () => void;
}

interface EventPayload {
  prompt?: string;
  title?: string;
  text?: string;
  options?: DirtyJobEventChoice[];
  choices?: DirtyJobEventChoice[];
}

export function DirtyJobsPage({ onChanged }: DirtyJobsPageProps) {
  const [opportunities, setOpportunities] = useState<DirtyJobOpportunity[]>([]);
  const [activeRuns, setActiveRuns] = useState<DirtyJobRun[]>([]);
  const [history, setHistory] = useState<DirtyJobRun[]>([]);
  const [crew, setCrew] = useState<CrewMember[]>([]);
  const [detail, setDetail] = useState<DirtyJobDetail | null>(null);
  const [roleSelections, setRoleSelections] = useState<Record<number, string>>({});
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [clock, setClock] = useState(Date.now());

  async function loadLists(): Promise<void> {
    try {
      const [opportunityResponse, activeResponse, historyResponse, crewResponse] =
        await Promise.all([
          api<{ data: DirtyJobOpportunity[] }>('/dirty-jobs'),
          api<{ data: DirtyJobRun[] }>('/dirty-jobs/active'),
          api<{ data: DirtyJobRun[] }>('/dirty-jobs/history'),
          api<{ data: CrewMember[] }>('/my-gang'),
        ]);

      setOpportunities(opportunityResponse.data);
      setActiveRuns(activeResponse.data);
      setHistory(historyResponse.data);
      setCrew(crewResponse.data);
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  useEffect(() => {
    void loadLists();
  }, []);

  useEffect(() => {
    const timer = window.setInterval(() => setClock(Date.now()), 1_000);
    return () => window.clearInterval(timer);
  }, []);

  const activeCrew = useMemo(
    () => crew.filter((member) => member.status === 'active'),
    [crew],
  );

  async function openOpportunity(opportunityId: number): Promise<void> {
    setLoading(true);
    setError('');

    try {
      const response = await api<DirtyJobDetail>(`/dirty-jobs/${opportunityId}`);
      setDetail(response);
      setRoleSelections(assignmentsToSelections(response.run));
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  async function refreshDetail(opportunityId: number): Promise<void> {
    const response = await api<DirtyJobDetail>(`/dirty-jobs/${opportunityId}`);
    setDetail(response);
    setRoleSelections(assignmentsToSelections(response.run));
  }

  async function accept(): Promise<void> {
    if (!detail) {
      return;
    }

    await performAction(async () => {
      const response = await api<{ message: string }>(
        `/dirty-jobs/${detail.opportunity.opportunity_id || detail.opportunity.id}/accept`,
        {
          method: 'POST',
          body: JSON.stringify({ idempotency_key: crypto.randomUUID() }),
        },
      );

      return response.message;
    });
  }

  async function prepare(option: PreparationOption): Promise<void> {
    if (!detail?.run) {
      return;
    }

    await performAction(async () => {
      const response = await api<{ message: string }>(
        `/dirty-job-runs/${detail.run!.id}/prepare`,
        {
          method: 'POST',
          body: JSON.stringify({ action_code: option.code }),
        },
      );

      return response.message;
    });
  }

  function selectRole(memberId: number, roleCode: string): void {
    setRoleSelections((current) => ({
      ...current,
      [memberId]: roleCode,
    }));
  }

  async function saveAssignments(): Promise<void> {
    if (!detail?.run) {
      return;
    }

    const assignments = Object.entries(roleSelections)
      .filter(([, roleCode]) => roleCode !== '')
      .map(([memberId, roleCode]) => ({
        member_id: Number(memberId),
        role_code: roleCode,
      }));

    await performAction(async () => {
      const response = await api<{ message: string }>(
        `/dirty-job-runs/${detail.run!.id}/assign-crew`,
        {
          method: 'POST',
          body: JSON.stringify({ assignments }),
        },
      );

      return response.message;
    });
  }

  async function execute(): Promise<void> {
    if (!detail?.run) {
      return;
    }

    const confirmed = window.confirm(
      'Begin execution? Assigned members will become busy and their current loadouts will be used.',
    );

    if (!confirmed) {
      return;
    }

    await performAction(async () => {
      const response = await api<{ message: string }>(
        `/dirty-job-runs/${detail.run!.id}/execute`,
        { method: 'POST' },
      );

      return response.message;
    });
  }

  async function submitDecision(decisionCode: string): Promise<void> {
    if (!detail?.run) {
      return;
    }

    await performAction(async () => {
      const response = await api<{ message: string }>(
        `/dirty-job-runs/${detail.run!.id}/decision`,
        {
          method: 'POST',
          body: JSON.stringify({ decision_code: decisionCode }),
        },
      );

      return response.message;
    });
  }

  async function resolve(): Promise<void> {
    if (!detail?.run) {
      return;
    }

    await performAction(async () => {
      const response = await api<{ message: string }>(
        `/dirty-job-runs/${detail.run!.id}/resolve`,
        { method: 'POST' },
      );

      return response.message;
    });
  }

  async function performAction(action: () => Promise<string>): Promise<void> {
    if (!detail) {
      return;
    }

    setLoading(true);
    setMessage('');
    setError('');

    try {
      const actionMessage = await action();
      setMessage(actionMessage);
      await refreshDetail(
        detail.opportunity.opportunity_id || detail.opportunity.id,
      );
      await loadLists();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  const event = (detail?.run?.event || {}) as EventPayload;
  const eventChoices = event.options || event.choices || [];
  const secondsRemaining = detail?.run?.completes_at
    ? Math.max(
        0,
        Math.ceil(
          (new Date(detail.run.completes_at).getTime() - clock) / 1_000,
        ),
      )
    : 0;

  return (
    <section className="page-section dirty-jobs-layout">
      <header className="page-header full-span">
        <div>
          <p className="eyebrow">Story operations</p>
          <h1>Dirty Jobs</h1>
          <p className="muted">
            Accept limited NPC opportunities, prepare the target, assign crew
            roles, use member loadouts, and manage consequences.
          </p>
        </div>
      </header>

      <div className="full-span">
        {message && <Notice message={message} kind="success" />}
        {error && <Notice message={error} kind="error" />}
      </div>

      <aside className="operation-sidebar">
        <section className="card section-card">
          <h2>Available opportunities</h2>
          <div className="operation-list">
            {opportunities.map((opportunity) => (
              <button
                className={
                  detail?.opportunity.id === opportunity.id
                    ? 'operation-list-item active'
                    : 'operation-list-item'
                }
                key={opportunity.id}
                onClick={() => openOpportunity(opportunity.id)}
              >
                <span>Tier {opportunity.tier} · {humanize(opportunity.category)}</span>
                <strong>{opportunity.title}</strong>
                <small>
                  ${opportunity.estimated_reward_min}–${opportunity.estimated_reward_max}
                </small>
              </button>
            ))}
            {opportunities.length === 0 && (
              <p className="muted">No available opportunities.</p>
            )}
          </div>
        </section>

        <section className="card section-card">
          <h2>Active operations</h2>
          <div className="operation-list">
            {activeRuns.map((run) => (
              <button
                className="operation-list-item"
                key={run.id}
                onClick={() => openOpportunity(run.opportunity_id)}
              >
                <span>{run.status}</span>
                <strong>{run.title || `Operation #${run.id}`}</strong>
                <small>{run.territory_name}</small>
              </button>
            ))}
            {activeRuns.length === 0 && (
              <p className="muted">No active operations.</p>
            )}
          </div>
        </section>
      </aside>

      <main className="operation-detail">
        {!detail && (
          <div className="card empty-state">
            Select an available or active Dirty Job to open the full briefing.
          </div>
        )}

        {detail && (
          <>
            <section className="card operation-hero">
              <div className="card-heading">
                <div>
                  <p className="eyebrow">
                    Tier {detail.opportunity.tier} · {humanize(detail.opportunity.category)}
                  </p>
                  <h2>{detail.opportunity.title}</h2>
                </div>
                <span className="risk-badge">
                  Police {detail.opportunity.police_presence}
                </span>
              </div>

              <p className="lead">{detail.opportunity.introduction}</p>
              <p>{detail.opportunity.briefing || detail.opportunity.short_description}</p>
              <p className="muted">
                Contact: {detail.opportunity.contact_name} · District:{' '}
                {detail.opportunity.territory_name} · Expires:{' '}
                {new Date(detail.opportunity.expires_at).toLocaleString()}
              </p>

              <dl className="details-grid">
                <div>
                  <dt>Estimated reward</dt>
                  <dd>
                    ${detail.opportunity.estimated_reward_min}–$
                    {detail.opportunity.estimated_reward_max}
                  </dd>
                </div>
                <div>
                  <dt>Energy</dt>
                  <dd>{detail.opportunity.energy_cost}</dd>
                </div>
                <div>
                  <dt>Heat range</dt>
                  <dd>{detail.opportunity.heat_min}–{detail.opportunity.heat_max}</dd>
                </div>
                <div>
                  <dt>Minimum crew</dt>
                  <dd>{detail.opportunity.min_crew_size}</dd>
                </div>
              </dl>

              {detail.opportunity.requirement_messages.length > 0 && (
                <ul className="requirement-list">
                  {detail.opportunity.requirement_messages.map((requirement) => (
                    <li key={requirement}>{requirement}</li>
                  ))}
                </ul>
              )}

              {!detail.run && (
                <button
                  className="btn primary"
                  disabled={loading || !detail.opportunity.can_accept}
                  onClick={accept}
                >
                  Accept Dirty Job
                </button>
              )}
            </section>

            {detail.run && (
              <OperationWorkspace
                detail={detail}
                crew={activeCrew}
                roleSelections={roleSelections}
                event={event}
                eventChoices={eventChoices}
                secondsRemaining={secondsRemaining}
                loading={loading}
                onPrepare={prepare}
                onRoleChange={selectRole}
                onSaveAssignments={saveAssignments}
                onExecute={execute}
                onDecision={submitDecision}
                onResolve={resolve}
              />
            )}
          </>
        )}

        {history.length > 0 && (
          <section className="card section-card">
            <h2>Recent operation history</h2>
            <div className="list-stack">
              {history.slice(0, 8).map((run) => (
                <article className="list-row" key={run.id}>
                  <div>
                    <strong>{run.title || `Operation #${run.id}`}</strong>
                    <p className="muted">
                      {run.territory_name} · {run.outcome || run.status}
                    </p>
                  </div>
                  <span>
                    ${(run.cash_reward || 0) + (run.dirty_cash_reward || 0)}
                  </span>
                </article>
              ))}
            </div>
          </section>
        )}
      </main>
    </section>
  );
}

interface OperationWorkspaceProps {
  detail: DirtyJobDetail;
  crew: CrewMember[];
  roleSelections: Record<number, string>;
  event: EventPayload;
  eventChoices: DirtyJobEventChoice[];
  secondsRemaining: number;
  loading: boolean;
  onPrepare: (option: PreparationOption) => void;
  onRoleChange: (memberId: number, roleCode: string) => void;
  onSaveAssignments: () => void;
  onExecute: () => void;
  onDecision: (decisionCode: string) => void;
  onResolve: () => void;
}

function OperationWorkspace({
  detail,
  crew,
  roleSelections,
  event,
  eventChoices,
  secondsRemaining,
  loading,
  onPrepare,
  onRoleChange,
  onSaveAssignments,
  onExecute,
  onDecision,
  onResolve,
}: OperationWorkspaceProps) {
  const run = detail.run!;
  const editable = ['accepted', 'preparing', 'ready'].includes(run.status);
  const completedPreparationCodes = new Set(
    (run.preparations || []).map((entry) => String(entry.action_code || '')),
  );

  return (
    <>
      <section className="card section-card">
        <div className="section-heading-row">
          <div>
            <p className="eyebrow">Operation #{run.id}</p>
            <h2>Status: {humanize(run.status)}</h2>
          </div>
          {run.status === 'executing' && (
            <span className="timer-badge">
              {secondsRemaining > 0
                ? `${secondsRemaining}s remaining`
                : 'Ready to resolve'}
            </span>
          )}
        </div>
      </section>

      {editable && (
        <>
          <section className="card section-card">
            <h2>1. Preparation</h2>
            <p className="muted">
              Preparation can improve success or reduce heat, but costs money,
              energy, or time. Bonuses remain bounded.
            </p>
            <div className="card-grid compact-grid">
              {(detail.opportunity.preparation_options || []).map((option) => {
                const completed = completedPreparationCodes.has(option.code);

                return (
                  <article className="sub-card" key={option.code}>
                    <h3>{option.name}</h3>
                    <p>{option.description || humanize(option.code)}</p>
                    <p className="muted">
                      Cash ${option.cash_cost || 0} · Energy {option.energy_cost || 0}
                    </p>
                    <button
                      className="btn"
                      disabled={loading || completed}
                      onClick={() => onPrepare(option)}
                    >
                      {completed ? 'Completed' : 'Perform preparation'}
                    </button>
                  </article>
                );
              })}
            </div>
          </section>

          <section className="card section-card">
            <h2>2. Crew roles and current loadouts</h2>
            <p className="muted">
              A member may fill one role. Equipment is taken from each assigned
              member’s current loadout when execution starts.
            </p>

            <div className="assignment-table">
              {crew.map((member) => (
                <div className="assignment-row" key={member.id}>
                  <div>
                    <strong>{displayCrewName(member)}</strong>
                    <small>
                      {member.status} · HP {member.health}/{member.max_health} ·
                      morale {member.morale}
                    </small>
                    <small>
                      Loadout:{' '}
                      {member.equipment.map((item) => item.name).join(', ') || 'none'}
                    </small>
                  </div>
                  <select
                    value={roleSelections[member.id] || ''}
                    onChange={(event) => onRoleChange(member.id, event.target.value)}
                  >
                    <option value="">Not assigned</option>
                    {Object.entries(detail.crew_roles).map(([code, definition]) => (
                      <option value={code} key={code}>
                        {definition.name} ({definition.stats.join(', ')})
                      </option>
                    ))}
                  </select>
                </div>
              ))}
              {crew.length === 0 && (
                <p className="muted">
                  You have no active crew. Tier 1 jobs with no crew requirement
                  can still be attempted alone.
                </p>
              )}
            </div>

            {detail.opportunity.required_roles.length > 0 && (
              <p>
                Required roles:{' '}
                <strong>{detail.opportunity.required_roles.join(', ')}</strong>
              </p>
            )}

            <div className="button-row">
              <button className="btn" disabled={loading} onClick={onSaveAssignments}>
                Save assignments
              </button>
              <button className="btn primary" disabled={loading} onClick={onExecute}>
                Begin execution
              </button>
            </div>
          </section>
        </>
      )}

      {run.status === 'executing' && (
        <section className="card section-card">
          <h2>Execution in progress</h2>
          <p>
            The backend timer is authoritative. Crew members stay busy until
            the operation resolves.
          </p>
          <button
            className="btn primary"
            disabled={loading || secondsRemaining > 0}
            onClick={onResolve}
          >
            Resolve operation
          </button>
        </section>
      )}

      {run.status === 'awaiting_decision' && (
        <section className="card section-card event-card">
          <p className="eyebrow">Live complication</p>
          <h2>{event.title || 'A decision is required'}</h2>
          <p>{event.prompt || event.text || 'Choose how the crew should respond.'}</p>
          <div className="choice-grid">
            {eventChoices.map((choice) => (
              <button
                className="choice-button"
                disabled={loading}
                onClick={() => onDecision(choice.code)}
                key={choice.code}
              >
                <strong>{choice.label}</strong>
                {choice.description && <span>{choice.description}</span>}
              </button>
            ))}
          </div>
        </section>
      )}

      {['completed', 'partially_completed', 'failed'].includes(run.status) && (
        <section className="card section-card result-card">
          <p className="eyebrow">Final result</p>
          <h2>{humanize(run.outcome || run.status)}</h2>
          <p>{run.result?.result_text || 'The operation has been resolved.'}</p>

          <dl className="details-grid">
            <div><dt>Clean cash</dt><dd>${run.cash_reward || 0}</dd></div>
            <div><dt>Dirty cash</dt><dd>${run.dirty_cash_reward || 0}</dd></div>
            <div><dt>Heat gained</dt><dd>{run.heat_gained || 0}</dd></div>
            <div><dt>Experience</dt><dd>{run.experience_gained || 0}</dd></div>
            <div><dt>Reputation</dt><dd>{run.reputation_gained || 0}</dd></div>
          </dl>

          <OutcomeList
            title="Physical rewards"
            values={run.result?.physical_rewards || []}
          />
          <OutcomeList
            title="Crew consequences"
            values={run.result?.crew_consequences || []}
          />
          <OutcomeList
            title="Equipment consequences"
            values={run.result?.equipment_consequences || []}
          />
        </section>
      )}
    </>
  );
}

function OutcomeList({
  title,
  values,
}: {
  title: string;
  values: Array<Record<string, unknown>>;
}) {
  if (values.length === 0) {
    return null;
  }

  return (
    <div className="outcome-list">
      <h3>{title}</h3>
      {values.map((entry, index) => (
        <pre key={`${title}-${index}`}>{JSON.stringify(entry, null, 2)}</pre>
      ))}
    </div>
  );
}

function assignmentsToSelections(run: DirtyJobRun | null): Record<number, string> {
  const selections: Record<number, string> = {};

  for (const assignment of run?.assignments || []) {
    selections[assignment.gang_member_id] = assignment.role_code;
  }

  return selections;
}

function humanize(value: string): string {
  return value
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

function displayCrewName(member: CrewMember): string {
  const nickname = member.nickname ? ` “${member.nickname}”` : '';
  return `${member.first_name}${nickname} ${member.last_name}`;
}
