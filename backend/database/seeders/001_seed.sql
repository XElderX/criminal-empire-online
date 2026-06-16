INSERT IGNORE INTO users (id, username, email, password, role, cash, energy, max_energy, created_at, updated_at)
VALUES (1, 'admin', 'admin@criminal.test', '$argon2id$v=19$m=65536,t=4,p=1$d0xjbXhPdUdrS2VHV1ppUg$SJWriyC4tANOiEbXd6GOhC2czgnc3PJAHi0+yiB1q5o', 'admin', 5000000, 100, 100, NOW(), NOW());
INSERT INTO crimes (name,description,risk_level,energy_cost,success_rate,reward_min,reward_max,heat_gain,experience_gain,created_at,updated_at) VALUES
('Pickpocket','Low risk street crime.',1,5,90,100,500,1,5,NOW(),NOW()),
('Protection Collection','Collect protection money from small shops.',2,10,80,800,2500,3,12,NOW(),NOW()),
('Store Robbery','Armed robbery against a local store.',3,15,70,1000,5000,5,20,NOW(),NOW()),
('Vehicle Theft','Steal and strip a luxury vehicle.',4,25,60,5000,18000,10,45,NOW(),NOW()),
('Armored Truck Heist','High-risk crew job with major payout.',6,40,40,25000,100000,25,100,NOW(),NOW());
INSERT INTO weapons (name,class,damage,accuracy,reliability,concealment,maintenance_cost,price,created_at,updated_at) VALUES
('Saturday Night Special','pistol',12,55,60,90,25,1200,NOW(),NOW()),
('9mm Pistol','pistol',18,70,80,80,50,4500,NOW(),NOW()),
('Sawed-off Shotgun','shotgun',35,45,65,45,120,12000,NOW(),NOW()),
('Compact SMG','smg',28,62,70,55,180,30000,NOW(),NOW()),
('Assault Rifle','assault_rifle',45,68,75,20,350,85000,NOW(),NOW());
INSERT INTO drugs (id,name,base_price,risk_factor,quality_modifier) VALUES
(1,'Cannabis',120,1,1.00),(2,'Cocaine',3000,5,1.20),(3,'Heroin',2500,6,1.15),(4,'Methamphetamine',1600,5,1.10),(5,'Synthetic Drugs',900,4,1.05);
INSERT INTO drug_prices (drug_id,region,price,supply,demand,police_pressure,updated_at) VALUES
(1,'Downtown',140,80,110,5,NOW()),(2,'Downtown',4200,60,140,20,NOW()),(3,'Downtown',3300,70,125,18,NOW()),(4,'Industrial Zone',2100,90,130,12,NOW()),(5,'Harbor',1200,120,100,8,NOW());
INSERT INTO territories (name,population,wealth,crime_rate,government_presence,tax_income,created_at,updated_at) VALUES
('Downtown',850000,85,55,70,25000,NOW(),NOW()),('Harbor',220000,60,75,45,18000,NOW(),NOW()),('Industrial Zone',310000,50,80,35,14000,NOW(),NOW()),('Old Town',180000,45,65,55,9000,NOW(),NOW()),('Luxury Hills',95000,95,25,85,35000,NOW(),NOW());
INSERT INTO officials (name,department,loyalty,price,exposure_risk) VALUES
('Detective Miles','police',10,25000,18),('Judge Romano','courts',5,120000,30),('Customs Agent Vega','customs',15,50000,22),('Councilman Briggs','politics',20,90000,28),('Narcotics Lt. Hale','anti_drug',0,70000,35);
