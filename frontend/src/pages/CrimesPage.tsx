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
  QuickCrimeEventChoice,
  QuickCrimeOverview,
  QuickCrimeRun,
  QuickCrimeTemplate,
} from '../types';

interface CrimesPageProps {
  onChanged: () => void;
}

type CrimesSubtab = 'explore_leads' | 'quick_crimes' | 'fallback_street_actions';

function locationQuery(): { region: string | null; location: string | null; tab: string | null } {
  const params = new URLSearchParams(window.location.search);
  return {
    region: params.get('region'),
    location: params.get('location'),
    tab: params.get('tab'),
  };
}

function locationQueryString(): string {
  const { region, location } = locationQuery();
  const params = new URLSearchParams();
  if (region) params.set('region', region);
  if (location) params.set('location', location);
  const value = params.toString();
  return value ? `?${value}` : '';
}

export function CrimesPage({ onChanged }: CrimesPageProps) {
  const [overview, setOverview] = useState<CrimeOverview | null>(null);
  const [quickOverview, setQuickOverview] = useState<QuickCrimeOverview | null>(null);
  const [quickResult, setQuickResult] = useState<QuickCrimeRun | null>(null);
  const [selectedOpportunityId, setSelectedOpportunityId] = useState<number | null>(null);
  const [selectedCrewIds, setSelectedCrewIds] = useState<number[]>([]);
  const [selectedQuickCrewIds, setSelectedQuickCrewIds] = useState<number[]>([0]);
  const [selectedEquipmentKeys, setSelectedEquipmentKeys] = useState<string[]>([]);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [busyKey, setBusyKey] = useState('');
  const [activeSubtab, setActiveSubtab] = useState<CrimesSubtab>(() => locationQuery().tab === 'quick_crimes' ? 'quick_crimes' : 'explore_leads');

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
      const [response, quickResponse] = await Promise.all([
        api<CrimeOverview>('/crimes'),
        api<QuickCrimeOverview>(`/quick-crimes${locationQueryString()}`),
      ]);

      setOverview(response);
      setQuickOverview(quickResponse);

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
    if (!overview || !quickOverview) {
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

  async function prepareQuickCrime(template: QuickCrimeTemplate, optionCode: string): Promise<void> {
    await runAction(`quick-prepare-${template.id}-${optionCode}`, async () => {
      const response = await api<{ message: string }>(
        `/quick-crimes/${template.id}/prepare`,
        {
          method: 'POST',
          body: JSON.stringify({ code: optionCode }),
        },
      );

      return response.message;
    });
  }

  async function startQuickCrime(template: QuickCrimeTemplate): Promise<void> {
    await runAction(`quick-start-${template.id}`, async () => {
      const response = await api<{ message: string; run: QuickCrimeRun }>(
        `/quick-crimes/${template.id}/start`,
        {
          method: 'POST',
          body: JSON.stringify({
            idempotency_key: crypto.randomUUID(),
            crew_ids: selectedQuickCrewIds,
            region_slug: locationQuery().region,
            location_slug: locationQuery().location,
          }),
        },
      );

      setQuickResult(response.run);

      return response.run.event
        ? `${response.message} Choose how to handle: ${response.run.event.title}`
        : quickResultMessage(response.run);
    });
  }

  async function decideQuickCrime(run: QuickCrimeRun, choice: QuickCrimeEventChoice): Promise<void> {
    await runAction(`quick-decision-${run.id}-${choice.code}`, async () => {
      const response = await api<{ message: string; run: QuickCrimeRun }>(
        `/quick-crimes/runs/${run.id}/decision`,
        {
          method: 'POST',
          body: JSON.stringify({ decision_code: choice.code }),
        },
      );

      setQuickResult(response.run);

      return quickResultMessage(response.run);
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

  function toggleQuickCrew(id: number): void {
    setSelectedQuickCrewIds((current) => (
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

  if (!overview || !quickOverview) {
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
        eyebrow="v0.6.1 location-aware crime loop"
        title="Crimes Expansion"
        description="Explore leads, quick crimes, and fallback actions with map location context. Nearby hotspots can now modify risk, rewards, and availability."
      />
      <LocationContextHeader />

      {message && <Notice message={message} kind="success" />}
      {error && <Notice message={error} kind="error" />}

      <ActiveEvents runs={overview.active_runs} busyKey={busyKey} onDecision={decide} />
      <section className="card section-card crimes-subtab-shell">
        <div className="card-heading crimes-subtab-heading">
          <div>
            <p className="eyebrow">Crimes workspace</p>
            <h2>Choose a crime flow</h2>
            <p className="muted">Switch between lead-driven operations, the newer quick-crime system, and the simple fallback street-action list.</p>
          </div>
        </div>

        <div className="crimes-subtab-row" role="tablist" aria-label="Crimes sections">
          <button
            className={`btn ${activeSubtab === 'explore_leads' ? 'primary crimes-subtab-active' : ''}`}
            onClick={() => setActiveSubtab('explore_leads')}
            role="tab"
            aria-selected={activeSubtab === 'explore_leads'}
          >
            Explore Leads
          </button>
          <button
            className={`btn ${activeSubtab === 'quick_crimes' ? 'primary crimes-subtab-active' : ''}`}
            onClick={() => setActiveSubtab('quick_crimes')}
            role="tab"
            aria-selected={activeSubtab === 'quick_crimes'}
          >
            Quick Crimes & Street Actions
          </button>
          <button
            className={`btn ${activeSubtab === 'fallback_street_actions' ? 'primary crimes-subtab-active' : ''}`}
            onClick={() => setActiveSubtab('fallback_street_actions')}
            role="tab"
            aria-selected={activeSubtab === 'fallback_street_actions'}
          >
            Fallback Street Actions
          </button>
        </div>
      </section>

      {activeSubtab === 'explore_leads' && (
        <>
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
        </>
      )}

      {activeSubtab === 'quick_crimes' && (
        <QuickCrimesPanel
          overview={quickOverview}
          result={quickResult}
          crew={overview.crew}
          selectedCrewIds={selectedQuickCrewIds}
          busyKey={busyKey}
          onToggleCrew={toggleQuickCrew}
          onPrepare={prepareQuickCrime}
          onStart={startQuickCrime}
          onDecision={decideQuickCrime}
        />
      )}

      {activeSubtab === 'fallback_street_actions' && (
        <LegacyCrimesPanel crimes={overview.legacy_crimes} busyKey={busyKey} onCommit={commitLegacy} />
      )}
    </section>
  );
}

function LocationContextHeader() {
  const { region, location } = locationQuery();
  if (!region && !location) {
    return null;
  }

  return (
    <section className="card location-context-header">
      <p className="eyebrow">Map context</p>
      <h3>Nearby actions for {location ? location.replace(/-/g, ' ') : region?.replace(/-/g, ' ')}</h3>
      <p className="muted">Local rules filter quick crimes and can apply heat, police, danger, reward, and territory modifiers. Use World Map to travel or change hotspot.</p>
      <button className="btn" onClick={() => window.history.pushState({}, '', '/world-map')}>Back to map path</button>
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

function QuickCrimesPanel({
  overview,
  result,
  crew,
  selectedCrewIds,
  busyKey,
  onToggleCrew,
  onPrepare,
  onStart,
  onDecision,
}: {
  overview: QuickCrimeOverview;
  result: QuickCrimeRun | null;
  crew: CrimeCrewOption[];
  selectedCrewIds: number[];
  busyKey: string;
  onToggleCrew: (id: number) => void;
  onPrepare: (template: QuickCrimeTemplate, optionCode: string) => void;
  onStart: (template: QuickCrimeTemplate) => void;
  onDecision: (run: QuickCrimeRun, choice: QuickCrimeEventChoice) => void;
}) {
  const activeEventRuns = overview.active_runs.filter((run) => run.event);

  return (
    <section className="card section-card quick-crimes-panel">
      <div className="card-heading">
        <div>
          <p className="eyebrow">v0.4.2 fallback loop</p>
          <h2>Quick Crimes & Street Actions</h2>
          <p className="muted">
            Smaller cooldown-based actions for early money, XP, fallback leads, and crew practice when no major opportunity is ready.
          </p>
        </div>
      </div>

      {activeEventRuns.length > 0 && (
        <div className="quick-event-stack">
          {activeEventRuns.map((run) => (
            <article key={run.id} className="crime-event-card quick-event-card">
              <p className="eyebrow">Quick crime decision</p>
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
        </div>
      )}

      {result?.result && (
        <QuickCrimeResultPanel run={result} />
      )}

      <div className="quick-crime-grid">
        {overview.data.map((template) => (
          <QuickCrimeCard
            key={template.id}
            template={template}
            crew={crew}
            selectedCrewIds={selectedCrewIds}
            busyKey={busyKey}
            onToggleCrew={onToggleCrew}
            onPrepare={onPrepare}
            onStart={onStart}
          />
        ))}
      </div>

      {overview.history.length > 0 && (
        <section className="quick-history-panel">
          <h3>Recent quick action history</h3>
          <div className="timeline compact-timeline">
            {overview.history.slice(0, 6).map((run) => (
              <article key={run.id}>
                <span>{run.outcome || run.status}</span>
                <strong>{run.result?.title || `Quick run #${run.id}`}</strong>
                <p className="muted">
                  Cash ${Number(run.reward_cash || 0).toLocaleString()} · XP {run.experience_gained || 0} · heat +{run.heat_gained || 0}
                </p>
              </article>
            ))}
          </div>
        </section>
      )}
    </section>
  );
}

function QuickCrimeCard({
  template,
  crew,
  selectedCrewIds,
  busyKey,
  onToggleCrew,
  onPrepare,
  onStart,
}: {
  template: QuickCrimeTemplate;
  crew: CrimeCrewOption[];
  selectedCrewIds: number[];
  busyKey: string;
  onToggleCrew: (id: number) => void;
  onPrepare: (template: QuickCrimeTemplate, optionCode: string) => void;
  onStart: (template: QuickCrimeTemplate) => void;
}) {
  const preparedCodes = template.prepared.map((entry) => entry.code);
  const busy = busyKey.startsWith(`quick-start-${template.id}`)
    || busyKey.startsWith(`quick-prepare-${template.id}`);

  return (
    <article className={`quick-crime-card ${template.can_start ? '' : 'locked'}`}>
      <div className="quick-card-topline">
        <RiskBadge value={template.tier >= 4 ? 'high' : template.tier >= 2 ? 'medium' : 'low'} />
        <span className="info-pill">{template.category.replace(/_/g, ' ')}</span>
      </div>

      <h3>{template.title}</h3>
      <p>{template.description}</p>
      {template.local_location_name && (
        <p className="muted local-context-line">
          Nearby: {template.local_region_name} / {template.local_location_name}
          {template.requires_current_location ? ' · travel required' : ''}
        </p>
      )}

      <dl className="details-grid compact-details-grid">
        <div><dt>Level</dt><dd>{template.min_level}+</dd></div>
        <div><dt>Energy</dt><dd>{template.energy_cost}</dd></div>
        <div><dt>Reward</dt><dd>${template.reward_min}–${template.reward_max}</dd></div>
        <div><dt>XP</dt><dd>{template.xp_min}–{template.xp_max}</dd></div>
        <div><dt>Heat</dt><dd>{template.heat_min}–{template.heat_max}</dd></div>
        <div><dt>Cooldown</dt><dd>{formatCooldown(template.cooldown_seconds)}</dd></div>
      </dl>

      <QuickRequirementList template={template} />

      <div className="quick-actor-selector">
        <h4>Actors</h4>
        <p className="muted">Select the boss and/or crew who take part. Boss skills now count like crew skills.</p>
        <div className="crime-selector-grid">
          {crew.map((member) => (
            <label key={`quick-${template.id}-${member.id}`} className={`selector-chip ${member.is_boss ? 'boss-selector-chip' : ''}`}>
              <input
                type="checkbox"
                checked={selectedCrewIds.includes(member.id)}
                onChange={() => onToggleCrew(member.id)}
              />
              <span>{member.is_boss ? `Boss: ${member.first_name} ${member.last_name}` : (member.nickname || `${member.first_name} ${member.last_name}`)}</span>
              <small>{member.role_code} · drive {member.driving} · stealth {member.stealth} · heat {member.personal_heat || 0}</small>
            </label>
          ))}
        </div>
      </div>

      {template.preparation_options.length > 0 && (
        <div className="quick-prep-list">
          <h4>Light preparation</h4>
          {template.preparation_options.map((option) => {
            const applied = preparedCodes.includes(option.code);
            return (
              <button
                key={option.code}
                className={`prep-option-card ${applied ? 'applied' : ''}`}
                disabled={applied || busyKey !== ''}
                onClick={() => onPrepare(template, option.code)}
              >
                <strong>{option.name}</strong>
                <span>{option.description}</span>
                <small>${option.cash_cost} · {option.energy_cost} energy</small>
              </button>
            );
          })}
        </div>
      )}

      {template.locked_reasons.length > 0 && (
        <div className="missing-item-notice">
          <strong>Locked</strong>
          <ul>
            {template.locked_reasons.map((reason) => (
              <li key={reason}>{reason}</li>
            ))}
          </ul>
        </div>
      )}

      <button
        className="btn primary full-width"
        disabled={!template.can_start || busy || busyKey !== ''}
        onClick={() => onStart(template)}
      >
        {template.cooldown.active
          ? `Cooldown ${template.cooldown.remaining_seconds}s`
          : busy
            ? 'Working…'
            : 'Start quick crime'}
      </button>
    </article>
  );
}

function QuickRequirementList({ template }: { template: QuickCrimeTemplate }) {
  return (
    <div className="quick-requirements">
      <h4>Requirements</h4>
      <div className="chip-row">
        {template.required_all_item_tags.map((tag) => (
          <span key={`all-${tag}`} className="requirement-chip required">Need {tag.replace(/_/g, ' ')}</span>
        ))}
        {template.required_any_item_tags.length > 0 && (
          <span className="requirement-chip required">
            Need one: {template.required_any_item_tags.map((tag) => tag.replace(/_/g, ' ')).join(', ')}
          </span>
        )}
        {template.recommended_item_tags.map((tag) => (
          <span key={`rec-${tag}`} className="requirement-chip">Recommended {tag.replace(/_/g, ' ')}</span>
        ))}
        {template.required_all_item_tags.length === 0 && template.required_any_item_tags.length === 0 && (
          <span className="requirement-chip">No hard item requirement</span>
        )}
      </div>
      {template.missing_items.length > 0 && (
        <p className="muted warning-text">
          Missing: {template.missing_items.map((item) => item.label).join('; ')}. Buy or obtain it through shop/inventory before starting.
        </p>
      )}
    </div>
  );
}

function QuickCrimeResultPanel({ run }: { run: QuickCrimeRun }) {
  const result = run.result;

  if (!result) {
    return null;
  }

  return (
    <article className="quick-result-panel">
      <p className="eyebrow">Latest quick result</p>
      <h3>{result.title || run.outcome || 'Quick action resolved'}</h3>
      <p>{result.description}</p>
      <dl className="details-grid compact-details-grid">
        <div><dt>Cash</dt><dd>${Number(run.reward_cash || 0).toLocaleString()}</dd></div>
        <div><dt>XP</dt><dd>{run.experience_gained}</dd></div>
        <div><dt>Heat</dt><dd>+{run.heat_gained}</dd></div>
        <div><dt>Outcome</dt><dd>{run.outcome}</dd></div>
      </dl>
      {result.loot && result.loot.length > 0 && (
        <p className="muted">Loot: {result.loot.map((item) => `${item.quantity}× ${item.item_code.replace(/_/g, ' ')}`).join(', ')}</p>
      )}
      {result.skill_gains && result.skill_gains.length > 0 && (
        <p className="success-text">
          Rare skill gain: {result.skill_gains.map((gain) => `${String(gain.skill)} +${String(gain.amount)}`).join(', ')}
        </p>
      )}
    </article>
  );
}

function formatCooldown(seconds: number): string {
  if (seconds < 60) {
    return `${seconds}s`;
  }

  const minutes = Math.ceil(seconds / 60);
  return `${minutes}m`;
}

function quickResultMessage(run: QuickCrimeRun): string {
  const title = run.result?.title || run.outcome || 'Quick crime resolved';
  return `${title}. Cash +$${Number(run.reward_cash || 0).toLocaleString()}, XP +${run.experience_gained || 0}, heat +${run.heat_gained || 0}.`;
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
          <h2>Fallback Street Actions</h2>
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
                disabled={busyKey !== '' || crime.cooldown?.active === true}
                onClick={() => onCommit(crime)}
              >
                {crime.cooldown?.active
                  ? `Cooldown ${formatCooldown(crime.cooldown.remaining_seconds)}`
                  : 'Commit quick action'}
              </button>
            )}
          >
            <dl className="details-grid">
              <div><dt>Energy</dt><dd>{crime.energy_cost}</dd></div>
              <div><dt>Chance</dt><dd>{crime.success_rate}%</dd></div>
              <div><dt>Reward</dt><dd>${crime.reward_min}–${crime.reward_max}</dd></div>
              <div><dt>Heat</dt><dd><HeatBadge value={crime.heat_gain} /></dd></div>
              <div><dt>Cooldown</dt><dd>{formatCooldown(crime.cooldown_seconds || 600)}</dd></div>
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
