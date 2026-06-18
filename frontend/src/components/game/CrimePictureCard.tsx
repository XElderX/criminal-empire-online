import { useState, type ReactNode } from 'react';

interface CrimePictureCardProps {
  image: string;
  title: string;
  eyebrow?: string;
  description?: string;
  children?: ReactNode;
  actions?: ReactNode;
  fallbackImage?: string;
}

export function CrimePictureCard({
  image,
  title,
  eyebrow,
  description,
  children,
  actions,
  fallbackImage = '/assets/placeholders/default_crime.svg',
}: CrimePictureCardProps) {
  const [source, setSource] = useState(image);

  return (
    <article className="crime-picture-card card">
      <div className="crime-picture-frame">
        <img
          src={source}
          alt=""
          loading="lazy"
          onError={() => setSource(fallbackImage)}
        />
      </div>
      <div className="crime-picture-body">
        {eyebrow && <p className="eyebrow">{eyebrow}</p>}
        <h2>{title}</h2>
        {description && <p>{description}</p>}
        {children}
        {actions && <div className="crime-picture-actions">{actions}</div>}
      </div>
    </article>
  );
}
