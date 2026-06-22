# Career AI — الدليل الشامل للمشروع

> **ملف واحد متكامل** يشرح كل ما تم بناؤه في مشروع Career AI: Backend (Laravel) + Frontend (Flutter) + الذكاء الاصطناعي + قاعدة البيانات + الـ API + التشغيل والاختبار.

---

## جدول المحتويات

1. [نظرة عامة](#1-نظرة-عامة)
2. [البنية التقنية](#2-البنية-التقنية)
3. [هيكل المشروع](#3-هيكل-المشروع)
4. [أدوار المستخدمين](#4-أدوار-المستخدمين)
5. [الميزات حسب الدور](#5-الميزات-حسب-الدور)
6. [قسم الذكاء الاصطناعي (AI)](#6-قسم-الذكاء-الاصطناعي-ai)
7. [قاعدة البيانات](#7-قاعدة-البيانات)
8. [مرجع الـ API](#8-مرجع-ال-api)
9. [تدفقات العمل (User Flows)](#9-تدفقات-العمل-user-flows)
10. [التثبيت والتشغيل](#10-التثبيت-والتشغيل)
11. [متغيرات البيئة (.env)](#11-متغيرات-البيئة-env)
12. [الاختبار](#12-الاختبار)
13. [الملفات المهمة](#13-الملفات-المهمة)
14. [المخططات (ERD & Class Diagram)](#14-المخططات-erd--class-diagram)
15. [قيود معروفة ونقاط للمناقشة](#15-قيود-معروفة-ونقاط-للمناقشة)
16. [ملفات قديمة (تم دمجها هنا)](#16-ملفات-قديمة-تم-دمجها-هنا)

---

## 1. نظرة عامة

**Career AI** منصة توظيف ذكية تربط **باحثي العمل** مع **الشركات**، وتستخدم **Google Gemini AI** لتقديم:

- تحليل السيرة الذاتية واكتشاف فجوة المهارات
- خريطة تعلم (Roadmap) وكورسات مقترحة
- اختبارات (Quiz) للتحقق من إتقان المهارات
- توليد CV احترافي متوافق مع أنظمة ATS
- مطابقة ذكية بين المرشحين والوظائف المنشورة

المشروع مقسّم إلى **مستودعين**:

| المستودع | المسار | التقنية |
|----------|--------|---------|
| **Backend** | `c:\xampp\htdocs\career_ai_la` | Laravel 10 + MySQL |
| **Frontend** | `c:\Users\HP\career_ai` | Flutter (Dart) |

```
┌─────────────────┐       REST API        ┌──────────────────┐       HTTPS        ┌──────────────┐
│  Flutter App    │  ──────────────────►  │  Laravel Backend │  ───────────────►  │ Google Gemini│
│  (Mobile/Web)   │   Bearer Token        │  (career_ai_la)  │                    │     API      │
└─────────────────┘                       └────────┬─────────┘                    └──────────────┘
                                                   │
                                                   ▼
                                            ┌──────────────┐
                                            │    MySQL     │
                                            └──────────────┘
```

**مهم:** تطبيق Flutter **لا يتصل مباشرة** بـ Gemini. كل طلبات AI تمر عبر Backend لحماية مفتاح API.

---

## 2. البنية التقنية

### Backend (Laravel)

| المكوّن | التقنية / المكتبة |
|---------|-------------------|
| Framework | Laravel 10, PHP 8.1+ |
| Authentication | Laravel Sanctum (Bearer Token) |
| AI | Google Gemini Flash (`gemini-flash-latest`) |
| PDF Parsing | `smalot/pdfparser` |
| PDF Generation | `barryvdh/laravel-dompdf` |
| Web Scraping (اختياري) | Symfony DomCrawler + DuckDuckGo |
| Payments | Stripe Checkout API |
| Notifications | Firebase Cloud Messaging (FCM) |
| Password Reset | Laravel Password Broker + Email |

### Frontend (Flutter)

| المكوّن | التقنية / المكتبة |
|---------|-------------------|
| HTTP Client | `http` |
| Auth Storage | `shared_preferences` |
| File Upload | `file_picker` |
| PDF | `pdf`, `printing` |
| Notifications | `firebase_core`, `flutter_local_notifications` |
| Google Sign-In | `google_sign_in` |
| Charts | `fl_chart` |

---

## 3. هيكل المشروع

### Backend — الملفات الأساسية

```
career_ai_la/
├── app/
│   ├── Http/Controllers/
│   │   ├── AiController.php          ← endpoints الذكاء الاصطناعي
│   │   ├── AuthController.php        ← تسجيل / دخول / profile / reset password
│   │   ├── JobController.php         ← الوظائف + dashboard الشركة + المرشحين
│   │   ├── AdminController.php       ← لوحة الأدمن
│   │   └── ComplaintController.php   ← الشكاوى
│   ├── Services/
│   │   ├── AiCareerService.php       ← كل استدعاءات Gemini
│   │   ├── CandidateScoringService.php ← مطابقة المرشح × وظيفة
│   │   └── FcmService.php            ← إشعارات Firebase
│   └── Models/
│       ├── User.php, JobSeeker.php, Company.php
│       ├── Job.php, JobCandidateScore.php
│       ├── UserResume.php, UserRoadmap.php, UserQuiz.php
│       └── Complaint.php
├── routes/api.php                    ← كل مسارات الـ API
├── config/services.php               ← GEMINI_API_KEY
├── database/migrations/              ← جداول DB
├── database/seeders/                 ← Admin + بيانات تجريبية
├── erd_final.puml                    ← مخطط ERD
├── class_diagram_final.puml          ← مخطط UML
├── api_tests_updated.http            ← اختبار API (REST Client)
└── README.md                         ← هذا الملف
```

### Frontend — الشاشات

```
career_ai/lib/
├── main.dart                         ← Routes + Firebase init
├── services/
│   ├── ai_api_service.dart           ← كل اتصالات Backend
│   ├── local_storage_service.dart
│   └── notification_service.dart
└── screens/
    ├── auth_screen.dart, sign_up_screen.dart
    ├── upload_cv_screen.dart         ← رفع CV / إدخال يدوي
    ├── cv_analysis_screen.dart       ← نتائج التحليل
    ├── roadmap_screen.dart           ← خريطة التعلم
    ├── quiz_screen.dart              ← الاختبار
    ├── user_dashboard.dart           ← dashboard الباحث
    ├── post_job_screen.dart          ← نشر وظيفة
    ├── company_dashboard.dart        ← dashboard الشركة
    ├── suggested_profiles_screen.dart
    ├── candidate_profile_screen.dart
    ├── job_details_screen.dart
    ├── ai_insights_screen.dart       ← معلومات AI (محتوى ثابت)
    ├── admin_login_screen.dart
    └── admin_dashboard_pro.dart
```

---

## 4. أدوار المستخدمين

| الدور | `role` في DB | الوصف |
|-------|-------------|-------|
| **باحث عن عمل** | `job` | يرفع CV، يتابع roadmap، يأخذ quiz، يولّد ATS CV |
| **شركة** | `company` | تنشر وظائف (مدفوعة)، ترى مرشحين مقترحين، dashboard |
| **أدمن** | `admin` | يوافق على الشركات، يؤكد الدفع، يدير الشكاوى |

---

## 5. الميزات حسب الدور

### باحث عن عمل (Job Seeker)

1. **التسجيل / الدخول** — email + password + governorate
2. **رفع CV** — PDF (حتى 2MB) أو إدخال يدوي (summary, skills, experience, education)
3. **تحليل فجوة المهارات** — Gemini يستخرج `current_skills` و `missing_skills`
4. **Roadmap + كورسات** — خطة تعلم + روابط كورسات مقترحة
5. **Quiz** — 5 أسئلة لكل مهارة؛ النجاح ≥ 70% يضيف المهارة لـ `completed_skills`
6. **ATS CV** — CV HTML محسّن → PDF للتحميل
7. **عرض الوظائف النشطة** — قائمة الوظائف المدفوعة
8. **الشكاوى** — إرسال شكوى للأدمن
9. **إعادة تعيين كلمة المرور** — عبر email

### شركة (Company)

1. **التسجيل** — مع رفع السجل التجاري (commercial register)
2. **التحقق** — الأدمن يوافق/يرفض (`verification_status`)
3. **نشر وظيفة** — $25 عبر Stripe (أو mock checkout)
4. **Dashboard** — إحصائيات، top 5 مرشحين، الوظائف الأخيرة
5. **Suggested Profiles** — كل المرشحين مرتبين حسب match %
6. **Job Candidates** — مرشحون لوظيفة محددة مع justification
7. **Candidate Profile** — تفاصيل مرشح + نسبة التوافق
8. **AI Insights** — شاشة توضيحية (محتوى static)

### أدمن (Admin)

1. **Dashboard** — revenue, active jobs, tickets, charts
2. **Company Verification** — approve / reject
3. **Payment Confirmation** — تفعيل الوظائف يدوياً + trigger AI scoring
4. **Complaints** — عرض وتحديث وحل الشكاوى
5. **Talent Activity** — نشاط AI (resumes, roadmaps, quizzes)

**بيانات الدخول الافتراضية (بعد seed):**
- Email: `admin@career.ai` (أو `ADMIN_EMAIL` من `.env`)
- Password: `admin123` (أو `ADMIN_PASSWORD` من `.env`)

---

## 6. قسم الذكاء الاصطناعي (AI)

### 6.1 النموذج المستخدم

```
Model: gemini-flash-latest
API:   https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent
Key:   GEMINI_API_KEY في .env
```

**لماذا Gemini Flash؟**
- سريع ومناسب للاستجابات الفورية (CV, quiz, roadmap)
- اقتصادي لمشروع تعليمي
- يدعم `systemInstruction` و structured JSON output
- تم اختبار عدة نماذج في `test_gemini.php` واعتماد الأحدث

**إعدادات التوليد:**
- `temperature = 0.7`
- `timeout = 60 ثانية`

### 6.2 طبقة الاستدعاء — `AiCareerService::callGemini()`

```
Prompt + SystemInstruction → cURL POST → Gemini API → استخراج النص → تنظيف JSON/HTML → إرجاع النتيجة
```

**Prompt Engineering:**
- كل مهمة لها `systemInstruction` يحدد الدور + schema المخرجات
- الرد يُنظَّف بـ regex (إزالة ```json wrappers، استخراج `{...}` أو `[...]`)
- عند فشل JSON → exception واضحة

### 6.3 وظائف AI — 6 استخدامات

| # | الوظيفة | الدالة | AI؟ | الوصف |
|---|---------|--------|-----|-------|
| 1 | Gap Analysis | `analyzeGap()` | ✅ | مقارنة CV بالوظيفة المستهدفة → مهارات حالية/ناقصة |
| 2 | Roadmap | `generateRoadmapAndCourses()` | ✅ | خطة تعلم Markdown + كورسات لكل مهارة |
| 3 | Quiz | `generateQuiz()` | ✅ | 5 أسئلة MCQ مع correct_answer |
| 4 | Quiz Submit | `AiController::submitQuiz()` | ❌ | تصحيح محلي؛ ≥70% → completed_skills |
| 5 | ATS CV | `generateAtsCv()` | ✅ | HTML ATS-friendly → dompdf → PDF |
| 6 | Candidate Scoring | `scoreOneCandidate()` | ✅ | match % بين مرشح ووظيفة (50+25+25) |

**ما ليس AI:**
- استخراج نص PDF → `Smalot\PdfParser`
- HTML → PDF → `laravel-dompdf`
- تصحيح Quiz → مقارنة strings في Laravel
- Fallback scoring → خوارزمية محلية

### 6.4 Gap Analysis — التفاصيل

**Endpoint:** `POST /api/ai/cv/gap-analysis`

**Input:**
- `target_job` (required)
- `cv_file` (PDF, max 2MB) **أو** `manual_text`

**Output JSON:**
```json
{
  "success": true,
  "resume_id": 1,
  "cv_text": "...",
  "data": {
    "current_skills": ["Flutter", "Dart"],
    "missing_skills": ["Firebase", "CI/CD"],
    "panel_feedback": "..."
  }
}
```

**بعد التحليل:** يُحفظ في `user_resumes` + يُشغَّل `scoreAllJobsForCandidate()` تلقائياً.

### 6.5 Roadmap — التفاصيل

**Endpoint:** `POST /api/ai/career/roadmap`

**Input:** `target_job`, `missing_skills[]`

**Output:**
```json
{
  "roadmap": "# Phase 1\n...",
  "skills_courses": [
    {
      "skill": "Firebase",
      "courses": [
        {"platform": "YouTube", "title": "...", "url": "..."}
      ]
    }
  ]
}
```

**ملاحظة:** يوجد كود `scrapeRealCoursesForSkill()` للبحث في DuckDuckGo لكنه **غير مربوط** حالياً — الكورسات من Gemini مباشرة.

### 6.6 Quiz — التفاصيل

**توليد:** `POST /api/ai/career/quiz` → `{ skills_to_test: ["Flutter"] }`

**تقديم:** `POST /api/ai/career/quiz/submit`
```json
{
  "quiz_id": 1,
  "answers": ["A) ...", "B) ...", ...]
}
```

**قواعد النجاح:**
- Score = (correct / total) × 100
- ≥ 70% → المهارات تُضاف إلى `user_roadmaps.completed_skills`

### 6.7 ATS CV — التفاصيل

**Endpoint:** `POST /api/ai/cv/generate`

**Prompt:** ي simulates "Hiring Panel" من 4 شخصيات (HR, Tech Lead, Hiring Manager, Career Advisor)

**البيانات تُجلب تلقائياً:**
- آخر CV من `user_resumes.original_text`
- `current_skills` + `completed_skills` (من roadmap)
- معلومات شخصية من `users` + `job_seekers`

**Output:** PDF stream (`Professional_ATS_CV.pdf`)

### 6.8 Candidate Scoring — مطابقة المرشحين

**الخدمة:** `CandidateScoringService`

**متى يُحسب؟**

| الحدث | الدالة |
|-------|--------|
| رفع CV جديد | `scoreAllJobsForCandidate()` |
| تفعيل وظيفة (دفع/أدمن) | `scoreAllCandidatesForJob()` |

**معايير Gemini (100 نقطة):**

| المعيار | النقاط |
|---------|--------|
| تطابق المهارات | 50 |
| تطابق المسمى الوظيفي | 25 |
| الخبرة من CV | 25 |

**التخزين:** `job_candidate_scores` (match_score + justification)

**Fallback:** إذا فشل AI (`match_score = -1`) → `localFallbackScore()` بنفس الأوزان

---

## 7. قاعدة البيانات

### الجداول الرئيسية

| الجدول | الغرض |
|--------|-------|
| `users` | حسابات (job / company / admin) |
| `job_seekers` | بيانات إضافية للباحث |
| `companies` | بيانات الشركة + verification_status |
| `jobs` | الوظائف المنشورة + is_paid |
| `user_resumes` | CV + مهارات حالية/ناقصة |
| `user_roadmaps` | roadmap + كورسات + completed_skills |
| `user_quizzes` | أسئلة + score |
| `job_candidate_scores` | match % بين مرشح ووظيفة |
| `complaints` | شكاوى المستخدمين |
| `company_candidates` | shortlist (شركة → مرشح) |
| `personal_access_tokens` | Sanctum tokens |

### العلاقات الأساسية

```
users (1) ──< user_resumes
users (1) ──< user_roadmaps
users (1) ──< user_quizzes
users (1) ──< jobs (company owns)
users (1) ──< job_candidate_scores (as candidate)
jobs  (1) ──< job_candidate_scores
users (1) ──|| job_seekers
users (1) ──|| companies
```

### Migrations

```bash
php artisan migrate
php artisan db:seed   # Admin + Test Data
```

---

## 8. مرجع الـ API

**Base URL:** `http://127.0.0.1:8000/api`

**Authentication:** `Authorization: Bearer {token}` (Sanctum)

### Auth

| Method | Endpoint | الوصف |
|--------|----------|-------|
| POST | `/auth/register` | تسجيل (job / company) |
| POST | `/auth/login` | دخول |
| POST | `/auth/logout` | خروج |
| GET | `/auth/me` | بيانات المستخدم |
| POST | `/auth/profile/update` | تحديث profile + avatar |
| POST | `/auth/fcm-token` | حفظ FCM token |
| POST | `/auth/forgot-password` | إرسال رابط reset |
| POST | `/auth/reset-password` | تحديث كلمة المرور |

### AI (Job Seeker)

| Method | Endpoint | AI؟ | الوصف |
|--------|----------|-----|-------|
| POST | `/ai/cv/gap-analysis` | ✅ | تحليل CV |
| GET | `/ai/cv/latest` | ❌ | آخر CV |
| POST | `/ai/career/roadmap` | ✅ | توليد roadmap |
| GET | `/ai/career/my-roadmap` | ❌ | roadmap نشط |
| POST | `/ai/career/update-progress` | ❌ | إكمال مهارة يدوياً |
| POST | `/ai/career/quiz` | ✅ | توليد quiz |
| POST | `/ai/career/quiz/submit` | ❌ | تقديم وتصحيح quiz |
| POST | `/ai/cv/generate` | ✅ | توليد ATS CV (PDF) |

### Jobs & Company

| Method | Endpoint | الوصف |
|--------|----------|-------|
| POST | `/jobs` | إنشاء وظيفة + Stripe checkout URL |
| GET | `/jobs` | الوظائف النشطة (is_paid=true) |
| GET | `/company/dashboard` | dashboard الشركة |
| GET | `/candidates/suggested` | كل المرشحين المقترحين |
| GET | `/candidates/{userId}?job_id=` | profile مرشح |
| GET | `/jobs/{jobId}/candidates` | مرشحون لوظيفة محددة |
| GET | `/jobs/payment-success?job_id=` | callback نجاح الدفع |
| GET | `/jobs/payment-cancel?job_id=` | callback إلغاء الدفع |

### Complaints

| Method | Endpoint | الوصف |
|--------|----------|-------|
| POST | `/complaints` | إرسال شكوى |
| GET | `/complaints/mine` | شكاوى المستخدم |

### Admin (middleware: `auth:sanctum` + `admin`)

| Method | Endpoint | الوصف |
|--------|----------|-------|
| POST | `/admin/login` | دخول أدمن |
| POST | `/admin/logout` | خروج |
| GET | `/admin/dashboard` | إحصائيات |
| GET | `/admin/verifications/companies` | شركات pending |
| POST | `/admin/verifications/companies/{id}/approve` | موافقة |
| POST | `/admin/verifications/companies/{id}/reject` | رفض |
| GET | `/admin/payments/jobs` | وظائف غير مدفوعة |
| POST | `/admin/payments/jobs/{id}/confirm` | تأكيد دفع + AI scoring |
| GET | `/admin/complaints` | الشكاوى |
| GET | `/admin/complaints/{id}` | تفاصيل شكوى |
| PATCH | `/admin/complaints/{id}` | تحديث شكوى |
| GET | `/admin/talent-activity` | نشاط AI |

---

## 9. تدفقات العمل (User Flows)

### باحث عن عمل — الرحلة الكاملة

```
تسجيل → رفع CV/يدوي → Gap Analysis (AI)
    ↓
Roadmap + كورسات (AI)
    ↓
Quiz لكل مهارة (AI) → Submit (محلي) → إذا ≥70%: completed_skills
    ↓
Generate ATS CV (AI → PDF)
```

### شركة — الرحلة الكاملة

```
تسجيل + رفع سجل تجاري → انتظار موافقة Admin
    ↓
نشر وظيفة → Stripe $25 → is_paid=true
    ↓
AI scoring لكل المرشحين → job_candidate_scores
    ↓
Dashboard / Suggested Profiles / Job Candidates
```

### Admin

```
Login → Dashboard
    ├── Approve/Reject Companies (+ FCM notification)
    ├── Confirm Job Payments (+ AI scoring + FCM)
    └── Manage Complaints
```

---

## 10. التثبيت والتشغيل

### المتطلبات

- PHP 8.1+, Composer, MySQL (XAMPP)
- Flutter SDK 3.11+
- Node.js (اختياري — Vite)
- Gemini API Key
- Firebase project (للإشعارات — اختياري)
- Stripe Secret (للدفع — اختياري)

### Backend

```bash
cd c:\xampp\htdocs\career_ai_la

composer install
cp .env.example .env
# عدّل .env (DB, GEMINI_API_KEY, ...)

php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link

php artisan serve
# يعمل على http://127.0.0.1:8000
```

### Frontend

```bash
cd c:\Users\HP\career_ai

flutter pub get
flutter run
```

**تغيير عنوان Backend في Flutter:**

```dart
// lib/services/ai_api_service.dart
static const String _host = 'http://127.0.0.1:8000';
// Android Emulator: http://10.0.2.2:8000
// جهاز حقيقي: http://192.168.x.x:8000
```

---

## 11. متغيرات البيئة (.env)

أضف هذه المتغيرات إلى `.env`:

```env
# ── Database ──
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=career_ai
DB_USERNAME=root
DB_PASSWORD=

# ── AI ──
GEMINI_API_KEY=your_gemini_api_key_here

# ── Admin (Seeder) ──
ADMIN_EMAIL=admin@career.ai
ADMIN_PASSWORD=admin123

# ── Stripe (اختياري — بدونه يستخدم mock checkout) ──
STRIPE_SECRET=sk_test_...

# ── Firebase / FCM (اختياري) ──
FIREBASE_PROJECT_ID=career-ai-shahed
FIREBASE_CREDENTIALS=career-ai-shahed-da65f470655a.json

# ── Mail (Password Reset) ──
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your@gmail.com
MAIL_FROM_NAME="Career AI"
```

---

## 12. الاختبار

### اختبار API (REST Client)

افتح `api_tests_updated.http` في VS Code مع extension **REST Client**:

1. سجّل / ادخل → انسخ Token
2. ضع Token في `@token` variable
3. جرّب endpoints بالترتيب: gap-analysis → roadmap → quiz → submit → generate CV

### اختبار يدوي (Flutter)

```
1. Register as job seeker
2. Upload CV or manual entry
3. View analysis → Generate Roadmap
4. Take Quiz for a skill → Pass (70%+)
5. Generate ATS CV → verify new skills included
6. Register as company → Post job → Pay
7. View suggested candidates with match %
```

### اختبار Gemini

```bash
php test_gemini.php
```

### اختبار AI Endpoints

```bash
php test_ai_endpoints.php
```

---

## 13. الملفات المهمة

### Backend — Services & Controllers

| الملف | المسؤولية |
|-------|-----------|
| `app/Services/AiCareerService.php` | كل استدعاءات Gemini + PDF parsing |
| `app/Services/CandidateScoringService.php` | AI matching + fallback |
| `app/Http/Controllers/AiController.php` | AI endpoints للباحث |
| `app/Http/Controllers/JobController.php` | Jobs + company dashboard + candidates |
| `app/Http/Controllers/AdminController.php` | Admin panel |
| `app/Http/Controllers/AuthController.php` | Auth + profile |
| `routes/api.php` | كل routes |

### Frontend

| الملف | المسؤولية |
|-------|-----------|
| `lib/services/ai_api_service.dart` | HTTP client لكل API |
| `lib/screens/upload_cv_screen.dart` | رفع CV |
| `lib/screens/cv_analysis_screen.dart` | عرض التحليل |
| `lib/screens/roadmap_screen.dart` | Roadmap + كورسات |
| `lib/screens/quiz_screen.dart` | Quiz |
| `lib/screens/user_dashboard.dart` | Dashboard الباحث |
| `lib/screens/company_dashboard.dart` | Dashboard الشركة |

---

## 14. المخططات (ERD & Class Diagram)

| الملف | الوصف |
|-------|-------|
| `erd_final.puml` | مخطط قاعدة البيانات (PlantUML) |
| `class_diagram_final.puml` | مخطط UML للـ classes |
| `out/erd_final/career_ai_la_ERD.svg` | ERD م rendered |
| `out/class_diagram_final/CareerAI_ClassDiagram.svg` | Class diagram rendered |

**لتوليد SVG من PlantUML:**
```bash
# باستخدام PlantUML CLI أو extension في VS Code
plantuml erd_final.puml
plantuml class_diagram_final.puml
```

---

## 15. قيود معروفة ونقاط للمناقشة

### نقاط قوة
- فصل Frontend / Backend / AI (أمان API key)
- دورة حياة كاملة: تحليل → تعلم → اختبار → CV
- AI matching ثنائي الاتجاه (CV جديد / وظيفة جديدة)
- Fallback محلي عند فشل Gemini
- Prompts منظمة مع JSON schema

### قيود / نقاط ضعف (كن صريحاً في المناقشة)

| # | القيد | التفاصيل |
|---|-------|----------|
| 1 | `AiInsightsScreen` | محتوى **ثابت (static)** — ليس من AI حقيقي |
| 2 | روابط الكورسات | من Gemini مباشرة — قد تكون غير دقيقة (hallucination) |
| 3 | `scrapeRealCoursesForSkill()` | موجود لكن **غير مستخدم** |
| 4 | `rankCandidatesForCompany()` | موجود لكن **غير مستدعى** (يُستخدم `scoreOneCandidate` بدلاً) |
| 5 | PDF scanned (صورة) | لا OCR — فشل استخراج النص |
| 6 | `test_gemini.php` | يحتوي API key مكشوف — للاختبار فقط |
| 7 | Quiz grading | محلي فقط — AI يولّد ولا يصحّح |

---

## 16. ملفات قديمة (تم دمجها هنا)

الملفات التالية كانت موزّعة في المشروع. **محتواها مدمج في هذا README** — لا حاجة لقراءتها كلها:

| الملف القديم | ما يغطيه (الآن في README) |
|-------------|--------------------------|
| `00_START_HERE_FIRST.md` | البدء السريع |
| `START_HERE.md` | البدء السريع |
| `README_COMPLETE.md` | الدليل الشامل |
| `FINAL_SUMMARY.md` | الملخص النهائي |
| `COMPLETE_SOLUTION.md` | الحل الكامل |
| `IMPLEMENTATION_COMPLETE.md` | تفاصيل التنفيذ |
| `API_FIXES_DOCUMENTATION.md` | توثيق API |
| `FIXES_SUMMARY_AR.md` | ملخص الإصلاحات |

> **نصيحة:** اعتمد على `README.md` (هذا الملف) كمرجع واحد. الملفات القديمة يمكن حذفها لاحقاً إذا أردت.

---

## أسئلة شائعة (FAQ)

**س: كيف يعمل تحليل CV؟**  
> استخراج نص PDF بـ PHP → إرسال النص + target_job لـ Gemini → JSON بالمهارات.

**س: لماذا Gemini وليس ChatGPT؟**  
> Flash سريع، اقتصادي، يدعم system instructions، وتم اختباره.

**س: كيف يُقيَّم الكويز؟**  
> تصحيح **محلي** — مقارنة إجابات المستخدم مع `correct_answer`. AI يولّد فقط.

**س: متى يُحسب match score؟**  
> عند رفع CV أو تفعيل وظيفة. يُخزَّن في `job_candidate_scores`.

**س: هل AI يعمل offline؟**  
> لا — يحتاج إنترنت + Gemini API key صالح.

**س: ما threshold نجاح الكويز؟**  
> 70%. عند النجاح تُضاف المهارة إلى `completed_skills`.

---

## الترخيص

Laravel framework — [MIT License](https://opensource.org/licenses/MIT)

---

*آخر تحديث: يونيو 2026*
