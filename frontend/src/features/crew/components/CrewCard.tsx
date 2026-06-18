import type { CrewMember } from '../../../types';
import { CrewConditionMeters } from './CrewConditionMeters';
import { CrewEquipmentGrid } from './CrewEquipmentGrid';
import { CrewExperienceBar } from './CrewExperienceBar';
import { CrewPortrait } from './CrewPortrait';
import { CrewRoleBadge } from './CrewRoleBadge';
import { CrewSkillGrid } from './CrewSkillGrid';
import { CrewStatusBadge } from './CrewStatusBadge';
import { CrewTraitList } from './CrewTraitList';
import {
  displayCrewName,
  formatMoney,
} from '../utils/crewPresentation';

interface CrewCardProps {
  member: CrewMember;
  viewMode: 'grid' | 'list';
  busy?: boolean;
  onOpen: (member: CrewMember) => void;
  onPayOverdue: (member: CrewMember) => void;
  onDismiss: (member: CrewMember) => void;
}

export function CrewCard({
  member,
  viewMode,
  busy = false,
  onOpen,
  onPayOverdue,
  onDismiss,
}: CrewCardProps) {
  const name = displayCrewName(member);

  return (
    <article
      className={`crew-card crew-card-${viewMode} crew-accent-${member.role.accent}`}
    >
      <div className="crew-card-portrait-column">
        <CrewPortrait
          portrait={member.portrait}
          alt={`Portrait of ${name}, age ${member.age}`}
          status={member.status}
          size={viewMode === 'list' ? 'compact' : 'card'}
        />
      </div>

      <div className="crew-card-content">
        <header className="crew-card-header">
          <div>
            <div className="crew-card-kicker">
              <CrewRoleBadge role={member.role} />
              <span>
                Age {member.age} · {member.life_stage.label}
              </span>
            </div>
            <h2>{renderName(member)}</h2>
            <p className="crew-card-origin">
              {member.occupation} · {member.territory_name}
            </p>
          </div>
          <CrewStatusBadge status={member.status} />
        </header>

        <CrewExperienceBar
          level={member.level}
          current={member.experience_into_level}
          required={member.experience_for_next_level}
          progress={member.experience_progress_percent}
        />

        <div className="crew-card-reputation">
          <span>Reputation</span>
          <strong>{member.reputation_label}</strong>
        </div>

        <CrewConditionMeters
          health={member.health}
          maximumHealth={member.max_health}
          morale={member.morale}
          loyalty={member.loyalty}
          compact
        />

        <section className="crew-card-section">
          <h3>Top skills</h3>
          <CrewSkillGrid person={member} compact />
        </section>

        {viewMode === 'grid' && (
          <>
            <section className="crew-card-section">
              <h3>Traits</h3>
              <CrewTraitList traits={member.traits} compact />
            </section>

            <section className="crew-card-section">
              <h3>Equipment</h3>
              <CrewEquipmentGrid equipment={member.equipment} compact />
            </section>

            <blockquote className="crew-card-biography">
              {member.biography || 'No biography recorded.'}
            </blockquote>
          </>
        )}

        <footer className="crew-card-footer">
          <div>
            <span>Salary</span>
            <strong>{formatMoney(member.salary_weekly)}/week</strong>
          </div>
          <div>
            <span>Personal cash</span>
            <strong>{formatMoney(member.personal_cash)}</strong>
          </div>
          <div className="crew-card-actions">
            <button className="btn" onClick={() => onOpen(member)}>
              Open profile
            </button>
            {member.unpaid_salary > 0 && (
              <button
                className="btn primary"
                disabled={busy}
                onClick={() => onPayOverdue(member)}
              >
                Pay {formatMoney(member.unpaid_salary)}
              </button>
            )}
            <button
              className="btn danger-button"
              disabled={busy || member.status === 'busy'}
              onClick={() => onDismiss(member)}
            >
              Dismiss
            </button>
          </div>
        </footer>
      </div>
    </article>
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
