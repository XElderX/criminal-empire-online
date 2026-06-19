import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { GameHeader } from '../components/game/GameHeader';
import type {
  CrewMember,
  StarterJob,
  StarterJobRun,
  TutorialState,
} from '../types';

interface JobsPageProps {
  onChanged: () => void;
}

export function JobsPage({ onChanged }: JobsPageProps) {
  const [jobs, setJobs] = useState<StarterJob[]>([]);
  const [activeRuns, setActiveRuns] = useState<StarterJobRun[]>([]);
  const [crew, setCrew] = useState<CrewMember[]>([]);
  const [tutorial, setTutorial] = useState<TutorialState | null>(null);
  const [selectedMembers, setSelectedMembers] = useState<number[]>([]);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [clock, setClock] = useState(Date.now());

  async function load(): Promise<void> {
    try {
      const [jobResponse, runResponse, crewResponse, tutorialResponse] = await Promise.all([
        api<{ data: StarterJob[] }>('/jobs'),
        api<{ data: StarterJobRun[] }>('/jobs/active'),
        api<{ data: CrewMember[] }>('/my-gang'),
        api<{ tutorial: TutorialState }>('/tutorial'),
      ]);

      setJobs(jobResponse.data);
      setActiveRuns(runResponse.data);
      setCrew(crewResponse.data);
      setTutorial(tutorialResponse.tutorial);
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  useEffect(() => {
    const timer = window.setInterval(() => setClock(Date.now()), 1_000);
    return () => window.clearInterval(timer);
  }, []);

  const availableCrew = useMemo(
    () =>
      crew.filter(
        (member) => member.status === 'active' && !member.is_boss && member.id > 0,
      ),
    [crew],
  );

  const legalJobs = useMemo(
    () => jobs.filter((job) => job.category === 'legal'),
    [jobs],
  );

  const criminalJobs = useMemo(
    () => jobs.filter((job) => job.category === 'criminal'),
    [jobs],
  );

  function toggleMember(memberId: number): void {
    setSelectedMembers((current) =>
      current.includes(memberId)
        ? current.filter((id) => id !== memberId)
        : [...current, memberId],
    );
  }

  async function startJob(opportunityId: number): Promise<void> {
    setLoading(true);
    setMessage('');
    setError('');

    try {
      const response = await api<{ message: string }>('/jobs/' + opportunityId + '/start', {
        method: 'POST',
        body: JSON.stringify({
          member_ids: selectedMembers,
          idempotency_key: crypto.randomUUID(),
        }),
      });

      setMessage(response.message);
      setSelectedMembers([]);
      await load();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  async function completeRun(runId: number): Promise<void> {
    setLoading(true);
    setMessage('');
    setError('');

    try {
      const response = await api<{
        success: boolean;
        reward: number;
        heat_gained: number;
      }>(`/job-runs/${runId}/complete`, { method: 'POST' });

      setMessage(
        response.success
          ? `Job completed. You earned $${response.reward}.`
          : `The job failed. Heat increased by ${response.heat_gained}.`,
      );

      await load();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  function secondsRemaining(run: StarterJobRun): number {
    return Math.max(
      0,
      Math.ceil((new Date(run.completes_at).getTime() - clock) / 1_000),
    );
  }

  return (
    <section className="page-section">
      <GameHeader
        eyebrow="Street-level work"
        title="Street Jobs"
        description="Early jobs teach money, heat, and crew basics before larger Dirty Jobs."
      />

      {tutorial?.status === 'active' && tutorial.current_step && (
        <section className="card section-card">
          <p className="eyebrow">Current objective</p>
          <h2>{tutorial.current_step.title}</h2>
          <p>{tutorial.current_step.objective}</p>
        </section>
      )}

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      {activeRuns.length > 0 && (
        <section className="card section-card">
          <h2>Active jobs</h2>
          <div className="list-stack">
            {activeRuns.map((run) => {
              const remaining = secondsRemaining(run);

              return (
                <article className="list-row" key={run.id}>
                  <div>
                    <strong>{run.title}</strong>
                    <p className="muted">
                      {remaining > 0
                        ? `${remaining} seconds remaining`
                        : 'Ready to complete'}
                    </p>
                  </div>
                  <button
                    className="btn primary"
                    disabled={loading || remaining > 0}
                    onClick={() => completeRun(run.id)}
                  >
                    Complete
                  </button>
                </article>
              );
            })}
          </div>
        </section>
      )}

      <section className="card section-card">
        <h2>Required crew assignment</h2>
        <p className="muted">
          Street Jobs now require at least one active real NPC crew member. The boss
          cannot be assigned to these jobs.
        </p>
        {availableCrew.length === 0 ? (
          <p className="danger">
            Hire an NPC crew member from Recruitment before starting Street Jobs.
          </p>
        ) : (
          <div className="choice-grid">
            {availableCrew.map((member) => (
              <label className="choice-card" key={member.id}>
                <input
                  type="checkbox"
                  checked={selectedMembers.includes(member.id)}
                  onChange={() => toggleMember(member.id)}
                />
                <span>
                  <strong>{displayName(member)}</strong>
                  <small>
                    STR {member.strength} · INT {member.intelligence} · STL{' '}
                    {member.stealth}
                  </small>
                </span>
              </label>
            ))}
          </div>
        )}
      </section>

      <JobGroup
        title="Legal starter jobs"
        jobs={legalJobs}
        loading={loading}
        selectedMemberCount={selectedMembers.length}
        onStartJob={startJob}
      />

      <JobGroup
        title="Other starter jobs"
        jobs={criminalJobs}
        loading={loading}
        selectedMemberCount={selectedMembers.length}
        onStartJob={startJob}
      />

      {jobs.length === 0 && (
        <div className="card empty-state">
          No starter jobs are available right now. Process the world or check
          again after existing jobs finish.
        </div>
      )}
    </section>
  );
}

function displayName(member: CrewMember): string {
  const nickname = member.nickname ? ` “${member.nickname}”` : '';
  return `${member.first_name}${nickname} ${member.last_name}`;
}

function jobGiver(job: StarterJob): string {
  if (job.giver_nickname) {
    return job.giver_nickname;
  }

  const fullName = `${job.giver_first_name || ''} ${job.giver_last_name || ''}`.trim();
  return fullName || 'a local contact';
}

function JobGroup({
  title,
  jobs,
  loading,
  selectedMemberCount,
  onStartJob,
}: {
  title: string;
  jobs: StarterJob[];
  loading: boolean;
  selectedMemberCount: number;
  onStartJob: (opportunityId: number) => Promise<void>;
}) {
  if (jobs.length === 0) {
    return null;
  }

  return (
    <section className="section-card">
      <h2>{title}</h2>
      <div className="card-grid">
        {jobs.map((job) => {
          const requiredMembers = Math.max(1, job.min_assigned_members ?? 1);
          const hasSelectedCrew = selectedMemberCount >= requiredMembers;
          const assignmentMessage =
            job.assignment_hint ||
            `Assign at least ${requiredMembers} active NPC crew member${
              requiredMembers === 1 ? '' : 's'
            } before starting this Street Job.`;

          return (
          <article className="card" key={job.opportunity_id}>
            <div className="card-heading">
              <div>
                <p className="eyebrow">{job.territory_name}</p>
                <h2>{job.title}</h2>
              </div>
              <span className="risk-badge">Difficulty {job.difficulty}</span>
            </div>

            <p>{job.description}</p>
            <p className="muted">
              Offered by {jobGiver(job)} · {job.duration_seconds_effective}s
            </p>

            <dl className="details-grid">
              <div>
                <dt>Reward</dt>
                <dd>${job.reward_min}–${job.reward_max}</dd>
              </div>
              <div>
                <dt>Energy</dt>
                <dd>{job.energy_cost}</dd>
              </div>
              <div>
                <dt>Expected heat</dt>
                <dd>
                  {job.heat_min}–{job.heat_max}
                </dd>
              </div>
              <div>
                <dt>NPC crew required</dt>
                <dd>{requiredMembers}</dd>
              </div>
            </dl>

            {!hasSelectedCrew && (
              <p className="danger">{assignmentMessage}</p>
            )}

            {!job.can_start && (
              <p className="danger">
                {job.requirement_messages?.join(' ') || 'Requirements not met.'}
              </p>
            )}

            <button
              className="btn primary full-width"
              disabled={loading || !job.can_start || !hasSelectedCrew}
              onClick={() => onStartJob(job.opportunity_id)}
            >
              Start job
            </button>
          </article>
          );
        })}
      </div>
    </section>
  );
}
