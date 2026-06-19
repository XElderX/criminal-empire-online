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

  return (
    <section className="card local-activity-panel">
      <div className="card-heading">
        <div>
          <p className="eyebrow">Local gameplay</p>
          <h3>{activities.location.name}</h3>
          <p className="muted">
            {activities.playerIsHere ? 'You are here.' : 'Travel here to unlock location-required actions.'}
          </p>
        </div>
      </div>

      <div className="location-effect-summary">
        <span className="info-pill">Heat {activities.heatSummary.heat}</span>
        <span className="info-pill">Police {activities.heatSummary.police_pressure}</span>
        <span className="info-pill">Danger {activities.heatSummary.danger_level}</span>
        {activities.territorySummary && (
          <span className="info-pill">{activities.territorySummary.control_label}</span>
        )}
      </div>

      <button className="btn primary full-width" disabled={busy} onClick={onExplore}>
        {busy ? 'Exploring…' : 'Explore Area'}
      </button>
      <p className="muted">Costs 3 energy. Can reveal local rumors, leads, contacts, or warnings. Cooldown prevents refresh farming.</p>

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
        </div>
        {group.route_hint && (
          <button className="btn" onClick={() => onOpenRoute(group.route_hint || '')}>
            Open
          </button>
        )}
      </div>
      {group.preview.length > 0 && (
        <ul className="local-preview-list">
          {group.preview.slice(0, 3).map((entry, index) => (
            <li key={index}>
              <strong>{String(entry.title || entry.name || entry.opportunity_type || 'Local activity')}</strong>
              {entry.description ? <span>{String(entry.description)}</span> : null}
              {Array.isArray(entry.lockedReasons) && entry.lockedReasons.length > 0 ? (
                <small>Locked: {entry.lockedReasons.map((reason) => String(reason)).join(', ')}</small>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </article>
  );
}
