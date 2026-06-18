import { useEffect, useMemo, useState, type CSSProperties } from 'react';
import { getCrewPortrait } from '../../../data/assetManifest';
import type { CrewPortraitData } from '../../../types';

interface CrewPortraitProps {
  portrait: CrewPortraitData;
  alt: string;
  size?: 'compact' | 'card' | 'profile';
  status?: string;
  className?: string;
  gender?: string | null;
  age?: number | string | null;
}

export function CrewPortrait({
  portrait,
  alt,
  size = 'card',
  status,
  className = '',
  gender,
  age,
}: CrewPortraitProps) {
  const sourceList = useMemo(() => {
    const v036Portrait = getCrewPortrait(
      gender || portrait.gender,
      portrait.identity_key,
      age,
    );

    const backendSource = size === 'profile' ? portrait.url : portrait.thumbnail_url;

    return [
      v036Portrait.primary,
      v036Portrait.adultFallback,
      backendSource,
      portrait.fallback_url,
      v036Portrait.genderFallback,
      v036Portrait.globalFallback,
    ].filter((source, index, sources) => Boolean(source) && sources.indexOf(source) === index);
  }, [age, gender, portrait, size]);

  const [sourceIndex, setSourceIndex] = useState(0);
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    setSourceIndex(0);
    setLoaded(false);
  }, [sourceList]);

  function useFallback(): void {
    setSourceIndex((current) => Math.min(current + 1, sourceList.length - 1));
  }

  return (
    <div
      className={`crew-portrait crew-portrait-${size} ${className}`.trim()}
      style={{
        '--portrait-x': `${portrait.focal_x}%`,
        '--portrait-y': `${portrait.focal_y}%`,
      } as CSSProperties}
    >
      {!loaded && <span className="crew-portrait-skeleton" aria-hidden="true" />}
      <img
        src={sourceList[sourceIndex]}
        alt={alt}
        loading="lazy"
        decoding="async"
        onLoad={() => setLoaded(true)}
        onError={useFallback}
      />
      {status && <span className="crew-portrait-status">{status}</span>}
    </div>
  );
}
