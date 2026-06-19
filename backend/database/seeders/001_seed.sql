INSERT INTO users (id, username, email, password, role, cash, energy, max_energy, created_at, updated_at)
SELECT 1, 'admin', 'admin@criminal.test', '$argon2id$v=19$m=65536,t=4,p=1$d0xjbXhPdUdrS2VHV1ppUg$SJWriyC4tANOiEbXd6GOhC2czgnc3PJAHi0+yiB1q5o', 'admin', 5000000, 100, 100, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE id = 1 OR email = 'admin@criminal.test');

INSERT INTO crimes (name,description,risk_level,energy_cost,success_rate,reward_min,reward_max,heat_gain,experience_gain,created_at,updated_at)
SELECT 'Pickpocket','Low risk street crime.',1,5,90,100,500,1,5,NOW(),NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM crimes WHERE name = 'Pickpocket');
INSERT INTO crimes (name,description,risk_level,energy_cost,success_rate,reward_min,reward_max,heat_gain,experience_gain,created_at,updated_at)
SELECT 'Protection Collection','Collect protection money from small shops.',2,10,80,800,2500,3,12,NOW(),NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM crimes WHERE name = 'Protection Collection');

INSERT INTO weapons (name,class,damage,accuracy,reliability,concealment,maintenance_cost,price,created_at,updated_at)
SELECT 'Saturday Night Special','pistol',12,55,60,90,25,1200,NOW(),NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM weapons WHERE name = 'Saturday Night Special');
INSERT INTO weapons (name,class,damage,accuracy,reliability,concealment,maintenance_cost,price,created_at,updated_at)
SELECT '9mm Pistol','pistol',18,70,80,80,50,4500,NOW(),NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM weapons WHERE name = '9mm Pistol');
INSERT INTO weapons (name,class,damage,accuracy,reliability,concealment,maintenance_cost,price,created_at,updated_at)
SELECT 'Sawed-off Shotgun','shotgun',35,45,65,45,120,12000,NOW(),NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM weapons WHERE name = 'Sawed-off Shotgun');
INSERT INTO weapons (name,class,damage,accuracy,reliability,concealment,maintenance_cost,price,created_at,updated_at)
SELECT 'Compact SMG','smg',28,62,70,55,180,30000,NOW(),NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM weapons WHERE name = 'Compact SMG');
INSERT INTO weapons (name,class,damage,accuracy,reliability,concealment,maintenance_cost,price,created_at,updated_at)
SELECT 'Assault Rifle','assault_rifle',45,68,75,20,350,85000,NOW(),NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM weapons WHERE name = 'Assault Rifle');

INSERT INTO drugs (id,name,base_price,risk_factor,quality_modifier)
SELECT 1,'Cannabis',120,1,1.00
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM drugs WHERE id = 1);
INSERT INTO drugs (id,name,base_price,risk_factor,quality_modifier)
SELECT 2,'Cocaine',3000,5,1.20
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM drugs WHERE id = 2);
INSERT INTO drugs (id,name,base_price,risk_factor,quality_modifier)
SELECT 3,'Heroin',2500,6,1.15
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM drugs WHERE id = 3);
INSERT INTO drugs (id,name,base_price,risk_factor,quality_modifier)
SELECT 4,'Methamphetamine',1600,5,1.10
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM drugs WHERE id = 4);
INSERT INTO drugs (id,name,base_price,risk_factor,quality_modifier)
SELECT 5,'Synthetic Drugs',900,4,1.05
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM drugs WHERE id = 5);

INSERT INTO drug_prices (drug_id,region,price,supply,demand,police_pressure,updated_at)
SELECT 1,'Downtown',140,80,110,5,NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM drug_prices WHERE drug_id = 1 AND region = 'Downtown');
INSERT INTO drug_prices (drug_id,region,price,supply,demand,police_pressure,updated_at)
SELECT 2,'Downtown',4200,60,140,20,NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM drug_prices WHERE drug_id = 2 AND region = 'Downtown');
INSERT INTO drug_prices (drug_id,region,price,supply,demand,police_pressure,updated_at)
SELECT 3,'Downtown',3300,70,125,18,NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM drug_prices WHERE drug_id = 3 AND region = 'Downtown');
INSERT INTO drug_prices (drug_id,region,price,supply,demand,police_pressure,updated_at)
SELECT 4,'Industrial Zone',2100,90,130,12,NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM drug_prices WHERE drug_id = 4 AND region = 'Industrial Zone');
INSERT INTO drug_prices (drug_id,region,price,supply,demand,police_pressure,updated_at)
SELECT 5,'Harbor',1200,120,100,8,NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM drug_prices WHERE drug_id = 5 AND region = 'Harbor');

INSERT INTO territories (name,population,wealth,crime_rate,government_presence,tax_income,created_at,updated_at)
SELECT 'Downtown',850000,85,55,70,25000,NOW(),NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM territories WHERE name = 'Downtown');
INSERT INTO territories (name,population,wealth,crime_rate,government_presence,tax_income,created_at,updated_at)
SELECT 'Harbor',220000,60,75,45,18000,NOW(),NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM territories WHERE name = 'Harbor');
INSERT INTO territories (name,population,wealth,crime_rate,government_presence,tax_income,created_at,updated_at)
SELECT 'Industrial Zone',310000,50,80,35,14000,NOW(),NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM territories WHERE name = 'Industrial Zone');
INSERT INTO territories (name,population,wealth,crime_rate,government_presence,tax_income,created_at,updated_at)
SELECT 'Old Town',180000,45,65,55,9000,NOW(),NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM territories WHERE name = 'Old Town');
INSERT INTO territories (name,population,wealth,crime_rate,government_presence,tax_income,created_at,updated_at)
SELECT 'Luxury Hills',95000,95,25,85,35000,NOW(),NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM territories WHERE name = 'Luxury Hills');

INSERT INTO officials (name,department,loyalty,price,exposure_risk)
SELECT 'Detective Miles','police',10,25000,18
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM officials WHERE name = 'Detective Miles');
INSERT INTO officials (name,department,loyalty,price,exposure_risk)
SELECT 'Judge Romano','courts',5,120000,30
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM officials WHERE name = 'Judge Romano');
INSERT INTO officials (name,department,loyalty,price,exposure_risk)
SELECT 'Customs Agent Vega','customs',15,50000,22
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM officials WHERE name = 'Customs Agent Vega');
INSERT INTO officials (name,department,loyalty,price,exposure_risk)
SELECT 'Councilman Briggs','politics',20,90000,28
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM officials WHERE name = 'Councilman Briggs');
INSERT INTO officials (name,department,loyalty,price,exposure_risk)
SELECT 'Narcotics Lt. Hale','anti_drug',0,70000,35
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM officials WHERE name = 'Narcotics Lt. Hale');
