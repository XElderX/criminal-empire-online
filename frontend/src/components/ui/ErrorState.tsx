export function ErrorState({ message, details, onRetry }: { message: string; details?: string; onRetry?: () => void }) {
  return (
    <section className="card section-card error-state-panel">
      <p className="eyebrow">Action blocked</p>
      <h2>{message}</h2>
      {details && <details><summary>Technical details</summary><pre>{details}</pre></details>}
      {onRetry && <button className="btn" type="button" onClick={onRetry}>Retry</button>}
    </section>
  );
}
