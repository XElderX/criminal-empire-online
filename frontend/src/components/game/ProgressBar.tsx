interface ProgressBarProps {
  value: number;
  max?: number;
  label?: string;
  tone?: 'money' | 'heat' | 'energy' | 'xp' | 'risk' | 'blue' | 'purple' | 'neutral';
}

export function ProgressBar({
  value,
  max = 100,
  label,
  tone = 'neutral',
}: ProgressBarProps) {
  const maximum = Math.max(Number(max) || 1, 1);
  const current = Math.max(0, Math.min(Number(value) || 0, maximum));
  const percent = Math.round((current / maximum) * 100);

  return (
    <div className={`game-progress game-progress-${tone}`}>
      {label && (
        <div className="game-progress-label">
          <span>{label}</span>
          <strong>{current}/{maximum}</strong>
        </div>
      )}
      <div className="game-progress-track" aria-hidden="true">
        <span style={{ width: `${percent}%` }} />
      </div>
    </div>
  );
}
