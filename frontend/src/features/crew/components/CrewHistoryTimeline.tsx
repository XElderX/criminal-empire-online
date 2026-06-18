import type { CrewHistoryEntry } from '../../../types';

export function CrewHistoryTimeline({
  entries,
}: {
  entries: CrewHistoryEntry[];
}) {
  if (entries.length === 0) {
    return <p className="muted">No recorded history yet.</p>;
  }

  return (
    <div className="crew-history-timeline">
      {entries.map((entry) => (
        <article key={entry.id}>
          <span className="crew-history-marker" aria-hidden="true" />
          <div>
            <time dateTime={entry.created_at}>
              {new Date(entry.created_at).toLocaleString()}
            </time>
            <strong>{entry.title}</strong>
            <p>{entry.description}</p>
          </div>
        </article>
      ))}
    </div>
  );
}
