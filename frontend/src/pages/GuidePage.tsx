import { useEffect, useState } from 'react';
import { api } from '../api/client';
import type { GuidePayload } from '../types';
import { EmptyState } from '../components/game/EmptyState';
import { LoadingState } from '../components/game/LoadingState';

export function GuidePage() {
  const [guide, setGuide] = useState<GuidePayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    async function loadGuide() {
      try {
        await api('/tutorial/objective', {
          method: 'POST',
          body: JSON.stringify({ action_type: 'view_page', payload: { page: 'guide' } }),
        });
        const response = await api<{ guide: GuidePayload }>('/tutorial/guide');
        setGuide(response.guide);
      } catch (caught) {
        setError(caught instanceof Error ? caught.message : 'Guide could not be loaded.');
      } finally {
        setLoading(false);
      }
    }

    void loadGuide();
  }, []);

  if (loading) {
    return <LoadingState />;
  }

  if (error || !guide) {
    return <EmptyState title="Guide unavailable" message={error ?? 'No guide sections were returned.'} />;
  }

  return (
    <section className="guide-page page-stack">
      <header className="hero-panel">
        <p className="eyebrow">Player guidance</p>
        <h1>{guide.title}</h1>
        <p>{guide.release_title}</p>
      </header>

      <div className="guide-grid">
        {guide.sections.map((section, index) => (
          <article key={section.key} className="card guide-section-card">
            <span className="status-badge">{index + 1}</span>
            <h3>{section.title}</h3>
            <p>{section.body}</p>
          </article>
        ))}
      </div>
    </section>
  );
}
