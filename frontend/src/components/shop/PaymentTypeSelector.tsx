export type PaymentType = 'cash' | 'bank' | 'dirty_money';

export function PaymentTypeSelector({
  value,
  options,
  onChange,
}: {
  value: PaymentType;
  options: PaymentType[];
  onChange: (value: PaymentType) => void;
}) {
  if (options.length <= 1) {
    return <p className="muted">Payment: {formatPayment(options[0] || value)}</p>;
  }

  return (
    <label className="payment-type-selector">
      Payment type
      <select value={value} onChange={(event) => onChange(event.target.value as PaymentType)}>
        {options.map((option) => <option key={option} value={option}>{formatPayment(option)}</option>)}
      </select>
    </label>
  );
}

function formatPayment(value: PaymentType): string {
  if (value === 'dirty_money') return 'Dirty money';
  if (value === 'bank') return 'Bank money';
  return 'Clean cash';
}
