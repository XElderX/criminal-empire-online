-- Criminal Empire Online v0.5 — Heat & Police Pressure Expansion seed data

INSERT INTO heat_reduction_actions (
  code, name, description, target_type, heat_reduction_min, heat_reduction_max,
  investigation_reduction_min, investigation_reduction_max, cash_cost, energy_cost,
  cooldown_seconds, risk_percent, active, created_at, updated_at
) VALUES
('lie_low_short', 'Short Lie Low', 'Stay quiet, avoid attention, and cool the boss/gang heat before pressure escalates.', 'gang', 8, 15, 1, 4, 0, 14, 3600, 0, 1, NOW(), NOW()),
('lie_low_full_day', 'Full Day Lie Low', 'Spend a full quiet day reducing direct police attention and slowing investigations.', 'gang', 15, 25, 4, 9, 60, 25, 21600, 0, 1, NOW(), NOW()),
('bribe_contact', 'Bribe Contact', 'Use a corrupt contact to lower investigation pressure. It costs cash and can backfire.', 'investigation', 4, 10, 10, 30, 250, 4, 7200, 14, 1, NOW(), NOW()),
('pay_lawyer', 'Pay Lawyer', 'Pay legal help to soften arrest and investigation pressure.', 'investigation', 3, 8, 12, 28, 400, 0, 7200, 0, 1, NOW(), NOW()),
('destroy_evidence', 'Destroy Evidence', 'Use cleanup work to reduce evidence-linked heat. Failure may create more suspicion.', 'evidence', 8, 18, 8, 22, 120, 10, 10800, 18, 1, NOW(), NOW()),
('send_crew_away', 'Send High-Heat Crew Away', 'Bench or send away a crew member so their personal heat decays and spillover risk drops.', 'crew', 10, 24, 4, 12, 80, 0, 14400, 8, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  name = VALUES(name), description = VALUES(description), target_type = VALUES(target_type),
  heat_reduction_min = VALUES(heat_reduction_min), heat_reduction_max = VALUES(heat_reduction_max),
  investigation_reduction_min = VALUES(investigation_reduction_min), investigation_reduction_max = VALUES(investigation_reduction_max),
  cash_cost = VALUES(cash_cost), energy_cost = VALUES(energy_cost), cooldown_seconds = VALUES(cooldown_seconds),
  risk_percent = VALUES(risk_percent), active = VALUES(active), updated_at = NOW();

INSERT INTO update_notices (version, title, body, active, created_at, updated_at) VALUES
('0.5.0', 'v0.5 — Heat & Police Pressure Expansion', 'Heat now belongs to the boss, crew, NPCs, gangs, and districts. High-heat crew can trigger investigations and spillover. New reduction actions include stronger lie-low, bribes, lawyers, cleanup, and sending crew away. Dismissing a high-heat crew member can reduce pressure but may create revenge events. A new Heat & Police page explains active investigations, heat logs, risks, and reduction choices.', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title), body = VALUES(body), active = VALUES(active), updated_at = NOW();
