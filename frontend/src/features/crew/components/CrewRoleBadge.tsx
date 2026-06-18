import type { CrewRolePresentation } from '../../../types';

export function CrewRoleBadge({ role }: { role: CrewRolePresentation }) {
  return (
    <span
      className={`crew-role crew-role-${role.accent}`}
      title={role.description}
    >
      <span aria-hidden="true">{role.icon}</span>
      {role.name}
    </span>
  );
}
