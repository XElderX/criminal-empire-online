import type { CrewMember, RecruitmentCandidate } from '../../../types';

export type CrewLike = CrewMember | RecruitmentCandidate;

export const crewStatDefinitions = [
  { key: 'strength', label: 'Strength', shortLabel: 'STR', icon: '◆' },
  { key: 'shooting', label: 'Shooting', shortLabel: 'SHO', icon: '⌖' },
  { key: 'driving', label: 'Driving', shortLabel: 'DRV', icon: '◉' },
  { key: 'intelligence', label: 'Intelligence', shortLabel: 'INT', icon: '▣' },
  { key: 'stealth', label: 'Stealth', shortLabel: 'STL', icon: '◇' },
  { key: 'intimidation', label: 'Intimidation', shortLabel: 'ITM', icon: '✦' },
  { key: 'discipline', label: 'Discipline', shortLabel: 'DIS', icon: '◎' },
  {
    key: 'street_knowledge',
    label: 'Street knowledge',
    shortLabel: 'STK',
    icon: '◈',
  },
  { key: 'endurance', label: 'Endurance', shortLabel: 'END', icon: '⬢' },
] as const;

export type CrewStatKey = (typeof crewStatDefinitions)[number]['key'];

export function displayCrewName(person: CrewLike): string {
  const nickname = person.nickname ? ` “${person.nickname}”` : '';
  return `${person.first_name}${nickname} ${person.last_name}`;
}

export function topCrewStats(person: CrewLike, limit = 4) {
  return crewStatDefinitions
    .map((definition) => ({
      ...definition,
      value: Number(person[definition.key] ?? 0),
    }))
    .sort((left, right) => right.value - left.value)
    .slice(0, limit);
}

export function formatMoney(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
  }).format(amount);
}

export function statusLabel(status: string): string {
  return status
    .split('_').join(' ')
    .replace(/\b\w/g, (letter: string) => letter.toUpperCase());
}
