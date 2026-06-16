ALTER TABLE users MODIFY cash BIGINT UNSIGNED NOT NULL DEFAULT 500;
ALTER TABLE users ADD COLUMN IF NOT EXISTS dirty_money BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER bank_cash;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reputation INT NOT NULL DEFAULT 0 AFTER heat;
ALTER TABLE users ADD COLUMN IF NOT EXISTS home_territory_id BIGINT UNSIGNED NULL AFTER leadership;

ALTER TABLE territories ADD COLUMN IF NOT EXISTS unemployment INT NOT NULL DEFAULT 20;
ALTER TABLE territories ADD COLUMN IF NOT EXISTS police_presence INT NOT NULL DEFAULT 50;
ALTER TABLE territories ADD COLUMN IF NOT EXISTS drug_demand INT NOT NULL DEFAULT 50;
ALTER TABLE territories ADD COLUMN IF NOT EXISTS weapon_demand INT NOT NULL DEFAULT 40;
ALTER TABLE territories ADD COLUMN IF NOT EXISTS entertainment_demand INT NOT NULL DEFAULT 50;
ALTER TABLE territories ADD COLUMN IF NOT EXISTS rent_level INT NOT NULL DEFAULT 50;
ALTER TABLE territories ADD COLUMN IF NOT EXISTS corruption_level INT NOT NULL DEFAULT 20;
ALTER TABLE territories ADD COLUMN IF NOT EXISTS public_fear INT NOT NULL DEFAULT 10;

CREATE TABLE IF NOT EXISTS npcs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(60) NOT NULL,
  last_name VARCHAR(60) NOT NULL,
  nickname VARCHAR(80) NULL,
  age TINYINT UNSIGNED NOT NULL,
  gender VARCHAR(20) NULL,
  biography TEXT NULL,
  background VARCHAR(160) NULL,
  occupation VARCHAR(100) NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'civilian',
  home_territory_id BIGINT UNSIGNED NULL,
  workplace_business_id BIGINT UNSIGNED NULL,
  personal_cash BIGINT UNSIGNED NOT NULL DEFAULT 0,
  bank_cash BIGINT UNSIGNED NOT NULL DEFAULT 0,
  income_weekly BIGINT UNSIGNED NOT NULL DEFAULT 0,
  expenses_weekly BIGINT UNSIGNED NOT NULL DEFAULT 0,
  wealth_class VARCHAR(30) NOT NULL DEFAULT 'poor',
  reputation INT NOT NULL DEFAULT 0,
  criminal_reputation INT NOT NULL DEFAULT 0,
  health INT NOT NULL DEFAULT 100,
  max_health INT NOT NULL DEFAULT 100,
  morale INT NOT NULL DEFAULT 60,
  loyalty INT NOT NULL DEFAULT 50,
  status VARCHAR(30) NOT NULL DEFAULT 'active',
  arrested_until DATETIME NULL,
  alive TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX(role), INDEX(home_territory_id), INDEX(status),
  FOREIGN KEY (home_territory_id) REFERENCES territories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS npc_traits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  polarity ENUM('positive','negative','mixed') NOT NULL,
  description VARCHAR(255) NOT NULL,
  effects JSON NOT NULL
);

CREATE TABLE IF NOT EXISTS npc_trait_assignments (
  npc_id BIGINT UNSIGNED NOT NULL,
  trait_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY(npc_id, trait_id),
  FOREIGN KEY (npc_id) REFERENCES npcs(id) ON DELETE CASCADE,
  FOREIGN KEY (trait_id) REFERENCES npc_traits(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS recruitment_candidates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  npc_id BIGINT UNSIGNED NOT NULL UNIQUE,
  territory_id BIGINT UNSIGNED NOT NULL,
  tier ENUM('street','experienced','specialist','veteran') NOT NULL DEFAULT 'street',
  recruitment_fee BIGINT UNSIGNED NOT NULL,
  salary_weekly BIGINT UNSIGNED NOT NULL,
  personal_expenses_weekly BIGINT UNSIGNED NOT NULL DEFAULT 25,
  reputation_required INT NOT NULL DEFAULT 0,
  strength INT NOT NULL, shooting INT NOT NULL, driving INT NOT NULL,
  intelligence INT NOT NULL, stealth INT NOT NULL, intimidation INT NOT NULL,
  discipline INT NOT NULL, street_knowledge INT NOT NULL, endurance INT NOT NULL,
  level INT NOT NULL DEFAULT 1, experience BIGINT UNSIGNED NOT NULL DEFAULT 0,
  available_from DATETIME NOT NULL, expires_at DATETIME NULL,
  status ENUM('available','hired','expired') NOT NULL DEFAULT 'available',
  hired_by_user_id BIGINT UNSIGNED NULL, hired_at DATETIME NULL,
  created_at TIMESTAMP NULL,
  INDEX(status, expires_at), INDEX(territory_id),
  FOREIGN KEY (npc_id) REFERENCES npcs(id) ON DELETE CASCADE,
  FOREIGN KEY (territory_id) REFERENCES territories(id),
  FOREIGN KEY (hired_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS player_gang_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  npc_id BIGINT UNSIGNED NOT NULL UNIQUE,
  recruitment_candidate_id BIGINT UNSIGNED NULL,
  salary_weekly BIGINT UNSIGNED NOT NULL,
  unpaid_salary BIGINT UNSIGNED NOT NULL DEFAULT 0,
  personal_expenses_weekly BIGINT UNSIGNED NOT NULL DEFAULT 25,
  strength INT NOT NULL, shooting INT NOT NULL, driving INT NOT NULL,
  intelligence INT NOT NULL, stealth INT NOT NULL, intimidation INT NOT NULL,
  discipline INT NOT NULL, street_knowledge INT NOT NULL, endurance INT NOT NULL,
  level INT NOT NULL DEFAULT 1, experience BIGINT UNSIGNED NOT NULL DEFAULT 0,
  health INT NOT NULL DEFAULT 100, max_health INT NOT NULL DEFAULT 100,
  morale INT NOT NULL DEFAULT 60, loyalty INT NOT NULL DEFAULT 50,
  status ENUM('active','busy','injured','arrested','dismissed','dead') NOT NULL DEFAULT 'active',
  current_assignment_type VARCHAR(40) NULL, current_assignment_id BIGINT UNSIGNED NULL,
  recruited_at DATETIME NOT NULL, last_salary_at DATETIME NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  INDEX(user_id,status),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (npc_id) REFERENCES npcs(id),
  FOREIGN KEY (recruitment_candidate_id) REFERENCES recruitment_candidates(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  category ENUM('legal','criminal') NOT NULL,
  duration_seconds INT UNSIGNED NOT NULL,
  reward_min BIGINT UNSIGNED NOT NULL,
  reward_max BIGINT UNSIGNED NOT NULL,
  energy_cost INT NOT NULL DEFAULT 0,
  heat_min INT NOT NULL DEFAULT 0,
  heat_max INT NOT NULL DEFAULT 0,
  experience_gain INT NOT NULL DEFAULT 0,
  reputation_gain INT NOT NULL DEFAULT 0,
  base_success_rate INT NOT NULL DEFAULT 100,
  difficulty INT NOT NULL DEFAULT 1,
  min_reputation INT NOT NULL DEFAULT 0,
  min_gang_size INT NOT NULL DEFAULT 0,
  required_stat VARCHAR(40) NULL,
  required_stat_value INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS job_opportunities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_id BIGINT UNSIGNED NOT NULL,
  territory_id BIGINT UNSIGNED NOT NULL,
  giver_npc_id BIGINT UNSIGNED NULL,
  target_npc_id BIGINT UNSIGNED NULL,
  target_business_id BIGINT UNSIGNED NULL,
  source_budget BIGINT UNSIGNED NULL,
  status ENUM('available','active','completed','expired') NOT NULL DEFAULT 'available',
  available_from DATETIME NOT NULL, expires_at DATETIME NULL,
  created_at TIMESTAMP NULL,
  INDEX(status, expires_at), INDEX(territory_id),
  FOREIGN KEY(job_id) REFERENCES jobs(id), FOREIGN KEY(territory_id) REFERENCES territories(id),
  FOREIGN KEY(giver_npc_id) REFERENCES npcs(id) ON DELETE SET NULL,
  FOREIGN KEY(target_npc_id) REFERENCES npcs(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS job_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  opportunity_id BIGINT UNSIGNED NOT NULL,
  idempotency_key CHAR(36) NOT NULL,
  status ENUM('active','completed','failed','cancelled') NOT NULL DEFAULT 'active',
  started_at DATETIME NOT NULL, completes_at DATETIME NOT NULL, completed_at DATETIME NULL,
  success TINYINT(1) NULL, reward BIGINT UNSIGNED NOT NULL DEFAULT 0,
  heat_gained INT NOT NULL DEFAULT 0, result JSON NULL,
  created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
  UNIQUE KEY unique_idempotency(user_id,idempotency_key),
  INDEX(user_id,status),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(opportunity_id) REFERENCES job_opportunities(id)
);

CREATE TABLE IF NOT EXISTS job_assignments (
  job_run_id BIGINT UNSIGNED NOT NULL,
  gang_member_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY(job_run_id,gang_member_id),
  UNIQUE KEY one_active_assignment(gang_member_id, job_run_id),
  FOREIGN KEY(job_run_id) REFERENCES job_runs(id) ON DELETE CASCADE,
  FOREIGN KEY(gang_member_id) REFERENCES player_gang_members(id)
);

CREATE TABLE IF NOT EXISTS economy_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(60) NOT NULL,
  amount BIGINT UNSIGNED NOT NULL,
  currency VARCHAR(20) NOT NULL DEFAULT 'cash',
  source_type VARCHAR(50) NULL, source_id BIGINT UNSIGNED NULL,
  destination_type VARCHAR(50) NULL, destination_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL, npc_id BIGINT UNSIGNED NULL,
  business_id BIGINT UNSIGNED NULL, gang_member_id BIGINT UNSIGNED NULL,
  job_run_id BIGINT UNSIGNED NULL, territory_id BIGINT UNSIGNED NULL,
  description VARCHAR(255) NOT NULL,
  game_date DATETIME NULL, created_at TIMESTAMP NULL,
  INDEX(category), INDEX(user_id), INDEX(created_at),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY(npc_id) REFERENCES npcs(id) ON DELETE SET NULL,
  FOREIGN KEY(gang_member_id) REFERENCES player_gang_members(id) ON DELETE SET NULL,
  FOREIGN KEY(job_run_id) REFERENCES job_runs(id) ON DELETE SET NULL,
  FOREIGN KEY(territory_id) REFERENCES territories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS salary_payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gang_member_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  amount_due BIGINT UNSIGNED NOT NULL, amount_paid BIGINT UNSIGNED NOT NULL,
  unpaid_added BIGINT UNSIGNED NOT NULL DEFAULT 0,
  processed_at DATETIME NOT NULL,
  FOREIGN KEY(gang_member_id) REFERENCES player_gang_members(id),
  FOREIGN KEY(user_id) REFERENCES users(id)
);
