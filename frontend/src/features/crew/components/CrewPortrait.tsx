import { useEffect, useState, type CSSProperties } from 'react';
import type { CrewPortraitData } from '../../../types';

interface CrewPortraitProps {
  portrait: CrewPortraitData;
  alt: string;
  size?: 'compact' | 'card' | 'profile';
  status?: string;
  className?: string;
}

export function CrewPortrait({
  portrait,
  alt,
  size = 'card',
  status,
  className = '',
}: CrewPortraitProps) {
  const [source, setSource] = useState(
    size === 'profile' ? portrait.url : portrait.thumbnail_url,
  );
  const [loaded, setLoaded] = useState(false);

  useEffect(() => {
    setSource(size === 'profile' ? portrait.url : portrait.thumbnail_url);
    setLoaded(false);
  }, [portrait.url, portrait.thumbnail_url, size]);

  function useFallback(): void {
    if (source !== portrait.fallback_url) {
      setSource(portrait.fallback_url);
    }
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
        src={source}
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
