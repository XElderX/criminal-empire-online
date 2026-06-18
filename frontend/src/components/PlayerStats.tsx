import type { User } from '../types';
import { StatCard } from './game/StatCard';

export function PlayerStats({ user }: { user: User }) {
  const experienceForNextLevel = Math.max(user.level * 350, 100);
  const experienceIntoLevel = user.experience % experienceForNextLevel;

  return (
    <section className="stats-grid noir-stats-grid">
      <StatCard
        label="Cash"
        value={`$${Number(user.cash).toLocaleString()}`}
        detail={`Bank: $${Number(user.bank_cash).toLocaleString()}`}
        tone="money"
      />
      <StatCard
        label="Dirty money"
        value={`$${Number(user.dirty_money).toLocaleString()}`}
        detail="Needs laundering later"
        tone="purple"
      />
      <StatCard
        label="Energy"
        value={`${user.energy}/${user.max_energy}`}
        detail="Limits actions and preparation"
        tone="energy"
        progress={{ value: user.energy, max: user.max_energy }}
      />
      <StatCard
        label="Heat"
        value={user.heat}
        detail="Police pressure"
        tone="heat"
        progress={{ value: user.heat, max: 100 }}
      />
      <StatCard
        label="Level / XP"
        value={`Level ${user.level}`}
        detail={`${user.experience.toLocaleString()} total XP`}
        tone="xp"
        progress={{ value: experienceIntoLevel, max: experienceForNextLevel }}
      />
      <StatCard
        label="Reputation"
        value={user.reputation}
        detail="Criminal contacts trust"
        tone="blue"
      />
    </section>
  );
}
