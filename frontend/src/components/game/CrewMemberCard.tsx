import type { CrewMember } from '../../types';
import { getRoleIcon } from '../../data/assetManifest';
import { CrewPortrait } from './CrewPortrait';
import { ProgressBar } from './ProgressBar';

interface CrewMemberCardProps {
  member: CrewMember;
  busy?: boolean;
  onOpen?: (member: CrewMember) => void;
  onPayOverdue?: (member: CrewMember) => void;
  onDismiss?: (member: CrewMember) => void;
}

export function CrewMemberCard({
  member,
  busy = false,
  onOpen,
  onPayOverdue,
  onDismiss,
}: CrewMemberCardProps) {
  const roleIcon = getRoleIcon(member.role_code || member.role?.key);
  const name = displayCrewName(member);

  return (
    <article className={`crew-member-card card crew-accent-${member.role.accent}`}>
      <CrewPortrait
        gender={member.gender}
        portraitKey={member.portrait?.identity_key}
        age={member.age}
        alt={`Portrait of ${name}`}
      />
      <div className="crew-member-body">
        <header className="crew-member-header">
          <div>
            <p className="eyebrow">{member.gender || 'Unknown'} · Age {member.age}</p>
            <h2>{name}</h2>
            <p className="muted">{member.occupation} · {member.territory_name}</p>
          </div>
          <img className="role-icon" src={roleIcon} alt="" />
        </header>

        <div className="tag-row">
          <span className="status-badge">{member.role.name}</span>
          <span className={`status-badge status-${member.status}`}>{member.status}</span>
        </div>

        <div className="crew-meter-stack">
          <ProgressBar label="Loyalty" value={member.loyalty} max={100} tone="money" />
          <ProgressBar label="Morale" value={member.morale} max={100} tone="blue" />
        </div>

        <dl className="details-grid crew-mini-skills">
          <div><dt>Combat</dt><dd>{member.strength}</dd></div>
          <div><dt>Driving</dt><dd>{member.driving}</dd></div>
          <div><dt>Stealth</dt><dd>{member.stealth}</dd></div>
          <div><dt>Intel</dt><dd>{member.intelligence}</dd></div>
        </dl>

        <footer className="crew-member-actions">
          {onOpen && <button className="btn" onClick={() => onOpen(member)}>Dossier</button>}
          {member.unpaid_salary > 0 && onPayOverdue && (
            <button className="btn primary" disabled={busy} onClick={() => onPayOverdue(member)}>
              Pay ${member.unpaid_salary}
            </button>
          )}
          {onDismiss && (
            <button className="btn danger-button" disabled={busy || member.status === 'busy'} onClick={() => onDismiss(member)}>
              Dismiss
            </button>
          )}
        </footer>
      </div>
    </article>
  );
}

function displayCrewName(member: CrewMember): string {
  const nickname = member.nickname ? ` “${member.nickname}”` : '';
  return `${member.first_name}${nickname} ${member.last_name}`;
}
