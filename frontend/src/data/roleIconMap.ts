import { slugify } from '../utils/stringFormat';

export const roleIconMap: Record<string, string> = {
  leader: '/assets/icons/roles/leader.webp',
  enforcer: '/assets/icons/roles/enforcer.webp',
  driver: '/assets/icons/roles/driver.webp',
  hacker: '/assets/icons/roles/hacker.webp',
  scout: '/assets/icons/roles/scout.webp',
  medic: '/assets/icons/roles/medic.webp',
  negotiator: '/assets/icons/roles/negotiator.webp',
  thief: '/assets/icons/roles/thief.webp',
  smuggler: '/assets/icons/roles/smuggler.webp',
  cleaner: '/assets/icons/roles/cleaner.webp',
  fixer: '/assets/icons/roles/fixer.webp',
  recruit: '/assets/icons/roles/recruit.webp',
};

export function getRoleIcon(role: string | null | undefined): string {
  return roleIconMap[slugify(role)] || roleIconMap.recruit;
}
