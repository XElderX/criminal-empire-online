import type { ReactNode } from 'react';
import { ProgressBar } from './ProgressBar';

interface StatCardProps {
  label: string;
  value: ReactNode;
  detail?: ReactNode;
  tone?: 'money' | 'heat' | 'energy' | 'xp' | 'risk' | 'blue' | 'purple' | 'neutral';
  progress?: {
    value: number;
    max: number;
  };
}

export function StatCard({ label, value, detail, tone = 'neutral', progress }: StatCardProps) {
  return (
    <article className={`card stat-card noir-stat stat-${tone}`}>
      <span className="muted">{label}</span>
      <strong>{value}</strong>
      {detail && <small>{detail}</small>}
      {progress && (
        <ProgressBar value={progress.value} max={progress.max} tone={tone} />
      )}
    </article>
  );
}
