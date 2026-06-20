interface LoadoutSlidersProps {
  scores?: Record<string, number>;
}

const LABELS: Record<string, string> = {
  stealth: 'Stealth',
  intimidation: 'Intimidation',
  protection: 'Protection',
  carry_capacity: 'Carry Capacity',
  police_suspicion: 'Police Suspicion',
  mobility: 'Mobility',
  evidence_safety: 'Evidence Safety',
  utility: 'Utility',
};

export function LoadoutSliders({ scores = {} }: LoadoutSlidersProps) {
  return (
    <div className="loadout-sliders">
      {Object.entries(LABELS).map(([key, label]) => {
        const value = Math.max(0, Math.min(100, Number(scores[key] ?? 0)));
        return (
          <div className="loadout-slider" key={key}>
            <span>{label}</span>
            <div className="slider-track" aria-label={`${label}: ${value}`}>
              <div className="slider-fill" style={{ width: `${value}%` }} />
            </div>
            <strong>{value}</strong>
          </div>
        );
      })}
    </div>
  );
}
