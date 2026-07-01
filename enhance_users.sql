-- =======================================================
-- ENHANCE USERS - بدون PHP، SQL مباشر
-- كلمة السر للجديد: password123
-- =======================================================
SET @PWD = '$2y$10$L5gXXkrwtFvdol30xTVPVedit8uFc7yoPRiJoTeEw4R07C8lCHqfW';
SET @NOW = NOW();

-- =======================================================
-- 1) إصلاح الـ Users الناقصين
-- =======================================================

-- sarah (id 1) - حقن governorate
UPDATE users SET governorate='Damascus / دمشق', updated_at=@NOW
 WHERE email='saraha21hg@gmail.com' AND (governorate IS NULL OR governorate='');

-- Ahmed company (id 2) - حقن governorate
UPDATE users SET governorate='Damascus / دمشق', updated_at=@NOW
 WHERE email='ahmed@gmail.com' AND (governorate IS NULL OR governorate='');

-- Test User (id 4) - phone + governorate
UPDATE users SET phone='0991111000', governorate='Damascus / دمشق', updated_at=@NOW
 WHERE email='test@example.com';

-- Sarah Ali (id 5) - governorate
UPDATE users SET governorate='Damascus / دمشق', updated_at=@NOW
 WHERE email='sarah.user@gmail.com' AND (governorate IS NULL OR governorate='');

-- Ahmed Tech Co (id 6) - governorate + business_type
UPDATE users SET governorate='Aleppo / حلب', business_type='Software Development', updated_at=@NOW
 WHERE email='ahmed.company@gmail.com' AND (governorate IS NULL OR governorate='');

-- Aisha Developer (id 7) - governorate
UPDATE users SET governorate='Rif Dimashq / ريف دمشق', updated_at=@NOW
 WHERE email='aisha.user@gmail.com' AND (governorate IS NULL OR governorate='');

-- =======================================================
-- 2) إنشاء job_seekers الناقصة (4, 5, 7)
-- =======================================================
INSERT IGNORE INTO job_seekers (user_id, phone, created_at, updated_at)
SELECT u.id, u.phone, @NOW, @NOW FROM users u
 WHERE u.role='job' AND u.email IN ('test@example.com','sarah.user@gmail.com','aisha.user@gmail.com','olaen@gmail.com')
   AND NOT EXISTS (SELECT 1 FROM job_seekers js WHERE js.user_id=u.id);

-- =======================================================
-- 3) إنشاء Company الناقصة لـ Ahmed Tech Co (id 6)
-- =======================================================
INSERT INTO companies (user_id, phone, business_type, description, verification_status, created_at, updated_at)
SELECT u.id, u.phone, 'Software Development',
       'Tech company offering software development services for startups and enterprises.',
       'approved', @NOW, @NOW
 FROM users u
 WHERE u.email='ahmed.company@gmail.com'
   AND NOT EXISTS (SELECT 1 FROM companies c WHERE c.user_id=u.id);

-- =======================================================
-- 4) إضافة CVs للمستخدمين الناقصة سيرهم (4, 5, 7, 10)
-- =======================================================
INSERT INTO user_resumes (user_id, target_job, original_text, current_skills, missing_skills, created_at, updated_at)
SELECT id, 'Web Developer',
       'Junior web developer with 1 year experience building responsive websites.',
       JSON_ARRAY('HTML','CSS','JavaScript','Bootstrap'),
       JSON_ARRAY('React.js','Node.js','MongoDB'),
       @NOW, @NOW
 FROM users WHERE email='test@example.com'
   AND NOT EXISTS (SELECT 1 FROM user_resumes r WHERE r.user_id=users.id);

INSERT INTO user_resumes (user_id, target_job, original_text, current_skills, missing_skills, created_at, updated_at)
SELECT id, 'UI/UX Designer',
       'UI/UX designer focused on mobile and web app design with 2 years experience.',
       JSON_ARRAY('Figma','Adobe XD','Wireframing','Prototyping'),
       JSON_ARRAY('User Research','Design Systems','Accessibility (WCAG)'),
       @NOW, @NOW
 FROM users WHERE email='sarah.user@gmail.com'
   AND NOT EXISTS (SELECT 1 FROM user_resumes r WHERE r.user_id=users.id);

INSERT INTO user_resumes (user_id, target_job, original_text, current_skills, missing_skills, created_at, updated_at)
SELECT id, 'Full Stack Developer',
       'Full-stack developer specialized in Laravel and Vue.js with 2 years experience.',
       JSON_ARRAY('Laravel','PHP','Vue.js','MySQL','REST APIs'),
       JSON_ARRAY('Docker','CI/CD','AWS'),
       @NOW, @NOW
 FROM users WHERE email='aisha.user@gmail.com'
   AND NOT EXISTS (SELECT 1 FROM user_resumes r WHERE r.user_id=users.id);

INSERT INTO user_resumes (user_id, target_job, original_text, current_skills, missing_skills, created_at, updated_at)
SELECT id, 'Content Writer',
       'Bilingual content writer with experience in blogs, articles and social media.',
       JSON_ARRAY('Arabic Writing','English Writing','SEO','Editing'),
       JSON_ARRAY('Copywriting','Technical Writing','Content Strategy'),
       @NOW, @NOW
 FROM users WHERE email='olaen@gmail.com'
   AND NOT EXISTS (SELECT 1 FROM user_resumes r WHERE r.user_id=users.id);

-- =======================================================
-- 5) Job Seekers جدد - تخصصات متنوعة (مش معلوماتية)
-- =======================================================
INSERT IGNORE INTO users (name, email, password, role, phone, governorate, created_at, updated_at) VALUES
('Karim Haddad',  'karim.chef@test.com',       @PWD, 'job', '0993200001', 'Damascus / دمشق', @NOW, @NOW),
('Reem Saadeh',   'reem.lawyer@test.com',      @PWD, 'job', '0993200002', 'Damascus / دمشق', @NOW, @NOW),
('Yara Khoury',   'yara.pharma@test.com',      @PWD, 'job', '0993200003', 'Lattakia / اللاذقية', @NOW, @NOW),
('Ziad Ibrahim',  'ziad.translator@test.com',  @PWD, 'job', '0993200004', 'Aleppo / حلب', @NOW, @NOW),
('Maya Suleiman', 'maya.journalist@test.com',  @PWD, 'job', '0993200005', 'Damascus / دمشق', @NOW, @NOW),
('Fadi Aboud',    'fadi.mechanic@test.com',    @PWD, 'job', '0993200006', 'Homs / حمص', @NOW, @NOW),
('Nadia Tannous', 'nadia.photo@test.com',      @PWD, 'job', '0993200007', 'Tartus / طرطوس', @NOW, @NOW),
('Samer Najjar',  'samer.guide@test.com',      @PWD, 'job', '0993200008', 'Damascus / دمشق', @NOW, @NOW);

-- job_seekers لكل واحد جديد
INSERT IGNORE INTO job_seekers (user_id, phone, created_at, updated_at)
SELECT u.id, u.phone, @NOW, @NOW FROM users u
 WHERE u.email IN (
   'karim.chef@test.com','reem.lawyer@test.com','yara.pharma@test.com','ziad.translator@test.com',
   'maya.journalist@test.com','fadi.mechanic@test.com','nadia.photo@test.com','samer.guide@test.com'
 ) AND NOT EXISTS (SELECT 1 FROM job_seekers js WHERE js.user_id=u.id);

-- CVs للجدد
INSERT INTO user_resumes (user_id, target_job, original_text, current_skills, missing_skills, created_at, updated_at)
SELECT id, 'Head Chef',
  'Professional chef with 8 years experience in fine dining restaurants.',
  JSON_ARRAY('Mediterranean Cuisine','Menu Planning','Kitchen Management','Food Safety'),
  JSON_ARRAY('French Pastry','Molecular Gastronomy','Cost Control'),
  @NOW, @NOW FROM users WHERE email='karim.chef@test.com'
   AND NOT EXISTS (SELECT 1 FROM user_resumes r WHERE r.user_id=users.id);

INSERT INTO user_resumes (user_id, target_job, original_text, current_skills, missing_skills, created_at, updated_at)
SELECT id, 'Corporate Lawyer',
  'Lawyer with 5 years experience in corporate and commercial law.',
  JSON_ARRAY('Contract Law','Legal Research','Litigation','Arabic & English Drafting'),
  JSON_ARRAY('International Trade Law','IP Law','Arbitration'),
  @NOW, @NOW FROM users WHERE email='reem.lawyer@test.com'
   AND NOT EXISTS (SELECT 1 FROM user_resumes r WHERE r.user_id=users.id);

INSERT INTO user_resumes (user_id, target_job, original_text, current_skills, missing_skills, created_at, updated_at)
SELECT id, 'Clinical Pharmacist',
  'Licensed pharmacist with 4 years retail and hospital pharmacy experience.',
  JSON_ARRAY('Drug Dispensing','Patient Counseling','Inventory Management','Prescription Review'),
  JSON_ARRAY('Hospital Pharmacy','Clinical Trials','Pharmacovigilance'),
  @NOW, @NOW FROM users WHERE email='yara.pharma@test.com'
   AND NOT EXISTS (SELECT 1 FROM user_resumes r WHERE r.user_id=users.id);

INSERT INTO user_resumes (user_id, target_job, original_text, current_skills, missing_skills, created_at, updated_at)
SELECT id, 'English-Arabic Translator',
  'Certified translator with 6 years freelance experience across multiple domains.',
  JSON_ARRAY('Translation','Proofreading','CAT Tools','Subtitling'),
  JSON_ARRAY('Legal Translation','Medical Translation','Localization'),
  @NOW, @NOW FROM users WHERE email='ziad.translator@test.com'
   AND NOT EXISTS (SELECT 1 FROM user_resumes r WHERE r.user_id=users.id);

INSERT INTO user_resumes (user_id, target_job, original_text, current_skills, missing_skills, created_at, updated_at)
SELECT id, 'Investigative Journalist',
  'Journalist with 3 years experience in print and online news outlets.',
  JSON_ARRAY('News Writing','Interviewing','Research','Editing'),
  JSON_ARRAY('Data Journalism','Documentary Production','Multimedia Storytelling'),
  @NOW, @NOW FROM users WHERE email='maya.journalist@test.com'
   AND NOT EXISTS (SELECT 1 FROM user_resumes r WHERE r.user_id=users.id);

INSERT INTO user_resumes (user_id, target_job, original_text, current_skills, missing_skills, created_at, updated_at)
SELECT id, 'Auto Mechanic',
  'Auto mechanic with 7 years hands-on experience in vehicle repair and maintenance.',
  JSON_ARRAY('Engine Repair','Brake Systems','Electrical Diagnostics','Transmission Service'),
  JSON_ARRAY('Hybrid/EV Systems','Computer Diagnostics','European Car Specialization'),
  @NOW, @NOW FROM users WHERE email='fadi.mechanic@test.com'
   AND NOT EXISTS (SELECT 1 FROM user_resumes r WHERE r.user_id=users.id);

INSERT INTO user_resumes (user_id, target_job, original_text, current_skills, missing_skills, created_at, updated_at)
SELECT id, 'Wedding Photographer',
  'Professional photographer with 5 years specializing in events and portraits.',
  JSON_ARRAY('Portrait Photography','Lightroom','Photoshop','Studio Lighting'),
  JSON_ARRAY('Drone Photography','Video Editing','Cinematic Storytelling'),
  @NOW, @NOW FROM users WHERE email='nadia.photo@test.com'
   AND NOT EXISTS (SELECT 1 FROM user_resumes r WHERE r.user_id=users.id);

INSERT INTO user_resumes (user_id, target_job, original_text, current_skills, missing_skills, created_at, updated_at)
SELECT id, 'Tour Guide',
  'Licensed tour guide with deep knowledge of Syrian heritage and ancient sites.',
  JSON_ARRAY('Syrian History','English Communication','Customer Service','Group Management'),
  JSON_ARRAY('French Language','Tour Operations Software','First Aid Certification'),
  @NOW, @NOW FROM users WHERE email='samer.guide@test.com'
   AND NOT EXISTS (SELECT 1 FROM user_resumes r WHERE r.user_id=users.id);

-- =======================================================
-- 6) شركات جدد - قطاعات متنوعة
-- =======================================================
INSERT IGNORE INTO users (name, email, password, role, phone, governorate, business_type, created_at, updated_at) VALUES
('Damasco Restaurant Group',  'damasco@test.com',         @PWD, 'company', '0116100001', 'Damascus / دمشق',     'Restaurants / Food Service', @NOW, @NOW),
('Sham Properties',            'sham.properties@test.com', @PWD, 'company', '0116100002', 'Damascus / دمشق',     'Real Estate', @NOW, @NOW),
('Justice Law Office',         'justice.law@test.com',     @PWD, 'company', '0116100003', 'Aleppo / حلب',        'Legal Services', @NOW, @NOW),
('Ancient Syria Tours',        'ancientsyria@test.com',    @PWD, 'company', '0116100004', 'Damascus / دمشق',     'Tourism / Travel', @NOW, @NOW),
('Aleppo Textile Industries',  'aleppo.textile@test.com',  @PWD, 'company', '0116100005', 'Aleppo / حلب',        'Manufacturing', @NOW, @NOW),
('Swift Cargo Syria',          'swift.cargo@test.com',     @PWD, 'company', '0116100006', 'Lattakia / اللاذقية', 'Logistics / Shipping', @NOW, @NOW),
('Sham News Network',          'shamnews@test.com',        @PWD, 'company', '0116100007', 'Damascus / دمشق',     'Media / Press', @NOW, @NOW),
('Elegance Boutique',          'elegance@test.com',        @PWD, 'company', '0116100008', 'Damascus / دمشق',     'Retail / Fashion', @NOW, @NOW);

-- companies records
INSERT INTO companies (user_id, phone, business_type, description, verification_status, created_at, updated_at)
SELECT u.id, u.phone, u.business_type,
  CASE u.email
    WHEN 'damasco@test.com'         THEN 'Chain of fine-dining restaurants serving authentic Syrian and Mediterranean cuisine.'
    WHEN 'sham.properties@test.com' THEN 'Leading real estate agency in Syria handling sales, rentals, and property management.'
    WHEN 'justice.law@test.com'     THEN 'Established law firm specializing in corporate, commercial, and family law.'
    WHEN 'ancientsyria@test.com'    THEN 'Tour operator offering cultural and historical tours across Syria.'
    WHEN 'aleppo.textile@test.com'  THEN 'Traditional textile manufacturer producing fabrics for local and export markets.'
    WHEN 'swift.cargo@test.com'     THEN 'International logistics and freight forwarding company operating from Lattakia port.'
    WHEN 'shamnews@test.com'        THEN 'Independent news network covering local and regional events.'
    WHEN 'elegance@test.com'        THEN 'Upscale fashion boutique chain offering local and imported designer wear.'
  END,
  'approved', @NOW, @NOW
 FROM users u
 WHERE u.email IN (
   'damasco@test.com','sham.properties@test.com','justice.law@test.com','ancientsyria@test.com',
   'aleppo.textile@test.com','swift.cargo@test.com','shamnews@test.com','elegance@test.com'
 ) AND NOT EXISTS (SELECT 1 FROM companies c WHERE c.user_id=u.id);

-- jobs لكل شركة جديدة
INSERT INTO jobs (user_id, title, job_type, location, salary, description, requirements, is_paid, created_at, updated_at)
SELECT u.id, j.title, j.job_type, j.location, j.salary, j.description, j.requirements, 1, @NOW, @NOW
FROM users u
JOIN (
  SELECT 'damasco@test.com' AS email, 'Head Chef' AS title, 'Full-time' AS job_type, 'Damascus' AS location, '$900/month' AS salary,
         'Lead our kitchen team and develop seasonal menus.' AS description,
         'Mediterranean Cuisine, Menu Planning, Kitchen Management, 5+ years experience' AS requirements
  UNION ALL SELECT 'damasco@test.com', 'Restaurant Manager', 'Full-time', 'Damascus', '$700/month',
         'Manage daily operations and staff of our flagship restaurant.',
         'Hospitality Management, Customer Service, Inventory Control'
  UNION ALL SELECT 'sham.properties@test.com', 'Real Estate Agent', 'Full-time', 'Damascus', 'Commission-based',
         'Sell and rent residential and commercial properties.',
         'Sales Skills, Negotiation, Customer Relations, Driving License'
  UNION ALL SELECT 'justice.law@test.com', 'Corporate Lawyer', 'Full-time', 'Aleppo', '$1100/month',
         'Handle corporate legal matters and contract negotiations.',
         'Contract Law, Litigation, Legal Research, Bar Admission'
  UNION ALL SELECT 'justice.law@test.com', 'Legal Assistant', 'Full-time', 'Aleppo', '$500/month',
         'Support attorneys with research, filing, and client coordination.',
         'Legal Documentation, MS Office, Arabic & English'
  UNION ALL SELECT 'ancientsyria@test.com', 'Tour Guide', 'Part-time', 'Damascus', '$500/month',
         'Lead cultural tours of historical sites in Damascus and beyond.',
         'Syrian History, English/French, Customer Service, Tour Guide License'
  UNION ALL SELECT 'ancientsyria@test.com', 'Travel Consultant', 'Full-time', 'Damascus', '$600/month',
         'Plan custom travel packages and assist with bookings.',
         'Travel Planning, GDS Systems, Customer Service'
  UNION ALL SELECT 'aleppo.textile@test.com', 'Production Supervisor', 'Full-time', 'Aleppo', '$700/month',
         'Supervise production lines and ensure quality standards.',
         'Manufacturing Operations, Quality Control, Team Leadership'
  UNION ALL SELECT 'aleppo.textile@test.com', 'Quality Control Inspector', 'Full-time', 'Aleppo', '$450/month',
         'Inspect textile products to ensure they meet quality specifications.',
         'Quality Assurance, Attention to Detail, ISO Standards'
  UNION ALL SELECT 'swift.cargo@test.com', 'Logistics Coordinator', 'Full-time', 'Lattakia', '$650/month',
         'Coordinate shipments and manage customs documentation.',
         'Supply Chain, Customs Procedures, MS Excel, English'
  UNION ALL SELECT 'swift.cargo@test.com', 'Warehouse Manager', 'Full-time', 'Lattakia', '$800/month',
         'Oversee warehouse operations and inventory management.',
         'Warehouse Management, Inventory Systems, Team Leadership'
  UNION ALL SELECT 'shamnews@test.com', 'News Reporter', 'Full-time', 'Damascus', '$600/month',
         'Report on local news and conduct field interviews.',
         'News Writing, Interviewing, Research, Arabic & English'
  UNION ALL SELECT 'shamnews@test.com', 'Video Editor', 'Full-time', 'Damascus', '$700/month',
         'Edit news segments and documentaries.',
         'Adobe Premiere, After Effects, Color Grading'
  UNION ALL SELECT 'elegance@test.com', 'Fashion Buyer', 'Full-time', 'Damascus', '$750/month',
         'Source and purchase fashion items for our retail stores.',
         'Fashion Trends, Negotiation, Vendor Management, Travel Flexibility'
  UNION ALL SELECT 'elegance@test.com', 'Sales Associate', 'Full-time', 'Damascus', '$400/month',
         'Assist customers and drive sales in our boutique.',
         'Customer Service, Sales, Fashion Knowledge'
) j ON j.email = u.email
WHERE NOT EXISTS (SELECT 1 FROM jobs jj WHERE jj.user_id=u.id AND jj.title=j.title);

-- =======================================================
-- ✅ DONE
-- =======================================================
SELECT 'DONE' AS status, COUNT(*) AS total_users FROM users;
SELECT role, COUNT(*) AS count FROM users GROUP BY role;
