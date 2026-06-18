interface CrewExperienceBarProps {
  level: number;
  current: number;
  required: number;
  progress: number;
}

export function CrewExperienceBar({
  level,
  current,
  required,
  progress,
}: CrewExperienceBarProps) {
  const safeProgress = Math.max(0, Math.min(100, progress));

  return (
    <div className="crew-progress-block">
      <div className="crew-progress-heading">
        <span>Level {level}</span>
        <small>{current.toLocaleString()} / {required.toLocaleString()} XP</small>
      </div>
      <div className="crew-progress-track" aria-label={`Experience ${safeProgress}%`}>
        <span style={{ width: `${safeProgress}%` }} />
      </div>
    </div>
  );
}
