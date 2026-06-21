import { CrewPortrait } from '../game/CrewPortrait';
import type { LoadoutCharacterSummary } from '../../types';

interface LoadoutCharacterSelectorProps {
  characters: LoadoutCharacterSummary[];
  selectedKey: string;
  onSelect: (character: LoadoutCharacterSummary) => void;
}

export function LoadoutCharacterSelector({ characters, selectedKey, onSelect }: LoadoutCharacterSelectorProps) {
  return (
    <section className="loadout-character-selector" aria-label="Choose character loadout">
      <div className="section-heading-row">
        <div>
          <p className="eyebrow">Loadout owner</p>
          <h2>Choose boss or crew</h2>
          <p className="muted">Pick who you are setting up. Gear and carried items below apply only to the selected character.</p>
        </div>
      </div>
      <div className="loadout-character-strip">
        {characters.map((character) => {
          const selected = character.key === selectedKey;
          const roleName = typeof character.role === 'object' && character.role && 'name' in character.role ? character.role.name : character.character_type;
          return (
            <button
              type="button"
              key={character.key}
              className={`loadout-character-card ${selected ? 'selected' : ''} ${character.loadout_status || ''}`}
              onClick={() => onSelect(character)}
            >
              <CrewPortrait
                gender={character.gender}
                portraitKey={character.portrait_set_key || undefined}
                age={character.age}
                alt={character.display_name}
                size="compact"
              />
              <span className="loadout-character-main">
                <strong>{character.display_name}</strong>
                <small>{roleName} · {character.status}</small>
                <span className="character-mini-stats">
                  <span>HP {character.health}/{character.max_health}</span>
                  <span>Heat {character.personal_heat ?? 0}</span>
                </span>
              </span>
              <span className="loadout-character-badges">
                <span>{character.equipped_count} equipped</span>
                <span>{character.carried_count} carried</span>
                {character.loadout_warning_count > 0 && <span className="danger">{character.loadout_warning_count} warnings</span>}
              </span>
            </button>
          );
        })}
      </div>
    </section>
  );
}
