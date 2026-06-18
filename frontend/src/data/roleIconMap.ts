import { slugify } from '../utils/stringFormat';

export const roleIconMap: Record<string, string> = {
  leader: '/assets/icons/roles/leader.svg',
  enforcer: '/assets/icons/roles/enforcer.svg',
  driver: '/assets/icons/roles/driver.svg',
  hacker: '/assets/icons/roles/hacker.svg',
  scout: '/assets/icons/roles/scout.svg',
  medic: '/assets/icons/roles/medic.svg',
  negotiator: '/assets/icons/roles/negotiator.svg',
  thief: '/assets/icons/roles/thief.svg',
  smuggler: '/assets/icons/roles/smuggler.svg',
  cleaner: '/assets/icons/roles/cleaner.svg',
  fixer: '/assets/icons/roles/fixer.svg',
  recruit: '/assets/icons/roles/recruit.svg',
};

export function getRoleIcon(role: string | null | undefined): string {
  return roleIconMap[slugify(role)] || roleIconMap.recruit;
}
