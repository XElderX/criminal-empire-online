import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import { Notice } from '../components/Notice';
import { CrimePictureCard } from '../components/game/CrimePictureCard';
import { EmptyState } from '../components/game/EmptyState';
import { GameHeader } from '../components/game/GameHeader';
import { HeatBadge } from '../components/game/HeatBadge';
import { ItemIconCard } from '../components/game/ItemIconCard';
import { RiskBadge } from '../components/game/RiskBadge';
import { getCrimeImage } from '../data/assetManifest';
import type {
  Crime,
  CrimeCrewOption,
  CrimeEquipmentOption,
  CrimeEventChoice,
  CrimeOverview,
  CrimeOpportunity,
  CrimePreparationOption,
  CrimeRun,
} from '../types';

interface CrimesPageProps {
  onChanged: () => void;
}

export function CrimesPage({ onChanged }: CrimesPageProps) {
  const [overview, setOverview] = useState<CrimeOverview | null>(null);
  const [selectedOpportunityId, setSelectedOpportunityId] = useState<number | null>(null);
  const [selectedCrewIds, setSelectedCrewIds] = useState<number[]>([]);
  const [selectedEquipmentKeys, setSelectedEquipmentKeys] = useState<string[]>([]);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [busyKey, setBusyKey] = useState('');

  useEffect(() => {
    void load();
  }, []);

  const selectedOpportunity = useMemo(() => {
    if (!overview || selectedOpportunityId === null) {
      return null;
    }

    return overview.opportunities.find((opportunity) => opportunity.id === selectedOpportunityId) || null;
  }, [overview, selectedOpportunityId]);

  async function load(): Promise<void> {
    try {
      const response = await api<CrimeOverview>('/crimes');
      setOverview(response);

      if (selectedOpportunityId === null && response.opportunities.length > 0) {
        setSelectedOpportunityId(response.opportunities[0].id);
      }
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  async function runAction(key: string, action: () => Promise<string>): Promise<void> {
    setBusyKey(key);
    setMessage('');
    setError('');

    try {
      setMessage(await action());
      await load();
      onChanged();
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setBusyKey('');
    }
  }

  async function explore(locationCode: string): Promise<void> {
    await runAction(`explore-${locationCode}`, async () => {
      const response = await api<{ message: string; opportunity: CrimeOpportunity }>(
        `/crime-locations/${locationCode}/explore`,
        { method: 'POST' },
      );
      setSelectedOpportunityId(response.opportunity.id);
      return response.message;
    });
  }

  async function investigate(opportunity: CrimeOpportunity): Promise<void> {
    await runAction(`investigate-${opportunity.id}`, async () => {
      const response = await api<{ message: string }>(
        `/crime-opportunities/${opportunity.id}/investigate`,
        { method: 'POST' },
      );
      return response.message;
    });
  }

  async function prepare(opportunity: CrimeOpportunity, option: CrimePreparationOption): Promise<void> {
    await runAction(`prepare-${opportunity.id}-${option.code}`, async () => {
      const response = await api<{ message: string }>(
        `/crime-opportunities/${opportunity.id}/prepare`,
        {
          method: 'POST',
          body: JSON.stringify({ code: option.code }),
        },
      );
      return response.message;
    });
  }

  async function assignCrew(opportunity: CrimeOpportunity): Promise<void> {
    await runAction(`assign-crew-${opportunity.id}`, async () => {
      const assignments = selectedCrewIds.map((gangMemberId, index) => ({
        gang_member_id: gangMemberId,
        role_code: opportunity.recommended_roles[index] || 'helper',
      }));
      const response = await api<{ message: string }>(
        `/crime-opportunities/${opportunity.id}/assign-crew`,
        {
          method: 'POST',
          body: JSON.stringify({ assignments }),
        },
      );
      return response.message;
    });
  }

  async function assignEquipment(opportunity: CrimeOpportunity): Promise<void> {
    if (!overview) {
      return;
    }

    await runAction(`assign-equipment-${opportunity.id}`, async () => {
      const equipment = overview.equipment
        .filter((entry) => selectedEquipmentKeys.includes(equipmentKey(entry)))
        .map((entry) => ({
          asset_type: entry.asset_type,
          asset_id: entry.asset_id,
          quantity: 1,
        }));
      const response = await api<{ message: string }>(
        `/crime-opportunities/${opportunity.id}/assign-equipment`,
        {
          method: 'POST',
          body: JSON.stringify({ equipment }),
        },
      );
      return response.message;
    });
  }

  async function execute(opportunity: CrimeOpportunity): Promise<void> {
    await runAction(`execute-${opportunity.id}`, async () => {
      const response = await api<{ message: string; run: CrimeRun }>(
        `/crime-opportunities/${opportunity.id}/start`,
        {
          method: 'POST',
          body: JSON.stringify({ idempotency_key: crypto.randomUUID() }),
        },
      );

      return response.run.event
        ? `${response.message} Choose how to handle: ${response.run.event.title}`
        : resultMessage(response.run);
    });
  }

  async function decide(run: CrimeRun, choice: CrimeEventChoice): Promise<void> {
    await runAction(`decision-${run.id}-${choice.code}`, async () => {
      const response = await api<{ message: string; run: CrimeRun }>(
        `/crime-runs/${run.id}/decision`,
        {
          method: 'POST',
          body: JSON.stringify({ decision_code: choice.code }),
        },
      );
      return resultMessage(response.run);
    });
  }

  async function abandon(opportunity: CrimeOpportunity): Promise<void> {
    await runAction(`abandon-${opportunity.id}`, async () => {
      const response = await api<{ message: string }>(
        `/crime-opportunities/${opportunity.id}/abandon`,
        { method: 'POST' },
      );
      return response.message;
    });
  }

  async function commitLegacy(crime: Crime): Promise<void> {
    await runAction(`legacy-${crime.id}`, async () => {
      const response = await api<{ success: boolean; reward: number; heat_gained: number }>(
        `/crimes/${crime.id}/commit`,
        { method: 'POST' },
      );

      return response.success
        ? `Street action succeeded. You earned $${response.reward}.`
        : `Street action failed. Heat increased by ${response.heat_gained}.`;
    });
  }

  function toggleCrew(id: number): void {
    setSelectedCrewIds((current) => (
      current.includes(id)
        ? current.filter((entry) => entry !== id)
        : [...current, id]
    ));
  }

  function toggleEquipment(entry: CrimeEquipmentOption): void {
    const key = equipmentKey(entry);
    setSelectedEquipmentKeys((current) => (
      current.includes(key)
        ? current.filter((item) => item !== key)
        : [...current, key]
    ));
  }

  if (!overview) {
    return (
      <section className="page-section crimes-v04-page">
        <GameHeader
          eyebrow="Crimes expansion"
          title="Crimes"
          description={error
            ? 'The crimes expansion could not load from the API.'
            : 'Loading crime opportunities, contacts, and city leads…'}
        />
        {error && <Notice message={error} kind="error" />}
        {!error && <p className="muted">Waiting for crime opportunities, contacts, and city leads…</p>}
        {error && (
          <button className="btn" disabled={busyKey !== ''} onClick={() => void load()}>
            Retry loading crimes
          </button>
        )}
      </section>
    );
  }

  return (
    <section className="page-section crimes-v04-page">
      <GameHeader
        eyebrow="v0.4 crime loop"
        title="Crimes Expansion"
        description="Explore locations, uncover rumors, investigate leads, prepare, assign crew/equipment, handle random events, and let NPCs remember the outcome."
      />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <ActiveEvents runs={overview.active_runs} busyKey={busyKey} onDecision={decide} />

      <section className="card section-card crime-loop-panel">
        <div className="card-heading">
          <div>
            <p className="eyebrow">Step 1</p>
            <h2>Explore for opportunities</h2>
            <p className="muted">Crimes are now discovered through places and NPC information instead of all being static buttons.</p>
          </div>
        </div>
        <div className="crime-location-grid">
          {overview.locations.map((location) => (
            <article key={location.code} className="crime-location-card">
              <RiskBadge value={location.risk_level} />
              <h3>{location.name}</h3>
              <p>{location.description}</p>
              <dl className="details-grid compact-details-grid">
                <div><dt>Energy</dt><dd>{location.energy_cost}</dd></div>
                <div><dt>Cash</dt><dd>${location.cash_cost}</dd></div>
                <div><dt>Level</dt><dd>{location.min_level}+</dd></div>
              </dl>
              <button
                className="btn primary full-width"
                disabled={!location.can_explore || busyKey === `explore-${location.code}`}
                onClick={() => explore(location.code)}
              >
                {busyKey === `explore-${location.code}` ? 'Searching…' : 'Explore leads'}
              </button>
              {location.blocked_reason && <p className="muted warning-text">{location.blocked_reason}</p>}
            </article>
          ))}
        </div>
      </section>

      <div className="content-grid two-columns crime-v04-main-grid">
        <section className="card section-card">
          <div className="card-heading">
            <div>
              <p className="eyebrow">Step 2</p>
              <h2>Known opportunities</h2>
              <p className="muted">Rumors and leads must be investigated before serious execution.</p>
            </div>
          </div>

          {overview.opportunities.length === 0 && (
            <EmptyState title="No known opportunities" message="Explore a location to generate a rumor, lead, or confirmed opening." />
          )}

          <div className="crime-opportunity-list">
            {overview.opportunities.map((opportunity) => (
              <OpportunityCard
                key={opportunity.id}
                opportunity={opportunity}
                selected={selectedOpportunityId === opportunity.id}
                busyKey={busyKey}
                onSelect={() => setSelectedOpportunityId(opportunity.id)}
                onInvestigate={() => investigate(opportunity)}
                onExecute={() => execute(opportunity)}
                onAbandon={() => abandon(opportunity)}
              />
            ))}
          </div>
        </section>

        <section className="card section-card">
          <div className="card-heading">
            <div>
              <p className="eyebrow">Step 3</p>
              <h2>Prepare selected opportunity</h2>
              <p className="muted">Preparation improves odds but never guarantees a clean result.</p>
            </div>
          </div>

          {!selectedOpportunity && (
            <EmptyState title="Select an opportunity" message="Pick a rumor or lead to see preparation, crew, and equipment options." />
          )}

          {selectedOpportunity && (
            <OpportunityDetail
              opportunity={selectedOpportunity}
              crew={overview.crew}
              equipment={overview.equipment}
              selectedCrewIds={selectedCrewIds}
              selectedEquipmentKeys={selectedEquipmentKeys}
              busyKey={busyKey}
              onToggleCrew={toggleCrew}
              onToggleEquipment={toggleEquipment}
              onPrepare={(option) => prepare(selectedOpportunity, option)}
              onAssignCrew={() => assignCrew(selectedOpportunity)}
              onAssignEquipment={() => assignEquipment(selectedOpportunity)}
            />
          )}
        </section>
      </div>

      <div className="content-grid two-columns">
        <ContactsPanel contacts={overview.contacts} />
        <HistoryPanel runs={overview.history} />
      </div>

      <LegacyCrimesPanel crimes={overview.legacy_crimes} busyKey={busyKey} onCommit={commitLegacy} />
    </section>
  );
}

function OpportunityCard({
  opportunity,
  selected,
  busyKey,
  onSelect,
  onInvestigate,
  onExecute,
  onAbandon,
}: {
  opportunity: CrimeOpportunity;
  selected: boolean;
  busyKey: string;
  onSelect: () => void;
  onInvestigate: () => void;
  onExecute: () => void;
  onAbandon: () => void;
}) {
  const image = getCrimeImage(opportunity.code || opportunity.title);

  return (
    <article className={`crime-opportunity-card ${selected ? 'selected' : ''}`}>
      <button className="crime-opportunity-select" onClick={onSelect}>
        <img src={image} alt="" />
        <div>
          <span className={`info-pill ${opportunity.information_level}`}>{opportunity.information_level}</span>
          <h3>{opportunity.title}</h3>
          <p>{opportunity.briefing}</p>
        </div>
      </button>

      <dl className="details-grid compact-details-grid">
        <div><dt>District</dt><dd>{opportunity.territory_name || 'Unknown'}</dd></div>
        <div><dt>Source</dt><dd>{opportunity.source_name || opportunity.source_type}</dd></div>
        <div><dt>Reward</dt><dd>${opportunity.estimated_reward_min}–${opportunity.estimated_reward_max}</dd></div>
        <div><dt>Heat</dt><dd>{opportunity.estimated_heat_min}–{opportunity.estimated_heat_max}</dd></div>
      </dl>

      <div className="crime-action-row">
        <button className="btn" disabled={!opportunity.can_investigate || busyKey !== ''} onClick={onInvestigate}>
          Investigate
        </button>
        <button className="btn primary" disabled={!opportunity.can_execute || busyKey !== ''} onClick={onExecute}>
          Execute
        </button>
        <button className="btn danger" disabled={busyKey !== ''} onClick={onAbandon}>
          Abandon
        </button>
      </div>
    </article>
  );
}

function OpportunityDetail({
  opportunity,
  crew,
  equipment,
  selectedCrewIds,
  selectedEquipmentKeys,
  busyKey,
  onToggleCrew,
  onToggleEquipment,
  onPrepare,
  onAssignCrew,
  onAssignEquipment,
}: {
  opportunity: CrimeOpportunity;
  crew: CrimeCrewOption[];
  equipment: CrimeEquipmentOption[];
  selectedCrewIds: number[];
  selectedEquipmentKeys: string[];
  busyKey: string;
  onToggleCrew: (id: number) => void;
  onToggleEquipment: (entry: CrimeEquipmentOption) => void;
  onPrepare: (option: CrimePreparationOption) => void;
  onAssignCrew: () => void;
  onAssignEquipment: () => void;
}) {
  const appliedPreparationCodes = opportunity.preparations.map((preparation) => preparation.code);

  return (
    <div className="crime-detail-panel">
      <div className="crime-briefing-box">
        <span className={`info-pill ${opportunity.quality}`}>Quality: {opportunity.quality}</span>
        <h3>{opportunity.title}</h3>
        <p>{opportunity.source_description}</p>
        <p className="muted">Target: {opportunity.target_name || 'Unknown'} · Energy to execute: {opportunity.energy_cost}</p>
      </div>

      <section>
        <h3>Preparation actions</h3>
        <div className="crime-prep-grid">
          {opportunity.preparation_options.map((option) => {
            const applied = appliedPreparationCodes.includes(option.code);
            return (
              <button
                key={option.code}
                className={`prep-option-card ${applied ? 'applied' : ''}`}
                disabled={applied || !opportunity.can_prepare || busyKey !== ''}
                onClick={() => onPrepare(option)}
              >
                <strong>{option.name}</strong>
                <span>{option.description}</span>
                <small>${option.cash_cost} · {option.energy_cost} energy</small>
              </button>
            );
          })}
        </div>
      </section>

      <section>
        <div className="card-heading small-heading">
          <h3>Crew assignment</h3>
          <button className="btn" disabled={!opportunity.can_prepare || busyKey !== ''} onClick={onAssignCrew}>
            Save crew
          </button>
        </div>
        <div className="crime-selector-grid">
          {crew.map((member) => (
            <label key={member.id} className="selector-chip">
              <input
                type="checkbox"
                checked={selectedCrewIds.includes(member.id)}
                onChange={() => onToggleCrew(member.id)}
              />
              <span>{member.nickname || `${member.first_name} ${member.last_name}`}</span>
              <small>{member.role_code} · loyalty {member.loyalty}%</small>
            </label>
          ))}
        </div>
        {opportunity.assignments.length > 0 && (
          <p className="muted">Assigned: {opportunity.assignments.map((assignment) => assignment.nickname || assignment.first_name || `#${assignment.gang_member_id}`).join(', ')}</p>
        )}
      </section>

      <section>
        <div className="card-heading small-heading">
          <h3>Equipment selection</h3>
          <button className="btn" disabled={!opportunity.can_prepare || busyKey !== ''} onClick={onAssignEquipment}>
            Save equipment
          </button>
        </div>
        <div className="crime-equipment-grid">
          {equipment.map((entry) => (
            <label key={equipmentKey(entry)} className="equipment-selector-card">
              <input
                type="checkbox"
                checked={selectedEquipmentKeys.includes(equipmentKey(entry))}
                onChange={() => onToggleEquipment(entry)}
              />
              <ItemIconCard
                item={{
                  id: entry.asset_id,
                  name: entry.name,
                  category: entry.category || entry.asset_type,
                  quantity: entry.available_quantity,
                }}
                compact
              />
            </label>
          ))}
        </div>
      </section>
    </div>
  );
}

function ActiveEvents({
  runs,
  busyKey,
  onDecision,
}: {
  runs: CrimeRun[];
  busyKey: string;
  onDecision: (run: CrimeRun, choice: CrimeEventChoice) => void;
}) {
  const eventRuns = runs.filter((run) => run.event);

  if (eventRuns.length === 0) {
    return null;
  }

  return (
    <section className="card section-card crime-event-panel">
      <p className="eyebrow">Active crime event</p>
      <h2>Decision needed</h2>
      {eventRuns.map((run) => (
        <article key={run.id} className="crime-event-card">
          <h3>{run.event?.title}</h3>
          <p>{run.event?.description}</p>
          <div className="crime-action-row">
            {run.event?.choices.map((choice) => (
              <button
                key={choice.code}
                className="btn"
                disabled={busyKey !== ''}
                onClick={() => onDecision(run, choice)}
              >
                {choice.label}
              </button>
            ))}
          </div>
        </article>
      ))}
    </section>
  );
}

function ContactsPanel({ contacts }: { contacts: CrimeOverview['contacts'] }) {
  return (
    <section className="card section-card">
      <div className="card-heading">
        <div>
          <p className="eyebrow">NPC memory</p>
          <h2>Known contacts</h2>
        </div>
      </div>
      {contacts.length === 0 && <EmptyState title="No contacts yet" message="Explore locations to meet recurring NPCs." />}
      <div className="contact-list-v04">
        {contacts.map((contact) => (
          <article key={`${contact.npc_id}-${contact.id}`} className="contact-row-v04">
            {contact.portrait && <img src={contact.portrait.thumbnail_url} alt="" />}
            <div>
              <strong>{contact.full_name}</strong>
              <p className="muted">{contact.relationship_type} · trust {contact.trust} · suspicion {contact.suspicion}</p>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}

function HistoryPanel({ runs }: { runs: CrimeRun[] }) {
  return (
    <section className="card section-card">
      <div className="card-heading">
        <div>
          <p className="eyebrow">Timeline</p>
          <h2>Recent outcomes</h2>
        </div>
      </div>
      {runs.length === 0 && <EmptyState title="No crime outcomes yet" message="Resolved crime runs will appear here." />}
      <div className="timeline compact-timeline">
        {runs.map((run) => (
          <article key={run.id}>
            <span>{run.outcome || run.status}</span>
            <strong>{run.result?.title || `Run #${run.id}`}</strong>
            <p className="muted">Dirty cash ${Number(run.reward_dirty_cash || 0).toLocaleString()} · heat +{run.heat_gained || 0}</p>
          </article>
        ))}
      </div>
    </section>
  );
}

function LegacyCrimesPanel({
  crimes,
  busyKey,
  onCommit,
}: {
  crimes: Crime[];
  busyKey: string;
  onCommit: (crime: Crime) => void;
}) {
  if (crimes.length === 0) {
    return null;
  }

  return (
    <section className="card section-card legacy-crimes-panel">
      <div className="card-heading">
        <div>
          <p className="eyebrow">Fallback street actions</p>
          <h2>Quick crimes</h2>
          <p className="muted">Old simple street actions remain for backward compatibility and early low-risk play.</p>
        </div>
      </div>
      <div className="card-grid picture-card-grid">
        {crimes.map((crime) => (
          <CrimePictureCard
            key={crime.id}
            image={getCrimeImage(crime.name)}
            title={crime.name}
            eyebrow={`Difficulty ${crime.success_rate}% base chance`}
            description={crime.description}
            actions={(
              <button
                className="btn primary full-width"
                disabled={busyKey !== ''}
                onClick={() => onCommit(crime)}
              >
                Commit quick action
              </button>
            )}
          >
            <dl className="details-grid">
              <div><dt>Energy</dt><dd>{crime.energy_cost}</dd></div>
              <div><dt>Chance</dt><dd>{crime.success_rate}%</dd></div>
              <div><dt>Reward</dt><dd>${crime.reward_min}–${crime.reward_max}</dd></div>
              <div><dt>Heat</dt><dd><HeatBadge value={crime.heat_gain} /></dd></div>
            </dl>
          </CrimePictureCard>
        ))}
      </div>
    </section>
  );
}

function equipmentKey(entry: CrimeEquipmentOption): string {
  return `${entry.asset_type}-${entry.asset_id}`;
}

function resultMessage(run: CrimeRun): string {
  const title = run.result?.title || run.outcome || 'Crime resolved';
  const reward = Number(run.reward_dirty_cash || 0).toLocaleString();
  return `${title}. Dirty cash +$${reward}, heat +${run.heat_gained || 0}.`;
}
