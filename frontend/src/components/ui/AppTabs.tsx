import type { ReactNode } from 'react';

export interface AppTabDefinition<T extends string> {
  key: T;
  label: string;
  count?: number;
  disabled?: boolean;
  description?: string;
}

export function AppTabs<T extends string>({
  tabs,
  active,
  onChange,
  ariaLabel,
}: {
  tabs: AppTabDefinition<T>[];
  active: T;
  onChange: (key: T) => void;
  ariaLabel: string;
}) {
  return (
    <div className="app-tabs" role="tablist" aria-label={ariaLabel}>
      {tabs.map((tab) => (
        <button
          key={tab.key}
          className={`app-tab-button ${active === tab.key ? 'active' : ''}`}
          type="button"
          role="tab"
          aria-selected={active === tab.key}
          disabled={tab.disabled}
          title={tab.description}
          onClick={() => onChange(tab.key)}
        >
          <span>{tab.label}</span>
          {typeof tab.count === 'number' && <span className="tab-count-badge">{tab.count}</span>}
        </button>
      ))}
    </div>
  );
}

export function AppTabPanel({ active, children }: { active: boolean; children: ReactNode }) {
  if (!active) return null;
  return <div className="app-tab-panel" role="tabpanel">{children}</div>;
}
