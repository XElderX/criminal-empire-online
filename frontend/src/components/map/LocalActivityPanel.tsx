import type { LocationActivitiesResponse, LocalActivityGroup } from '../../types/worldMap';

export function LocalActivityPanel({
  activities,
  busy,
  onExplore,
  onOpenRoute,
}: {
  activities: LocationActivitiesResponse | null;
  busy: boolean;
  onExplore: () => void;
  onOpenRoute: (routeHint: string) => void;
}) {
  if (!activities) {
    return (
      <section className="card local-activity-panel">
        <p className="muted">Select a hotspot to load nearby activities.</p>
      </section>
    );
  }

  const travelPurpose = activities.travelPurpose;

  return (
    <section className="card local-activity-panel">
      <div className="card-heading">
        <div>
          <p className="eyebrow">Local gameplay</p>
          <h3>{activities.location.name}</h3>
          <p className={activities.playerIsHere ? 'success-text' : 'warning-text'}>
            {activities.playerIsHere ? 'You are here. Local actions are available.' : 'Travel here to unlock location-required actions.'}
          </p>
        </div>
      </div>

      {travelPurpose && (
        <div className="local-purpose-box">
          <strong>{travelPurpose.headline}</strong>
          {travelPurpose.unlocks.length > 0 && (
            <ul>
              {travelPurpose.unlocks.map((item) => <li key={item}>{item}</li>)}
            </ul>
          )}
          {travelPurpose.remote.length > 0 && (
            <p className="muted">Remote view: {travelPurpose.remote.join(', ')}</p>
          )}
        </div>
      )}

      <div className="location-effect-summary">
        <span className="info-pill">Heat {activities.heatSummary.heat}</span>
        <span className="info-pill">Police {activities.heatSummary.police_pressure}</span>
        <span className="info-pill">Danger {activities.heatSummary.danger_level}</span>
        {activities.territorySummary && (
          <span className="info-pill">{activities.territorySummary.control_label}</span>
        )}
      </div>

      <button className="btn primary full-width" disabled={busy || !activities.playerIsHere} onClick={onExplore}>
        {busy ? 'Exploring…' : activities.playerIsHere ? 'Explore Area' : 'Travel here to explore'}
      </button>
      <p className="muted">Exploration is local. It costs energy and can reveal rumors, leads, contacts, or warnings.</p>

      <div className="local-activity-groups" aria-label="Quick Crimes Nearby and Dirty Jobs Nearby">
        {activities.activityGroups.length === 0 && (
          <p className="muted">No local activities found yet. Explore the area or try another hotspot.</p>
        )}
        {activities.activityGroups.map((group) => (
          <LocalActivityGroupCard key={group.key} group={group} onOpenRoute={onOpenRoute} />
        ))}
      </div>
    </section>
  );
}

function LocalActivityGroupCard({
  group,
  onOpenRoute,
}: {
  group: LocalActivityGroup;
  onOpenRoute: (routeHint: string) => void;
}) {
  return (
    <article className="local-activity-group-card">
      <div className="card-heading small-heading">
        <div>
          <h4>{group.title}</h4>
          <p className="muted">{group.availableCount} available · {group.lockedCount} locked</p>
          <p className={group.localPresenceSatisfied === false ? 'warning-text' : 'success-text'}>
            {group.availabilityLabel || (group.localPresenceSatisfied === false ? 'Requires local presence' : 'Available here')}
          </p>
        </div>
        {group.route_hint && (
          <button className="btn" onClick={() => onOpenRoute(group.route_hint || '')}>
            Open
          </button>
        )}
      </div>
      {group.preview.length > 0 && (
        <ul className="local-preview-list">
          {group.preview.slice(0, 4).map((entry, index) => {
            const lockedReasons = Array.isArray(entry.lockedReasons) ? entry.lockedReasons : [];
            const status = String(entry.localPresenceStatus || '');

            return (
              <li key={index}>
                <span className="local-preview-row">
                  <span>
                    <strong>{String(entry.title || entry.name || entry.opportunity_type || 'Local activity')}</strong>
                    {entry.description ? <span>{String(entry.description)}</span> : null}
                  </span>
                  {String(entry.route_hint || '').startsWith('shops') ? (
                    <button className="btn compact-btn" onClick={() => onOpenRoute(String(entry.route_hint))}>Open shop</button>
                  ) : null}
                </span>
                {status === 'travel_required' ? <small>Requires local presence. {String(entry.travelHint || '')}</small> : null}
                {lockedReasons.length > 0 ? (
                  <small>Locked: {lockedReasons.map((reason) => String(reason)).join(', ')}</small>
                ) : null}
              </li>
            );
          })}
        </ul>
      )}
    </article>
  );
}
