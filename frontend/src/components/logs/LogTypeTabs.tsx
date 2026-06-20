export const LOG_TYPES = ['audit','economy','heat','crime','dirty_job','shop','travel','tutorial','system','error'] as const;
export type LogType = typeof LOG_TYPES[number];
export function LogTypeTabs({ active, onChange }: { active: LogType; onChange: (type: LogType) => void }) {
  return <div className="page-tabs">{LOG_TYPES.map((type) => <button key={type} className={active === type ? 'active' : ''} onClick={() => onChange(type)}>{type.replace('_', ' ')}</button>)}</div>;
}
