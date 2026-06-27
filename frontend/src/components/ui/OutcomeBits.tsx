export function InfoHighlight({ label, value, tone = 'info' }: { label: string; value: string | number; tone?: string }) {
  return <span className={`info-highlight ${tone}`}><small>{label}</small><strong>{value}</strong></span>;
}

export function StatDelta({ label, value }: { label: string; value: number }) {
  const tone = value > 0 ? 'positive' : value < 0 ? 'negative' : 'neutral';
  return <span className={`stat-delta ${tone}`}>{label}: {value > 0 ? '+' : ''}{value}</span>;
}

export function WarningCallout({ title, message }: { title: string; message: string }) {
  return <div className="warning-callout"><strong>{title}</strong><p>{message}</p></div>;
}

export function NextActionCard({ title, message }: { title: string; message: string }) {
  return <article className="next-action-card"><strong>{title}</strong><p>{message}</p></article>;
}
