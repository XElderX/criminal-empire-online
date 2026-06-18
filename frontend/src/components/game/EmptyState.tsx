interface EmptyStateProps {
  title: string;
  message?: string;
}

export function EmptyState({ title, message }: EmptyStateProps) {
  return (
    <div className="card empty-state game-empty-state">
      <div className="empty-state-mark" aria-hidden="true">◇</div>
      <div>
        <h2>{title}</h2>
        {message && <p className="muted">{message}</p>}
      </div>
    </div>
  );
}
