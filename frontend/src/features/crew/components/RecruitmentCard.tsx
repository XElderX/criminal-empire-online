import type { RecruitmentCandidate } from '../../../types';
import { CrewConditionMeters } from './CrewConditionMeters';
import { CrewExperienceBar } from './CrewExperienceBar';
import { CrewPortrait } from './CrewPortrait';
import { CrewRoleBadge } from './CrewRoleBadge';
import { CrewSkillGrid } from './CrewSkillGrid';
import { CrewTraitList } from './CrewTraitList';
import {
  displayCrewName,
  formatMoney,
} from '../utils/crewPresentation';

interface RecruitmentCardProps {
  candidate: RecruitmentCandidate;
  busy?: boolean;
  onHire: (candidate: RecruitmentCandidate) => void;
}

export function RecruitmentCard({
  candidate,
  busy = false,
  onHire,
}: RecruitmentCardProps) {
  const name = displayCrewName(candidate);

  return (
    <article className={`recruit-card crew-accent-${candidate.role.accent}`}>
      <CrewPortrait
        portrait={candidate.portrait}
        gender={candidate.gender}
        age={candidate.age}
        alt={`Portrait of ${name}, age ${candidate.age}`}
        size="card"
      />

      <div className="recruit-card-content">
        <header>
          <div className="crew-card-kicker">
            <CrewRoleBadge role={candidate.role} />
            <span>
              Age {candidate.age} · {candidate.life_stage.label}
            </span>
          </div>
          <h2>{renderName(candidate)}</h2>
          <p className="crew-card-origin">
            {candidate.occupation} · {candidate.territory_name}
          </p>
        </header>

        <p className="recruit-biography">{candidate.biography}</p>

        <CrewExperienceBar
          level={candidate.level}
          current={candidate.experience_into_level}
          required={candidate.experience_for_next_level}
          progress={candidate.experience_progress_percent}
        />

        <CrewConditionMeters
          health={candidate.health}
          maximumHealth={candidate.max_health}
          morale={candidate.morale}
          loyalty={candidate.loyalty}
          compact
        />

        <section className="crew-card-section">
          <h3>Strongest skills</h3>
          <CrewSkillGrid person={candidate} compact />
        </section>

        <section className="crew-card-section">
          <h3>Known traits</h3>
          <CrewTraitList traits={candidate.traits} compact />
        </section>

        <div className="recruit-price-grid">
          <div>
            <span>Recruitment fee</span>
            <strong>{formatMoney(candidate.recruitment_fee)}</strong>
          </div>
          <div>
            <span>Weekly salary</span>
            <strong>{formatMoney(candidate.salary_weekly)}</strong>
          </div>
          <div>
            <span>Personal cash</span>
            <strong>{formatMoney(candidate.personal_cash)}</strong>
          </div>
        </div>

        {candidate.hire_block_reasons.length > 0 && (
          <ul className="recruit-block-reasons">
            {candidate.hire_block_reasons.map((reason) => (
              <li key={reason}>{reason}</li>
            ))}
          </ul>
        )}

        <button
          className="btn primary full-width"
          disabled={!candidate.can_hire || busy}
          onClick={() => onHire(candidate)}
        >
          {busy ? 'Hiring…' : `Hire for ${formatMoney(candidate.recruitment_fee)}`}
        </button>
      </div>
    </article>
  );
}

function renderName(candidate: RecruitmentCandidate) {
  return (
    <>
      <span>{candidate.first_name} </span>
      {candidate.nickname && (
        <span className="crew-nickname">“{candidate.nickname}” </span>
      )}
      <span>{candidate.last_name}</span>
    </>
  );
}
