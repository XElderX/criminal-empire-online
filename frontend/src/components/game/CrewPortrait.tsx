import { useEffect, useMemo, useState } from 'react';
import { getCrewPortrait } from '../../data/assetManifest';

interface CrewPortraitProps {
  gender?: string | null;
  portraitKey?: string | null;
  age?: number | string | null;
  alt: string;
  className?: string;
  size?: 'compact' | 'card' | 'profile';
}

export function CrewPortrait({
  gender,
  portraitKey,
  age,
  alt,
  className = '',
  size = 'card',
}: CrewPortraitProps) {
  const portrait = useMemo(
    () => getCrewPortrait(gender, portraitKey, age),
    [gender, portraitKey, age],
  );
  const [sourceIndex, setSourceIndex] = useState(0);

  useEffect(() => setSourceIndex(0), [portrait.sources]);

  return (
    <div className={`crew-portrait crew-portrait-${size} ${className}`.trim()}>
      <img
        src={portrait.sources[sourceIndex]}
        alt={alt}
        loading="lazy"
        decoding="async"
        onError={() => setSourceIndex((current) => Math.min(current + 1, portrait.sources.length - 1))}
      />
    </div>
  );
}
