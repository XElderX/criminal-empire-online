export function ItemEffectBadge({ label, value }: { label: string; value: string | number }) {
  return <span className="item-effect-badge">{label}: {value}</span>;
}
