export function ShopRequirementBadge({ text, tone = 'neutral' }: { text: string; tone?: 'neutral' | 'warning' | 'success' | 'danger' }) {
  return <span className={`info-pill shop-badge ${tone}`}>{text}</span>;
}
