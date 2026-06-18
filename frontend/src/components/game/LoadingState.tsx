interface LoadingStateProps {
  label?: string;
  count?: number;
}

export function LoadingState({ label = 'Loading city data…', count = 3 }: LoadingStateProps) {
  return (
    <div className="game-loading" aria-label={label}>
      {Array.from({ length: count }).map((_, index) => (
        <div className="crew-card-skeleton" key={index} />
      ))}
    </div>
  );
}
