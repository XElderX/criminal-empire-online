import type { CrewTrait } from '../../../types';

export function CrewTraitList({
  traits,
  compact = false,
}: {
  traits: CrewTrait[];
  compact?: boolean;
}) {
  const visibleTraits = compact ? traits.slice(0, 4) : traits;

  if (visibleTraits.length === 0) {
    return <p className="muted crew-empty-line">No known traits.</p>;
  }

  return (
    <div className="crew-trait-list">
      {visibleTraits.map((trait) => (
        <span
          className={`crew-trait crew-trait-${trait.polarity}`}
          title={`${trait.description} ${formatEffects(trait.effects)}`.trim()}
          key={trait.code}
        >
          {trait.name}
        </span>
      ))}
    </div>
  );
}

function formatEffects(effects: Record<string, number>): string {
  return Object.entries(effects)
    .map(([key, value]) => `${key.split('_').join(' ')}: ${value}`)
    .join(', ');
}
