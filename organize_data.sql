-- =======================================================
-- تنظيم وتوزيع البيانات لعرض احترافي للداش بورد
-- =======================================================

-- =======================================================
-- 1) توزيع تواريخ الشركات على شهور Jan-May 2026 (لو الـ Bar Chart)
-- =======================================================
UPDATE companies SET created_at='2026-01-15 10:30:00' WHERE id=1;
UPDATE companies SET created_at='2026-01-22 14:15:00' WHERE id=2;
UPDATE companies SET created_at='2026-02-05 09:45:00' WHERE id=3;
UPDATE companies SET created_at='2026-02-18 11:20:00' WHERE id=4;
UPDATE companies SET created_at='2026-02-28 16:00:00' WHERE id=5;
UPDATE companies SET created_at='2026-03-08 10:00:00' WHERE id=6;
UPDATE companies SET created_at='2026-03-15 13:30:00' WHERE id=7;
UPDATE companies SET created_at='2026-03-22 15:45:00' WHERE id=8;
UPDATE companies SET created_at='2026-03-30 11:15:00' WHERE id=9;
UPDATE companies SET created_at='2026-04-05 09:00:00' WHERE id=10;
UPDATE companies SET created_at='2026-04-12 14:30:00' WHERE id=11;
UPDATE companies SET created_at='2026-04-20 10:45:00' WHERE id=12;
UPDATE companies SET created_at='2026-04-25 16:30:00' WHERE id=13;
UPDATE companies SET created_at='2026-05-03 11:00:00' WHERE id=14;
UPDATE companies SET created_at='2026-05-10 13:15:00' WHERE id=15;
UPDATE companies SET created_at='2026-05-15 09:30:00' WHERE id=16;
UPDATE companies SET created_at='2026-05-20 14:00:00' WHERE id=17;
UPDATE companies SET created_at='2026-05-25 10:30:00' WHERE id=18;
UPDATE companies SET created_at='2026-05-26 15:00:00' WHERE id=19;
UPDATE companies SET created_at='2026-05-27 11:45:00' WHERE id=20;

-- مزامنة users.created_at مع companies
UPDATE users u JOIN companies c ON c.user_id=u.id
   SET u.created_at=c.created_at WHERE u.role='company';

-- =======================================================
-- 2) إضافة 3 شركات Pending Verifications مع licenses
-- =======================================================
SET @PWD = '$2y$10$L5gXXkrwtFvdol30xTVPVedit8uFc7yoPRiJoTeEw4R07C8lCHqfW';

INSERT IGNORE INTO users (name, email, password, role, phone, governorate, business_type, created_at, updated_at) VALUES
('Quantum Software Labs', 'quantum.labs@test.com',  @PWD, 'company', '0116200001', 'Damascus / دمشق', 'Technology / IT', '2026-05-26 09:30:00', NOW()),
('Green Earth Agriculture','green.earth@test.com',  @PWD, 'company', '0116200002', 'Hama / حماة',     'Agriculture',     '2026-05-27 14:15:00', NOW()),
('BlueWave Marketing',     'bluewave@test.com',     @PWD, 'company', '0116200003', 'Aleppo / حلب',    'Marketing / Advertising', '2026-05-28 10:00:00', NOW());

-- companies records (verification_status = pending + commercial_register)
INSERT INTO companies (user_id, phone, business_type, description, commercial_register_path, verification_status, created_at, updated_at)
SELECT u.id, u.phone, u.business_type,
  CASE u.email
    WHEN 'quantum.labs@test.com' THEN 'AI and quantum computing research lab building next-generation software solutions.'
    WHEN 'green.earth@test.com'  THEN 'Sustainable agriculture company specializing in organic farming and food processing.'
    WHEN 'bluewave@test.com'     THEN 'Full-service marketing and branding agency for SMBs and enterprises.'
  END,
  CONCAT('commercial_registers/', SUBSTRING_INDEX(u.email, '@', 1), '_license.pdf'),
  'pending', u.created_at, NOW()
 FROM users u
 WHERE u.email IN ('quantum.labs@test.com','green.earth@test.com','bluewave@test.com')
   AND NOT EXISTS (SELECT 1 FROM companies c WHERE c.user_id=u.id);

-- =======================================================
-- 3) توزيع تواريخ الـ Users (job seekers) على آخر شهرين
-- =======================================================
UPDATE users SET created_at='2026-04-01 10:00:00' WHERE email='saraha21hg@gmail.com';
UPDATE users SET created_at='2026-04-05 14:30:00' WHERE email='aisha@gmail.com';
UPDATE users SET created_at='2026-04-08 09:15:00' WHERE email='test@example.com';
UPDATE users SET created_at='2026-04-12 11:45:00' WHERE email='sarah.user@gmail.com';
UPDATE users SET created_at='2026-04-15 16:00:00' WHERE email='aisha.user@gmail.com';
UPDATE users SET created_at='2026-04-18 10:30:00' WHERE email='olaen@gmail.com';
UPDATE users SET created_at='2026-04-20 13:15:00' WHERE email='aliar12@gmail.com';
UPDATE users SET created_at='2026-04-22 09:30:00' WHERE email='ahmad@test.com';
UPDATE users SET created_at='2026-04-25 14:45:00' WHERE email='sara@test.com';
UPDATE users SET created_at='2026-04-28 11:00:00' WHERE email='omar@test.com';
UPDATE users SET created_at='2026-05-02 15:30:00' WHERE email='lina@test.com';
UPDATE users SET created_at='2026-05-05 10:15:00' WHERE email='kareem@test.com';
UPDATE users SET created_at='2026-05-08 13:45:00' WHERE email='nour@test.com';
UPDATE users SET created_at='2026-05-10 09:00:00' WHERE email='tarek@test.com';
UPDATE users SET created_at='2026-05-13 16:30:00' WHERE email='rima@test.com';
UPDATE users SET created_at='2026-05-15 11:15:00' WHERE email='bassam@test.com';
UPDATE users SET created_at='2026-05-17 14:00:00' WHERE email='hala@test.com';
UPDATE users SET created_at='2026-05-19 10:45:00' WHERE email='karim.chef@test.com';
UPDATE users SET created_at='2026-05-21 13:30:00' WHERE email='reem.lawyer@test.com';
UPDATE users SET created_at='2026-05-22 09:15:00' WHERE email='yara.pharma@test.com';
UPDATE users SET created_at='2026-05-23 15:00:00' WHERE email='ziad.translator@test.com';
UPDATE users SET created_at='2026-05-24 11:30:00' WHERE email='maya.journalist@test.com';
UPDATE users SET created_at='2026-05-25 14:15:00' WHERE email='fadi.mechanic@test.com';
UPDATE users SET created_at='2026-05-26 10:00:00' WHERE email='nadia.photo@test.com';
UPDATE users SET created_at='2026-05-27 16:45:00' WHERE email='samer.guide@test.com';

-- =======================================================
-- 4) توزيع تواريخ الـ Jobs على آخر 60 يوم
-- =======================================================
UPDATE jobs SET created_at='2026-04-02 10:00:00', updated_at='2026-04-02 10:00:00' WHERE id=1;
UPDATE jobs SET created_at='2026-04-08 11:30:00', updated_at='2026-04-08 11:30:00' WHERE id=2;
UPDATE jobs SET created_at='2026-04-15 09:45:00', updated_at='2026-04-15 09:45:00' WHERE id=3;
UPDATE jobs SET created_at='2026-04-20 14:00:00', updated_at='2026-04-20 14:00:00' WHERE id=4;
UPDATE jobs SET created_at='2026-04-25 16:30:00', updated_at='2026-04-25 16:30:00' WHERE id=5;
UPDATE jobs SET created_at='2026-04-30 10:15:00', updated_at='2026-04-30 10:15:00' WHERE id=6;
UPDATE jobs SET created_at='2026-05-03 13:45:00', updated_at='2026-05-03 13:45:00' WHERE id=7;
UPDATE jobs SET created_at='2026-05-06 09:30:00', updated_at='2026-05-06 09:30:00' WHERE id=8;
UPDATE jobs SET created_at='2026-05-09 15:00:00', updated_at='2026-05-09 15:00:00' WHERE id=9;
UPDATE jobs SET created_at='2026-05-12 11:15:00', updated_at='2026-05-12 11:15:00' WHERE id=10;
UPDATE jobs SET created_at='2026-05-14 14:30:00', updated_at='2026-05-14 14:30:00' WHERE id=11;
UPDATE jobs SET created_at='2026-05-16 10:45:00', updated_at='2026-05-16 10:45:00' WHERE id=12;
UPDATE jobs SET created_at='2026-05-18 16:00:00', updated_at='2026-05-18 16:00:00' WHERE id=13;
UPDATE jobs SET created_at='2026-05-19 09:15:00', updated_at='2026-05-19 09:15:00' WHERE id=14;
UPDATE jobs SET created_at='2026-05-20 13:00:00', updated_at='2026-05-20 13:00:00' WHERE id=15;
UPDATE jobs SET created_at='2026-05-21 11:30:00', updated_at='2026-05-21 11:30:00' WHERE id=16;
UPDATE jobs SET created_at='2026-05-22 15:45:00', updated_at='2026-05-22 15:45:00' WHERE id=17;
UPDATE jobs SET created_at='2026-05-23 10:00:00', updated_at='2026-05-23 10:00:00' WHERE id=18;
UPDATE jobs SET created_at='2026-05-23 14:30:00', updated_at='2026-05-23 14:30:00' WHERE id=19;
UPDATE jobs SET created_at='2026-05-24 09:45:00', updated_at='2026-05-24 09:45:00' WHERE id=20;
UPDATE jobs SET created_at='2026-05-24 13:15:00', updated_at='2026-05-24 13:15:00' WHERE id=21;
UPDATE jobs SET created_at='2026-05-25 10:30:00', updated_at='2026-05-25 10:30:00' WHERE id=22;
UPDATE jobs SET created_at='2026-05-25 15:00:00', updated_at='2026-05-25 15:00:00' WHERE id=23;
UPDATE jobs SET created_at='2026-05-26 09:15:00', updated_at='2026-05-26 09:15:00' WHERE id=24;
UPDATE jobs SET created_at='2026-05-26 14:45:00', updated_at='2026-05-26 14:45:00' WHERE id=25;
UPDATE jobs SET created_at='2026-05-26 16:30:00', updated_at='2026-05-26 16:30:00' WHERE id=26;
UPDATE jobs SET created_at='2026-05-27 10:15:00', updated_at='2026-05-27 10:15:00' WHERE id=27;
UPDATE jobs SET created_at='2026-05-27 13:00:00', updated_at='2026-05-27 13:00:00' WHERE id=28;
UPDATE jobs SET created_at='2026-05-27 15:30:00', updated_at='2026-05-27 15:30:00' WHERE id=29;
UPDATE jobs SET created_at='2026-05-28 09:00:00', updated_at='2026-05-28 09:00:00' WHERE id=30;
UPDATE jobs SET created_at='2026-05-28 11:30:00', updated_at='2026-05-28 11:30:00' WHERE id=31;
UPDATE jobs SET created_at='2026-05-28 13:45:00', updated_at='2026-05-28 13:45:00' WHERE id=32;
UPDATE jobs SET created_at='2026-05-28 16:00:00', updated_at='2026-05-28 16:00:00' WHERE id=33;

-- إضافة وظيفتين Pending Payment للعرض على صفحة Pending Payments
INSERT INTO jobs (user_id, title, job_type, location, salary, description, requirements, is_paid, payment_session_id, created_at, updated_at)
SELECT u.id, 'Senior Backend Engineer', 'Full-time', 'Damascus', '$1500/month',
  'Lead backend development for our flagship product.',
  'Node.js, PostgreSQL, Microservices, AWS, 5+ years experience',
  0, CONCAT('cs_test_', SUBSTRING(MD5(RAND()),1,20)), '2026-05-28 10:00:00', NOW()
FROM users u WHERE u.email='technova@test.com'
  AND NOT EXISTS (SELECT 1 FROM jobs WHERE user_id=u.id AND title='Senior Backend Engineer');

INSERT INTO jobs (user_id, title, job_type, location, salary, description, requirements, is_paid, payment_session_id, created_at, updated_at)
SELECT u.id, 'Mobile App Developer (iOS)', 'Full-time', 'Aleppo', '$1200/month',
  'Build and maintain iOS apps for our digital products.',
  'Swift, SwiftUI, REST APIs, Git, 3+ years experience',
  0, CONCAT('cs_test_', SUBSTRING(MD5(RAND()),1,20)), '2026-05-28 14:30:00', NOW()
FROM users u WHERE u.email='digitalhorizons@test.com'
  AND NOT EXISTS (SELECT 1 FROM jobs WHERE user_id=u.id AND title='Mobile App Developer (iOS)');

-- =======================================================
-- 5) توزيع تواريخ user_resumes (آخر 14 يوم) - للـ Talent Activity
-- =======================================================
UPDATE user_resumes SET created_at='2026-05-15 09:00:00', updated_at='2026-05-15 09:00:00' WHERE id=1;
UPDATE user_resumes SET created_at='2026-05-16 11:30:00', updated_at='2026-05-16 11:30:00' WHERE id=2;
UPDATE user_resumes SET created_at='2026-05-17 14:00:00', updated_at='2026-05-17 14:00:00' WHERE id=3;
UPDATE user_resumes SET created_at='2026-05-18 10:15:00', updated_at='2026-05-18 10:15:00' WHERE id=4;
UPDATE user_resumes SET created_at='2026-05-19 13:45:00', updated_at='2026-05-19 13:45:00' WHERE id=5;
UPDATE user_resumes SET created_at='2026-05-20 09:30:00', updated_at='2026-05-20 09:30:00' WHERE id=6;
UPDATE user_resumes SET created_at='2026-05-20 15:00:00', updated_at='2026-05-20 15:00:00' WHERE id=7;
UPDATE user_resumes SET created_at='2026-05-21 11:15:00', updated_at='2026-05-21 11:15:00' WHERE id=8;
UPDATE user_resumes SET created_at='2026-05-22 14:30:00', updated_at='2026-05-22 14:30:00' WHERE id=9;
UPDATE user_resumes SET created_at='2026-05-23 10:45:00', updated_at='2026-05-23 10:45:00' WHERE id=10;
UPDATE user_resumes SET created_at='2026-05-24 13:00:00', updated_at='2026-05-24 13:00:00' WHERE id=11;
UPDATE user_resumes SET created_at='2026-05-24 16:30:00', updated_at='2026-05-24 16:30:00' WHERE id=12;
UPDATE user_resumes SET created_at='2026-05-25 09:15:00', updated_at='2026-05-25 09:15:00' WHERE id=13;
UPDATE user_resumes SET created_at='2026-05-25 14:45:00', updated_at='2026-05-25 14:45:00' WHERE id=14;
UPDATE user_resumes SET created_at='2026-05-26 11:00:00', updated_at='2026-05-26 11:00:00' WHERE id=15;
UPDATE user_resumes SET created_at='2026-05-26 15:30:00', updated_at='2026-05-26 15:30:00' WHERE id=16;
UPDATE user_resumes SET created_at='2026-05-27 09:30:00', updated_at='2026-05-27 09:30:00' WHERE id=17;
UPDATE user_resumes SET created_at='2026-05-27 13:00:00', updated_at='2026-05-27 13:00:00' WHERE id=18;
UPDATE user_resumes SET created_at='2026-05-27 16:00:00', updated_at='2026-05-27 16:00:00' WHERE id=19;
UPDATE user_resumes SET created_at='2026-05-28 10:00:00', updated_at='2026-05-28 10:00:00' WHERE id>=20;

-- =======================================================
-- 6) توزيع تواريخ user_roadmaps و user_quizzes
-- =======================================================
UPDATE user_roadmaps SET created_at=DATE_ADD('2026-05-15 10:00:00', INTERVAL id*12 HOUR),
                         updated_at=DATE_ADD('2026-05-15 10:00:00', INTERVAL id*12 HOUR);

UPDATE user_quizzes  SET created_at=DATE_ADD('2026-05-18 11:00:00', INTERVAL id*15 HOUR),
                         updated_at=DATE_ADD('2026-05-18 11:00:00', INTERVAL id*15 HOUR);

-- =======================================================
-- 7) إضافة شكاوى متنوّعة (in_progress + resolved) لتنوّع الحالة
-- =======================================================
INSERT INTO complaints (user_id, role, subject, message, status, admin_response, resolved_at, created_at, updated_at)
SELECT u.id, 'job', 'CV upload not working',
       'I tried to upload my CV three times but got an error each time. Please help.',
       'pending', NULL, NULL, '2026-05-27 10:30:00', NOW()
FROM users u WHERE u.email='ahmad@test.com'
  AND NOT EXISTS (SELECT 1 FROM complaints WHERE user_id=u.id AND subject='CV upload not working');

INSERT INTO complaints (user_id, role, subject, message, status, admin_response, resolved_at, created_at, updated_at)
SELECT u.id, 'company', 'Payment confirmation delayed',
       'I paid for a job post 2 days ago but it is still showing as pending.',
       'in_progress', 'We are reviewing your payment with our finance team.', NULL,
       '2026-05-26 14:15:00', NOW()
FROM users u WHERE u.email='technova@test.com'
  AND NOT EXISTS (SELECT 1 FROM complaints WHERE user_id=u.id AND subject='Payment confirmation delayed');

INSERT INTO complaints (user_id, role, subject, message, status, admin_response, resolved_at, created_at, updated_at)
SELECT u.id, 'job', 'Roadmap generation error',
       'The roadmap generation gives me an error when I select Frontend Developer as target.',
       'resolved', 'Issue fixed in latest update. Please try again and let us know.',
       '2026-05-24 16:00:00', '2026-05-23 09:00:00', NOW()
FROM users u WHERE u.email='sara@test.com'
  AND NOT EXISTS (SELECT 1 FROM complaints WHERE user_id=u.id AND subject='Roadmap generation error');

INSERT INTO complaints (user_id, role, subject, message, status, admin_response, resolved_at, created_at, updated_at)
SELECT u.id, 'company', 'Cannot access company dashboard',
       'After login I cannot see the company dashboard, page is blank.',
       'resolved', 'Browser cache issue. Cleared and now working as expected.',
       '2026-05-22 11:30:00', '2026-05-21 14:45:00', NOW()
FROM users u WHERE u.email='digitalhorizons@test.com'
  AND NOT EXISTS (SELECT 1 FROM complaints WHERE user_id=u.id AND subject='Cannot access company dashboard');

INSERT INTO complaints (user_id, role, subject, message, status, admin_response, resolved_at, created_at, updated_at)
SELECT u.id, 'job', 'Quiz score not saving',
       'I completed the quiz but my score is not saved in my profile.',
       'pending', NULL, NULL, '2026-05-28 09:15:00', NOW()
FROM users u WHERE u.email='lina@test.com'
  AND NOT EXISTS (SELECT 1 FROM complaints WHERE user_id=u.id AND subject='Quiz score not saving');

-- =======================================================
-- ✅ DONE - Summary
-- =======================================================
SELECT '=== DASHBOARD STATS ===' AS info;
SELECT
  (SELECT COUNT(*) FROM jobs WHERE is_paid=1) AS active_jobs,
  (SELECT COUNT(*) FROM jobs WHERE is_paid=1)*25 AS revenue,
  (SELECT COUNT(*) FROM complaints WHERE status='pending') AS pending_tickets,
  (SELECT COUNT(*) FROM companies WHERE verification_status='pending' AND commercial_register_path IS NOT NULL) AS pending_verifications,
  (SELECT COUNT(*) FROM users WHERE role='job') AS job_seekers,
  (SELECT COUNT(*) FROM users WHERE role='company') AS companies;

SELECT '=== COMPANIES BY MONTH ===' AS info;
SELECT MONTH(created_at) AS month, COUNT(*) AS new_companies
  FROM companies WHERE YEAR(created_at)=2026 GROUP BY MONTH(created_at);

SELECT '=== COMPLAINTS BY STATUS ===' AS info;
SELECT status, COUNT(*) AS count FROM complaints GROUP BY status;

SELECT '=== PENDING PAYMENTS ===' AS info;
SELECT COUNT(*) AS pending_jobs FROM jobs WHERE is_paid=0;
