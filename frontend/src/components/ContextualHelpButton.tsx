import { useEffect, useState } from 'react';
import { api } from '../api/client';
import type { HelpTip, PageName } from '../types';

interface ContextualHelpButtonProps {
  page: PageName;
  onOpenGuide: () => void;
}

export function ContextualHelpButton({ page, onOpenGuide }: ContextualHelpButtonProps) {
  const [tip, setTip] = useState<HelpTip | null>(null);
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;

    async function loadTip() {
      try {
        const response = await api<{ tips: HelpTip[] }>(`/help/tips?page=${encodeURIComponent(page)}`);
        const activeTip = response.tips.find((entry) => !entry.dismissed) ?? response.tips[0] ?? null;

        if (!cancelled) {
          setTip(activeTip);
          setOpen(Boolean(activeTip && !activeTip.dismissed));
        }
      } catch {
        if (!cancelled) {
          setTip(null);
          setOpen(false);
        }
      }
    }

    void loadTip();

    return () => {
      cancelled = true;
    };
  }, [page]);

  async function dismissTip() {
    if (!tip) {
      return;
    }

    setBusy(true);

    try {
      await api(`/help/tips/${encodeURIComponent(tip.tip_key)}/dismiss`, {
        method: 'POST',
        body: JSON.stringify({}),
      });
      setOpen(false);
      setTip({ ...tip, dismissed: true });
    } finally {
      setBusy(false);
    }
  }

  if (!tip) {
    return null;
  }

  return (
    <section className="context-help-wrapper">
      <button className="btn context-help-button" onClick={() => setOpen((value) => !value)}>
        ? {open ? 'Hide help' : 'Help'}
      </button>

      {open && (
        <aside className="card context-help-panel">
          <div className="section-heading-row">
            <div>
              <p className="eyebrow">Page help</p>
              <h3>{tip.title}</h3>
            </div>
            <button className="icon-button" onClick={() => setOpen(false)}>×</button>
          </div>
          <p>{tip.body}</p>
          <div className="button-row">
            <button className="btn" onClick={onOpenGuide}>Open Guide</button>
            <button className="btn" disabled={busy} onClick={() => void dismissTip()}>
              Dismiss tip
            </button>
          </div>
        </aside>
      )}
    </section>
  );
}
