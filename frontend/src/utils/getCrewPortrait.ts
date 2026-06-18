export type CrewGender = 'male' | 'female';

export interface CrewPortraitSources {
  primary: string;
  adultFallback: string;
  genderFallback: string;
  globalFallback: string;
  sources: string[];
}

export function getCrewPortrait(
  gender: string | null | undefined,
  portraitKey: string | null | undefined,
  age: number | string | null | undefined,
): CrewPortraitSources {
  const normalizedGender = normalizeGender(gender);
  const stage = ageToStage(Number(age ?? 32));
  const identity = normalizePortraitIdentity(portraitKey);

  const primary = `/assets/crew/${normalizedGender}/crew_${normalizedGender}_${identity}_${stage}.webp`;
  const adultFallback = `/assets/crew/${normalizedGender}/crew_${normalizedGender}_${identity}_32_40.webp`;
  const genderFallback = `/assets/crew/${normalizedGender}/default.svg`;
  const globalFallback = '/assets/crew/default.svg';

  return {
    primary,
    adultFallback,
    genderFallback,
    globalFallback,
    sources: uniqueSources([
      primary,
      adultFallback,
      genderFallback,
      globalFallback,
    ]),
  };
}

export function normalizeGender(gender: string | null | undefined): CrewGender {
  const normalized = String(gender || '').trim().toLowerCase();
  return normalized === 'female' || normalized === 'woman' || normalized === 'f'
    ? 'female'
    : 'male';
}

export function ageToStage(age: number): string {
  if (age <= 24) {
    return '16_24';
  }

  if (age <= 31) {
    return '25_31';
  }

  if (age <= 40) {
    return '32_40';
  }

  if (age <= 55) {
    return '41_55';
  }

  return '56_70';
}

function normalizePortraitIdentity(portraitKey: string | null | undefined): string {
  const rawKey = String(portraitKey || '').trim();
  const numberMatch = rawKey.match(/(\d{1,3})$/);
  const identityNumber = numberMatch ? Number(numberMatch[1]) : 1;
  const boundedIdentity = Math.min(Math.max(identityNumber, 1), 50);

  return String(boundedIdentity).padStart(3, '0');
}

function uniqueSources(paths: string[]): string[] {
  return paths.filter((path, index) => paths.indexOf(path) === index);
}
