export type LogType = 'system' | 'audit' | 'economy' | 'heat' | 'crime' | 'dirty_job' | 'shop' | 'travel' | 'tutorial' | 'error';

const LOG_TYPES: Array<{ value: LogType; label: string }> = [
  { value: 'audit', label: 'Audit' },
  { value: 'system', label: 'System' },
  { value: 'economy', label: 'Economy' },
  { value: 'heat', label: 'Heat' },
  { value: 'crime', label: 'Crime' },
  { value: 'dirty_job', label: 'Dirty Jobs' },
  { value: 'shop', label: 'Shops' },
  { value: 'travel', label: 'Travel' },
  { value: 'tutorial', label: 'Tutorial' },
  { value: 'error', label: 'Errors' },
];

export function LogTypeTabs({ active, onChange }: { active: LogType; onChange: (type: LogType) => void }) {
  return (
    <div className="page-tabs log-type-tabs" role="tablist" aria-label="Log type">
      {LOG_TYPES.map((entry) => (
        <button
          key={entry.value}
          type="button"
          role="tab"
          aria-selected={active === entry.value}
          className={`page-tab ${active === entry.value ? 'active' : ''}`}
          onClick={() => onChange(entry.value)}
        >
          {entry.label}
        </button>
      ))}
    </div>
  );
}
