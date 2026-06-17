import { useEffect, useState } from 'react';
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
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function loadTutorial(): Promise<void> {
    try {
      const response = await api<{ tutorial: TutorialState }>('/tutorial');
      setTutorial(response.tutorial);
    } catch (requestError) {
      setError((requestError as Error).message);
    }
  }

  useEffect(() => {
    void loadTutorial();
  }, [refreshKey]);

  useEffect(() => {
    if (isOpen) {
      void loadTutorial();
    }
  }, [isOpen]);

  async function advance(): Promise<void> {
    if (!tutorial?.current_step) {
      return;
    }

    setLoading(true);
    setMessage('');
    setError('');

    try {
      const response = await api<{ tutorial: TutorialState }>(
        '/tutorial/advance',
        {
          method: 'POST',
          body: JSON.stringify({
            step_code: tutorial.current_step.code,
            acknowledged: tutorial.current_step.requires_acknowledgement,
          }),
        },
      );

      setTutorial(response.tutorial);

      if ((response.tutorial.reward_granted || 0) > 0) {
        setMessage(
          `Tutorial complete. You received $${response.tutorial.reward_granted}.`,
        );
      } else {
        setMessage('Tutorial progress updated.');
      }
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  async function skipTutorial(): Promise<void> {
    const confirmed = window.confirm(
      'Skip the guided tutorial? No tutorial completion reward will be granted.',
    );

    if (!confirmed) {
      return;
    }

    setLoading(true);
    setError('');

    try {
      const response = await api<{ tutorial: TutorialState }>(
        '/tutorial/skip',
        { method: 'POST' },
      );
      setTutorial(response.tutorial);
      setMessage('Tutorial skipped. You can reopen this guide at any time.');
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  if (!isOpen) {
    return null;
  }

  return (
    <aside className="tutorial-panel">
      <header className="tutorial-header">
        <div>
          <p className="eyebrow">New-player guide</p>
          <h2>Rise from the street</h2>
        </div>
        <button className="icon-button" onClick={onClose} aria-label="Close tutorial">
          ×
        </button>
      </header>

      {error && <Notice message={error} kind="error" />}
      {message && <Notice message={message} kind="success" />}

      {!tutorial && <p className="muted">Loading tutorial progress…</p>}

      {tutorial && (
        <>
          <div className="tutorial-progress">
            <div>
              {tutorial.progress.completed} of {tutorial.progress.total} steps
            </div>
            <div className="progress-track">
              <span
                style={{
                  width: `${
                    (tutorial.progress.completed / tutorial.progress.total) * 100
                  }%`,
                }}
              />
            </div>
          </div>

          {tutorial.status === 'active' && tutorial.current_step && (
            <section className="tutorial-current card">
              <p className="eyebrow">Current objective</p>
              <h3>{tutorial.current_step.title}</h3>
              <p>{tutorial.current_step.objective}</p>

              <div className="button-row">
                <button
                  className="btn primary"
                  onClick={() => onNavigate(tutorial.current_step!.page)}
                >
                  Open {tutorial.current_step.page}
                </button>
                <button className="btn" disabled={loading} onClick={advance}>
                  {tutorial.current_step.requires_acknowledgement
                    ? 'I understand'
                    : 'Check progress'}
                </button>
              </div>
            </section>
          )}

          {tutorial.status === 'completed' && (
            <Notice
              kind="success"
              message="Tutorial completed. The full step history remains available below."
            />
          )}

          {tutorial.status === 'skipped' && (
            <Notice
              message="Tutorial was skipped. The guide remains available for reference."
            />
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
            <button className="btn danger-button full-width" onClick={skipTutorial}>
              Skip tutorial
            </button>
          )}
        </>
      )}
    </aside>
  );
}
