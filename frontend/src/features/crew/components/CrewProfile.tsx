import type { CrewHistoryEntry, CrewMember } from '../../../types';
import { CrewConditionMeters } from './CrewConditionMeters';
import { CrewEquipmentGrid } from './CrewEquipmentGrid';
import { CrewExperienceBar } from './CrewExperienceBar';
import { CrewHistoryTimeline } from './CrewHistoryTimeline';
import { CrewPortrait } from './CrewPortrait';
import { CrewRoleBadge } from './CrewRoleBadge';
import { CrewSkillGrid } from './CrewSkillGrid';
import { CrewStatusBadge } from './CrewStatusBadge';
import { CrewTraitList } from './CrewTraitList';
import {
  displayCrewName,
  formatMoney,
} from '../utils/crewPresentation';

interface CrewProfileProps {
  member: CrewMember;
  history: CrewHistoryEntry[];
  onClose: () => void;
}

export function CrewProfile({ member, history, onClose }: CrewProfileProps) {
  const name = displayCrewName(member);

  return (
    <div className="modal-backdrop" role="presentation">
      <section
        className={`crew-profile-modal crew-accent-${member.role.accent}`}
        role="dialog"
        aria-modal="true"
        aria-label={`${name} crew profile`}
      >
        <button
          className="crew-profile-close"
          onClick={onClose}
          aria-label="Close crew profile"
        >
          ×
        </button>

        <header className="crew-profile-hero">
          <CrewPortrait
            portrait={member.portrait}
            gender={member.gender}
            age={member.age}
            alt={`Portrait of ${name}, age ${member.age}`}
            size="profile"
            status={member.status}
          />
          <div className="crew-profile-identity">
            <div className="crew-profile-badges">
              <CrewRoleBadge role={member.role} />
              <CrewStatusBadge status={member.status} />
              <span className="crew-age-badge">
                Age {member.age} · {member.life_stage.label}
              </span>
            </div>
            <p className="eyebrow">Crew dossier</p>
            <h1>{renderName(member)}</h1>
            <p className="lead">
              {member.occupation} from {member.territory_name}
            </p>
            <CrewExperienceBar
              level={member.level}
              current={member.experience_into_level}
              required={member.experience_for_next_level}
              progress={member.experience_progress_percent}
            />
            <div className="crew-profile-reputation">
              <span>Reputation</span>
              <strong>{member.reputation_label}</strong>
            </div>
          </div>
        </header>

        <div className="crew-profile-body">
          <section className="crew-profile-panel crew-profile-biography">
            <h2>Biography</h2>
            <p>{member.biography || 'No biography recorded.'}</p>
            <dl className="crew-profile-facts">
              <div>
                <dt>Background</dt>
                <dd>{member.background || 'Unknown'}</dd>
              </div>
              <div>
                <dt>Former occupation</dt>
                <dd>{member.occupation || 'Unknown'}</dd>
              </div>
              <div>
                <dt>District of origin</dt>
                <dd>{member.territory_name || 'Unknown'}</dd>
              </div>
              <div>
                <dt>Portrait identity</dt>
                <dd>{member.portrait.identity_key || 'Fallback portrait'}</dd>
              </div>
            </dl>
          </section>

          <section className="crew-profile-panel">
            <h2>Condition</h2>
            <CrewConditionMeters
              health={member.health}
              maximumHealth={member.max_health}
              morale={member.morale}
              loyalty={member.loyalty}
            />
            <dl className="crew-profile-facts">
              <div>
                <dt>Unpaid salary</dt>
                <dd className={member.unpaid_salary > 0 ? 'danger' : ''}>
                  {formatMoney(member.unpaid_salary)}
                </dd>
              </div>
              <div>
                <dt>Recovery until</dt>
                <dd>{formatDate(member.recovery_until)}</dd>
              </div>
              <div>
                <dt>Arrested until</dt>
                <dd>{formatDate(member.arrested_until)}</dd>
              </div>
            </dl>
          </section>

          <section className="crew-profile-panel crew-profile-wide">
            <h2>Operational skills</h2>
            <CrewSkillGrid person={member} />
          </section>

          <section className="crew-profile-panel">
            <h2>Traits</h2>
            <CrewTraitList traits={member.traits} />
          </section>

          <section className="crew-profile-panel">
            <h2>Equipment</h2>
            <CrewEquipmentGrid equipment={member.equipment} />
          </section>

          <section className="crew-profile-panel">
            <h2>Finances</h2>
            <dl className="crew-profile-facts">
              <div>
                <dt>Weekly salary</dt>
                <dd>{formatMoney(member.salary_weekly)}</dd>
              </div>
              <div>
                <dt>Personal cash</dt>
                <dd>{formatMoney(member.personal_cash)}</dd>
              </div>
              <div>
                <dt>Total earnings</dt>
                <dd>{formatMoney(member.total_earnings)}</dd>
              </div>
            </dl>
          </section>

          <section className="crew-profile-panel">
            <h2>Career record</h2>
            <dl className="crew-profile-facts">
              <div><dt>Jobs completed</dt><dd>{member.jobs_completed}</dd></div>
              <div><dt>Jobs failed</dt><dd>{member.jobs_failed}</dd></div>
              <div><dt>Arrests</dt><dd>{member.arrests}</dd></div>
              <div><dt>Injuries</dt><dd>{member.injuries}</dd></div>
            </dl>
          </section>

          <section className="crew-profile-panel crew-profile-wide">
            <h2>History</h2>
            <CrewHistoryTimeline entries={history} />
          </section>
        </div>
      </section>
    </div>
  );
}

function renderName(member: CrewMember) {
  return (
    <>
      <span>{member.first_name} </span>
      {member.nickname && (
        <span className="crew-nickname">“{member.nickname}” </span>
      )}
      <span>{member.last_name}</span>
    </>
  );
}

function formatDate(value?: string | null): string {
  return value ? new Date(value).toLocaleString() : 'Not applicable';
}
