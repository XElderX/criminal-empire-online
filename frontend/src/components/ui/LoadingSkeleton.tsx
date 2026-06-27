export function LoadingSkeleton({ title = 'Loading city data…', rows = 3 }: { title?: string; rows?: number }) {
  return (
    <section className="card section-card loading-skeleton" aria-busy="true">
      <h2>{title}</h2>
      {Array.from({ length: rows }).map((_, index) => <span className="skeleton-line" key={index} />)}
    </section>
  );
}
