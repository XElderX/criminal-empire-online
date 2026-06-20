export function CarryInventoryGrid({ carried = [] }: { carried?: Array<{ name: string; quantity?: number; carry_units?: number }> }) {
  return (
    <div className="carry-panel">
      <div className="loadout-score-heading">
        <span className="eyebrow">Carried inventory</span>
        <strong>{carried.length} carried item{carried.length === 1 ? '' : 's'}</strong>
      </div>
      {carried.length === 0 ? (
        <p className="muted">No carried items. Useful gear can unlock options, but extra bulk increases risk.</p>
      ) : (
        <div className="carry-grid">
          {carried.map((item) => (
            <article className="carry-card" key={item.name}>
              <strong>{item.name}</strong>
              <span>Qty {item.quantity ?? 1}</span>
              <small>{Number(item.carry_units ?? 1)} carry units</small>
            </article>
          ))}
        </div>
      )}
    </div>
  );
}
