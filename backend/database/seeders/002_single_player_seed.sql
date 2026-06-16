UPDATE territories SET unemployment=35, police_presence=45, drug_demand=55, weapon_demand=35, entertainment_demand=40, rent_level=30, corruption_level=35 WHERE name='Old Town';
UPDATE territories SET unemployment=18, police_presence=75, drug_demand=70, weapon_demand=45, entertainment_demand=85, rent_level=80, corruption_level=25 WHERE name='Downtown';
UPDATE territories SET unemployment=28, police_presence=40, drug_demand=65, weapon_demand=60, entertainment_demand=35, rent_level=35, corruption_level=45 WHERE name='Harbor';
UPDATE territories SET unemployment=30, police_presence=35, drug_demand=60, weapon_demand=55, entertainment_demand=25, rent_level=25, corruption_level=50 WHERE name='Industrial Zone';
UPDATE territories SET unemployment=5, police_presence=90, drug_demand=45, weapon_demand=25, entertainment_demand=90, rent_level=100, corruption_level=15 WHERE name='Luxury Hills';

INSERT IGNORE INTO npc_traits (code,name,polarity,description,effects) VALUES
('loyal','Loyal','positive','Loyalty falls more slowly.',JSON_OBJECT('loyalty_loss_multiplier',0.7)),
('skilled_driver','Skilled Driver','positive','Improves driving jobs.',JSON_OBJECT('driving_bonus',12)),
('street_smart','Street Smart','positive','Improves street job planning.',JSON_OBJECT('street_knowledge_bonus',10)),
('fast_learner','Fast Learner','positive','Gains experience faster.',JSON_OBJECT('experience_multiplier',1.25)),
('frugal','Frugal','positive','Has lower personal expenses.',JSON_OBJECT('expense_multiplier',0.75)),
('greedy','Greedy','negative','Expects higher pay.',JSON_OBJECT('salary_multiplier',1.25)),
('reckless','Reckless','mixed','More reward, but more heat and injury risk.',JSON_OBJECT('reward_multiplier',1.1,'heat_multiplier',1.25)),
('addicted','Addicted','negative','Personal expenses and unreliability are higher.',JSON_OBJECT('expense_multiplier',1.5,'success_penalty',5)),
('unreliable','Unreliable','negative','May perform inconsistently.',JSON_OBJECT('success_penalty',8)),
('tough','Tough','positive','Resists injury.',JSON_OBJECT('injury_multiplier',0.7));

INSERT IGNORE INTO npcs (id,first_name,last_name,nickname,age,gender,biography,background,occupation,role,home_territory_id,personal_cash,income_weekly,expenses_weekly,wealth_class,reputation,criminal_reputation,health,max_health,morale,loyalty,status,created_at,updated_at) VALUES
(1001,'Marta','Klein','Marty',42,'female','Runs a tired car wash and gives honest work to people who need cash.','Small business owner','Car wash owner','contract_giver',(SELECT id FROM territories WHERE name='Old Town'),1800,550,420,'working',10,0,100,100,70,55,'active',NOW(),NOW()),
(1002,'Darius','Cole','Forklift',38,'male','Warehouse foreman who values punctual workers.','Industrial worker','Warehouse foreman','contract_giver',(SELECT id FROM territories WHERE name='Industrial Zone'),900,620,480,'working',5,0,100,100,65,50,'active',NOW(),NOW()),
(1003,'Nina','Vale','Nix',31,'female','Courier dispatcher with a network of small local clients.','Delivery work','Dispatcher','contract_giver',(SELECT id FROM territories WHERE name='Old Town'),1200,580,430,'working',8,2,100,100,70,55,'active',NOW(),NOW()),
(1004,'Leon','Briggs','Ledger',47,'male','A quiet debt collector who pays for discreet help.','Former bookmaker','Debt collector','criminal_contact',(SELECT id FROM territories WHERE name='Old Town'),4200,900,650,'working',25,35,100,100,60,45,'active',NOW(),NOW()),
(1101,'Milo','Reed','Sparrow',22,'male','A quick-handed drifter trying to escape small debts.','Unemployed courier','Unemployed','recruit',(SELECT id FROM territories WHERE name='Old Town'),35,0,40,'poor',0,8,100,100,62,58,'active',NOW(),NOW()),
(1102,'Tomas','Venn','Axle',27,'male','Former garage helper who knows every alley in the district.','Garage worker','Mechanic assistant','recruit',(SELECT id FROM territories WHERE name='Industrial Zone'),80,120,65,'poor',2,12,100,100,67,52,'active',NOW(),NOW()),
(1103,'Rina','Moss','Quiet',25,'female','Observant shop worker with a talent for staying unnoticed.','Retail worker','Shop assistant','recruit',(SELECT id FROM territories WHERE name='Old Town'),60,95,55,'poor',1,10,100,100,64,60,'active',NOW(),NOW());

INSERT IGNORE INTO npc_trait_assignments (npc_id,trait_id) SELECT 1101,id FROM npc_traits WHERE code IN ('street_smart','greedy');
INSERT IGNORE INTO npc_trait_assignments (npc_id,trait_id) SELECT 1102,id FROM npc_traits WHERE code IN ('skilled_driver','reckless');
INSERT IGNORE INTO npc_trait_assignments (npc_id,trait_id) SELECT 1103,id FROM npc_traits WHERE code IN ('loyal','frugal');

INSERT IGNORE INTO recruitment_candidates (id,npc_id,territory_id,tier,recruitment_fee,salary_weekly,personal_expenses_weekly,reputation_required,strength,shooting,driving,intelligence,stealth,intimidation,discipline,street_knowledge,endurance,level,experience,available_from,expires_at,status,created_at) VALUES
(2001,1101,(SELECT id FROM territories WHERE name='Old Town'),'street',180,55,40,0,35,18,42,38,58,31,40,61,43,1,0,NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY),'available',NOW()),
(2002,1102,(SELECT id FROM territories WHERE name='Industrial Zone'),'street',320,75,65,0,49,22,67,41,40,45,52,65,55,1,0,NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY),'available',NOW()),
(2003,1103,(SELECT id FROM territories WHERE name='Old Town'),'street',260,65,55,0,29,15,38,55,72,34,61,57,39,1,0,NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY),'available',NOW());

INSERT IGNORE INTO jobs (code,name,description,category,duration_seconds,reward_min,reward_max,energy_cost,heat_min,heat_max,experience_gain,reputation_gain,base_success_rate,difficulty,min_reputation,min_gang_size,required_stat,required_stat_value,active,created_at,updated_at) VALUES
('wash_cars','Wash Cars','Wash cars at Marta''s struggling neighborhood car wash.','legal',60,15,30,3,0,0,2,0,100,1,0,0,NULL,0,1,NOW(),NOW()),
('carry_boxes','Carry Warehouse Boxes','Help unload deliveries at a local warehouse.','legal',120,20,40,5,0,0,3,0,100,1,0,0,NULL,0,1,NOW(),NOW()),
('deliver_packages','Deliver Packages','Deliver small packages across the district.','legal',180,25,50,6,0,1,4,0,96,1,0,0,'driving',0,1,NOW(),NOW()),
('collect_small_debt','Collect a Small Debt','Convince a late debtor to pay a local collector.','criminal',300,40,90,8,1,3,6,1,82,2,0,0,'intimidation',0,1,NOW(),NOW()),
('pickpocket','Pickpocket Pedestrians','Work a crowded street and sell what you lift to an NPC fence.','criminal',240,20,100,7,1,4,6,1,78,2,0,0,'stealth',0,1,NOW(),NOW()),
('steal_bicycle_parts','Steal Bicycle Parts','Strip useful parts and sell them to a back-alley mechanic.','criminal',480,50,140,10,2,6,9,2,70,3,0,0,'stealth',20,1,NOW(),NOW()),
('shoplift_goods','Shoplift Goods','Steal small goods from poorly secured shops.','criminal',480,60,180,10,2,7,10,2,68,3,0,0,'stealth',25,1,NOW(),NOW()),
('storage_shed','Break Into a Storage Shed','Break into a low-security storage shed after dark.','criminal',720,100,250,14,3,9,15,4,60,4,5,0,'stealth',30,1,NOW(),NOW()),
('intimidate_debtor','Intimidate a Local Debtor','Use a crew member or strong intimidation to force payment.','criminal',900,120,300,16,4,10,20,6,62,4,10,1,'intimidation',35,1,NOW(),NOW());

INSERT IGNORE INTO job_opportunities (id,job_id,territory_id,giver_npc_id,source_budget,status,available_from,expires_at,created_at)
SELECT 3000+j.id,j.id,(SELECT id FROM territories WHERE name='Old Town'),
CASE j.code WHEN 'wash_cars' THEN 1001 WHEN 'carry_boxes' THEN 1002 WHEN 'deliver_packages' THEN 1003 ELSE 1004 END,
5000,'available',NOW(),DATE_ADD(NOW(),INTERVAL 90 DAY),NOW() FROM jobs j;
