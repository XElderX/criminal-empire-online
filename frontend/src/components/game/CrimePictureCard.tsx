import { useState, type ReactNode } from 'react';

interface CrimePictureCardProps {
  image: string;
  title: string;
  eyebrow?: string;
  description?: string;
  children?: ReactNode;
  actions?: ReactNode;
}

export function CrimePictureCard({
  image,
  title,
  eyebrow,
  description,
  children,
  actions,
}: CrimePictureCardProps) {
  const [source, setSource] = useState(image);

  return (
    <article className="crime-picture-card card">
      <div className="crime-picture-frame">
        <img
          src={source}
          alt=""
          loading="lazy"
          onError={() => setSource('/assets/placeholders/default_crime.webp')}
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
