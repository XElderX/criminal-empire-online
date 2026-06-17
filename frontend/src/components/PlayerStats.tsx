import type { User } from '../types';

export function PlayerStats({ user }: { user: User }) {
  return (
    <section className="stats-grid">
      <article className="card stat-card">
        <span className="muted">Cash</span>
        <strong>${Number(user.cash).toLocaleString()}</strong>
        <small>Bank: ${Number(user.bank_cash).toLocaleString()}</small>
      </article>

      <article className="card stat-card">
        <span className="muted">Dirty money</span>
        <strong>${Number(user.dirty_money).toLocaleString()}</strong>
        <small>Needs laundering later</small>
      </article>

      <article className="card stat-card">
        <span className="muted">Energy</span>
        <strong>
          {user.energy}/{user.max_energy}
        </strong>
        <small>Limits actions and preparation</small>
      </article>

      <article className="card stat-card">
        <span className="muted">Heat</span>
        <strong className={user.heat > 15 ? 'danger' : ''}>{user.heat}</strong>
        <small>Higher heat increases police pressure</small>
      </article>

      <article className="card stat-card">
        <span className="muted">Level / XP</span>
        <strong>
          {user.level} / {user.experience}
        </strong>
        <small>Unlocks harder operations</small>
      </article>

      <article className="card stat-card">
        <span className="muted">Reputation</span>
        <strong>{user.reputation}</strong>
        <small>Builds trust with criminal contacts</small>
      </article>
    </section>
  );
}
