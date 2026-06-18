interface CrewConditionMetersProps {
  health: number;
  maximumHealth: number;
  morale: number;
  loyalty: number;
  compact?: boolean;
}

export function CrewConditionMeters({
  health,
  maximumHealth,
  morale,
  loyalty,
  compact = false,
}: CrewConditionMetersProps) {
  return (
    <div className={`crew-condition-grid ${compact ? 'is-compact' : ''}`}>
      <ConditionMeter label="Health" value={health} maximum={maximumHealth} />
      <ConditionMeter label="Morale" value={morale} maximum={100} />
      <ConditionMeter label="Loyalty" value={loyalty} maximum={100} />
    </div>
  );
}

function ConditionMeter({
  label,
  value,
  maximum,
}: {
  label: string;
  value: number;
  maximum: number;
}) {
  const percentage = maximum > 0
    ? Math.max(0, Math.min(100, (value / maximum) * 100))
    : 0;

  return (
    <div className="crew-condition-meter">
      <div>
        <span>{label}</span>
        <strong>{value}/{maximum}</strong>
      </div>
      <div className="crew-condition-track">
        <span style={{ width: `${percentage}%` }} />
      </div>
    </div>
  );
}
