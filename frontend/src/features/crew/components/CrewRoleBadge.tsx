import { getRoleIcon } from '../../../data/assetManifest';
import type { CrewRolePresentation } from '../../../types';

export function CrewRoleBadge({ role }: { role: CrewRolePresentation }) {
  return (
    <span
      className={`crew-role crew-role-${role.accent}`}
      title={role.description}
    >
      <img className="crew-role-icon" src={getRoleIcon(role.key)} alt="" />
      {role.name}
    </span>
  );
}
