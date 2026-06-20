import { useState } from 'react';
import { api, setToken } from '../api/client';
import type { User } from '../types';
import { Notice } from '../components/Notice';

interface AuthPageProps {
  onAuthenticated: (user: User) => void;
}

export function AuthPage({ onAuthenticated }: AuthPageProps) {
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [form, setForm] = useState({
    username: '',
    email: 'admin@criminal.test',
    password: 'password',
    boss_first_name: '',
    boss_last_name: '',
  });
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function submit(): Promise<void> {
    setLoading(true);
    setError('');

    try {
      const response = await api<{ token: string; user: User }>(
        mode === 'login' ? '/login' : '/register',
        {
          method: 'POST',
          body: JSON.stringify(form),
        },
      );

      setToken(response.token);
      onAuthenticated(response.user);
    } catch (requestError) {
      setError((requestError as Error).message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="auth-shell">
      <section className="card auth-card">
        <p className="eyebrow">Dirty Jobs Expansion</p>
        <h1>Criminal Empire Online</h1>
        <p className="muted">Version 0.7</p>

        {error && <Notice message={error} kind="error" />}

        {mode === 'register' && (
          <>
            <label>
              Username
              <input
                value={form.username}
                onChange={(event) =>
                  setForm({ ...form, username: event.target.value })
                }
              />
            </label>

            <label>
              Boss first name
              <input
                value={form.boss_first_name}
                onChange={(event) =>
                  setForm({ ...form, boss_first_name: event.target.value })
                }
              />
            </label>

            <label>
              Boss surname
              <input
                value={form.boss_last_name}
                onChange={(event) =>
                  setForm({ ...form, boss_last_name: event.target.value })
                }
              />
            </label>
          </>
        )}

        <label>
          Email
          <input
            type="email"
            value={form.email}
            onChange={(event) => setForm({ ...form, email: event.target.value })}
          />
        </label>

        <label>
          Password
          <input
            type="password"
            value={form.password}
            onChange={(event) =>
              setForm({ ...form, password: event.target.value })
            }
          />
        </label>

        <div className="button-row">
          <button className="btn primary" disabled={loading} onClick={submit}>
            {loading ? 'Working…' : mode === 'login' ? 'Login' : 'Create player'}
          </button>
          <button
            className="btn"
            onClick={() => setMode(mode === 'login' ? 'register' : 'login')}
          >
            {mode === 'login' ? 'Create account' : 'Use existing account'}
          </button>
        </div>
      </section>
    </main>
  );
}
