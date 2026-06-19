import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';
import type { PageName, TutorialState } from '../types';
import { Notice } from './Notice';

interface TutorialPanelProps {
  isOpen: boolean;
  refreshKey: number;
  onClose: () => void;
  onNavigate: (page: PageName) => void;
}

export function TutorialPanel({
  isOpen,
  refreshKey,
  onClose,
  onNavigate,
}: TutorialPanelProps) {
  const [tutorial, setTutorial] = useState<TutorialState | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    void loadTutorial();
  }, [isOpen, refreshKey]);

  const currentModule = useMemo(() => {
    if (!tutorial?.current_step) {
      return null;
    }

    return tutorial.modules.find(
      (module) => module.module_key === tutorial.current_step?.module_key,
    ) ?? null;
  }, [tutorial]);

  async function loadTutorial(): Promise<void> {
    setLoading(true);
    setError(null);

    try {
      const response = await api<{ tutorial: TutorialState }>('/tutorial/current');
      setTutorial(response.tutorial);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Tutorial could not be loaded.');
    } finally {
      setLoading(false);
    }
  }

  async function advance(): Promise<void> {
    if (!tutorial?.current_step) {
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await api<{ tutorial: TutorialState }>('/tutorial/advance', {
        method: 'POST',
        body: JSON.stringify({
          step_code: tutorial.current_step.code,
          acknowledged: tutorial.current_step.requires_acknowledgement
            || tutorial.current_step.objective_type === 'acknowledge'
            || tutorial.current_step.objective_type === 'view_guide',
        }),
      });
      setTutorial(response.tutorial);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Tutorial objective is not complete yet.');
    } finally {
      setLoading(false);
    }
  }

  async function skipTutorial(): Promise<void> {
    if (!window.confirm('Skip this tutorial? Rewards are not granted for skipped tutorials. You can reopen the guide later.')) {
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await api<{ tutorial: TutorialState }>('/tutorial/skip', {
        method: 'POST',
        body: JSON.stringify({}),
      });
      setTutorial(response.tutorial);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : 'Tutorial could not be skipped.');
    } finally {
      setLoading(false);
    }
  }

  if (!isOpen) {
    return null;
  }

  return (
    <aside className="tutorial-panel">
      <div className="tutorial-header">
        <div>
          <p className="eyebrow">Tutorial v{tutorial?.tutorial_version ?? '0.6.5'}</p>
          <h2>{tutorial?.title ?? 'World Tutorial'}</h2>
          {tutorial?.is_update_tutorial && (
            <small>World Systems Update: short update guide for existing players. Your old tutorial progress stays intact.</small>
          )}
        </div>
        <button className="icon-button" onClick={onClose}>×</button>
      </div>

      {loading && <Notice message="Checking tutorial progress…" />}
      {error && <Notice kind="error" message={error} />}

      {tutorial && (
        <>
          <div className="tutorial-progress">
            <div>
              {tutorial.progress.completed} of {tutorial.progress.total} steps
            </div>
            <div className="progress-track">
              <span
                style={{
                  width: `${tutorial.progress.total > 0
                    ? (tutorial.progress.completed / tutorial.progress.total) * 100
                    : 0}%`,
                }}
              />
            </div>
          </div>

          {tutorial.modules.length > 0 && (
            <div className="tutorial-module-list">
              {tutorial.modules.map((module) => (
                <div
                  key={module.module_key}
                  className={`tutorial-module ${currentModule?.module_key === module.module_key ? 'active' : ''}`}
                >
                  <strong>{module.title}</strong>
                  <small>{module.completed}/{module.total}</small>
                </div>
              ))}
            </div>
          )}

          {tutorial.status === 'active' && tutorial.current_step && (
            <section className="tutorial-current card">
              <p className="eyebrow">Current objective</p>
              <h3>{tutorial.current_step.title}</h3>
              <p>{tutorial.current_step.objective}</p>
              <div className="tag-row">
                <span className="status-badge">{tutorial.current_step.module_title}</span>
                <span className="status-badge">{tutorial.current_step.objective_type.replace(/_/g, ' ')}</span>
              </div>

              <div className="button-row">
                <button
                  className="btn primary"
                  onClick={() => onNavigate(tutorial.current_step!.page)}
                >
                  Open {tutorial.current_step.page}
                </button>
                <button className="btn" disabled={loading} onClick={() => void advance()}>
                  {tutorial.current_step.requires_acknowledgement
                    || tutorial.current_step.objective_type === 'acknowledge'
                    ? 'I understand'
                    : 'Check progress'}
                </button>
              </div>
            </section>
          )}

          {tutorial.status === 'completed' && (
            <Notice
              kind="success"
              message="Tutorial completed. You can reopen the guide any time from navigation."
            />
          )}

          {tutorial.status === 'skipped' && (
            <Notice message="Tutorial was skipped. The guide remains available for reference." />
          )}

          <div className="tutorial-step-list">
            {tutorial.steps.map((step, index) => (
              <button
                key={step.code}
                className={`tutorial-step ${step.completed ? 'completed' : ''}`}
                onClick={() => onNavigate(step.page)}
              >
                <span>{step.completed ? '✓' : index + 1}</span>
                <div>
                  <strong>{step.title}</strong>
                  <small>{step.objective}</small>
                </div>
              </button>
            ))}
          </div>

          {tutorial.status === 'active' && (
            <button className="btn danger-button full-width" onClick={() => void skipTutorial()}>
              Skip tutorial
            </button>
          )}
        </>
      )}
    </aside>
  );
}
