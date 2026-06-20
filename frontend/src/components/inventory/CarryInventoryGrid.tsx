export function CarryInventoryGrid({ carried = [] }: { carried?: Array<{ name: string; quantity?: number; carry_units?: number }> }) {
  if (carried.length === 0) {
    return <p className="muted">No carried items. Carrying useful gear can unlock options but adds bulk.</p>;
  }

  return (
    <div className="carry-grid">
      {carried.map((item) => (
        <article className="carry-card" key={item.name}>
          <strong>{item.name}</strong>
          <span>Qty {item.quantity ?? 1}</span>
          <small>{Number(item.carry_units ?? 1)} carry units</small>
        </article>
      ))}
    </div>
  );
}
