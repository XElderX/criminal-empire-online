export function LoadoutWarningList({ warnings = [] }: { warnings?: string[] }) {
  if (warnings.length === 0) {
    return <p className="muted">No loadout warnings.</p>;
  }

  return (
    <ul className="loadout-warning-list">
      {warnings.map((warning) => <li key={warning}>{warning}</li>)}
    </ul>
  );
}
