import type { ReactNode } from 'react';

interface SectionCardProps {
  title?: ReactNode;
  eyebrow?: ReactNode;
  actions?: ReactNode;
  children: ReactNode;
  className?: string;
}

export function SectionCard({
  title,
  eyebrow,
  actions,
  children,
  className = '',
}: SectionCardProps) {
  return (
    <section className={`card section-card noir-section ${className}`.trim()}>
      {(title || eyebrow || actions) && (
        <header className="section-heading-row">
          <div>
            {eyebrow && <p className="eyebrow">{eyebrow}</p>}
            {title && <h2>{title}</h2>}
          </div>
          {actions && <div className="section-actions">{actions}</div>}
        </header>
      )}
      {children}
    </section>
  );
}
