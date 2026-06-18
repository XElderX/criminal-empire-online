import type { CrewMember, RecruitmentCandidate } from '../../../types';
import {
  crewStatDefinitions,
  topCrewStats,
  type CrewLike,
} from '../utils/crewPresentation';

interface CrewSkillGridProps {
  person: CrewLike;
  compact?: boolean;
}

export function CrewSkillGrid({ person, compact = false }: CrewSkillGridProps) {
  const stats = compact
    ? topCrewStats(person, 4)
    : crewStatDefinitions.map((definition) => ({
        ...definition,
        value: Number(person[definition.key] ?? 0),
      }));

  return (
    <div className={`crew-skill-grid ${compact ? 'crew-skill-grid-compact' : ''}`}>
      {stats.map((stat) => (
        <div
          className="crew-skill"
          key={stat.key}
          title={`${stat.label}: ${stat.value}`}
          aria-label={`${stat.label}: ${stat.value}`}
        >
          <span className="crew-skill-icon" aria-hidden="true">{stat.icon}</span>
          <span className="crew-skill-label">{stat.label}</span>
          <strong>{stat.value}</strong>
        </div>
      ))}
    </div>
  );
}

export type CrewSkillPerson = CrewMember | RecruitmentCandidate;
