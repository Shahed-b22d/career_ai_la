<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Job;
use App\Models\JobSeeker;
use App\Models\User;
use App\Models\UserResume;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EnhanceUsersSeeder extends Seeder
{
    public function run(): void
    {
        // ─────────────────────────────────────────────────────────────────
        // 1) إصلاح الـ Users الناقصين (CV + governorate + job_seeker/company)
        // ─────────────────────────────────────────────────────────────────
        $fixes = [
            // Test User (id 4)
            [
                'email'       => 'test@example.com',
                'role'        => 'job',
                'phone'       => '0991111000',
                'governorate' => 'Damascus / دمشق',
                'target_job'  => 'Web Developer',
                'current_skills' => ['HTML', 'CSS', 'JavaScript', 'Bootstrap'],
                'missing_skills' => ['React.js', 'Node.js', 'MongoDB'],
                'cv_text' => 'Junior web developer with 1 year experience building responsive websites.',
            ],
            // Sarah Ali (id 5)
            [
                'email'       => 'sarah.user@gmail.com',
                'role'        => 'job',
                'phone'       => '0954367254',
                'governorate' => 'Damascus / دمشق',
                'target_job'  => 'UI/UX Designer',
                'current_skills' => ['Figma', 'Adobe XD', 'Wireframing', 'Prototyping'],
                'missing_skills' => ['User Research', 'Design Systems', 'Accessibility (WCAG)'],
                'cv_text' => 'UI/UX designer focused on mobile and web app design with 2 years experience.',
            ],
            // Aisha Developer (id 7)
            [
                'email'       => 'aisha.user@gmail.com',
                'role'        => 'job',
                'phone'       => '0954362459',
                'governorate' => 'Rif Dimashq / ريف دمشق',
                'target_job'  => 'Full Stack Developer',
                'current_skills' => ['Laravel', 'PHP', 'Vue.js', 'MySQL', 'REST APIs'],
                'missing_skills' => ['Docker', 'CI/CD', 'AWS'],
                'cv_text' => 'Full-stack developer specialized in Laravel and Vue.js with 2 years experience.',
            ],
            // Ola arnous (id 10)
            [
                'email'       => 'olaen@gmail.com',
                'role'        => 'job',
                'phone'       => '0954638425',
                'governorate' => 'Idlib / إدلب',
                'target_job'  => 'Content Writer',
                'current_skills' => ['Arabic Writing', 'English Writing', 'SEO', 'Editing'],
                'missing_skills' => ['Copywriting', 'Technical Writing', 'Content Strategy'],
                'cv_text' => 'Bilingual content writer with experience in blogs, articles and social media.',
            ],
            // sarah (id 1) - just fix governorate
            [
                'email'       => 'saraha21hg@gmail.com',
                'role'        => 'job',
                'phone'       => '0954367254',
                'governorate' => 'Damascus / دمشق',
                'target_job'  => null, // already has CVs
            ],
            // Ahmed (id 2) - company missing governorate
            [
                'email'       => 'ahmed@gmail.com',
                'role'        => 'company',
                'phone'       => '0956137543',
                'governorate' => 'Damascus / دمشق',
                'business_type' => 'Programming',
                'description' => 'Software development company specialized in custom web and mobile solutions.',
            ],
            // Ahmed Tech Co (id 6) - company missing record + governorate
            [
                'email'       => 'ahmed.company@gmail.com',
                'role'        => 'company',
                'phone'       => '0956137543',
                'governorate' => 'Aleppo / حلب',
                'business_type' => 'Software Development',
                'description' => 'Tech company offering software development services for startups and enterprises.',
            ],
        ];

        foreach ($fixes as $f) {
            $user = User::where('email', $f['email'])->first();
            if (!$user) continue;

            // Update missing user fields
            $user->update([
                'phone'       => $user->phone ?: ($f['phone'] ?? null),
                'governorate' => $user->governorate ?: ($f['governorate'] ?? null),
                'business_type' => ($f['role'] === 'company') ? ($user->business_type ?: ($f['business_type'] ?? null)) : $user->business_type,
            ]);

            if ($f['role'] === 'job') {
                JobSeeker::firstOrCreate(
                    ['user_id' => $user->id],
                    ['phone' => $f['phone'] ?? null]
                );

                if (!empty($f['target_job'])) {
                    UserResume::firstOrCreate(
                        ['user_id' => $user->id, 'target_job' => $f['target_job']],
                        [
                            'original_text'  => $f['cv_text'],
                            'current_skills' => $f['current_skills'],
                            'missing_skills' => $f['missing_skills'],
                        ]
                    );
                }
            } else { // company
                Company::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'phone'               => $f['phone'] ?? null,
                        'business_type'       => $f['business_type'] ?? null,
                        'description'         => $f['description'] ?? null,
                        'verification_status' => 'approved',
                    ]
                );
            }
        }

        // ─────────────────────────────────────────────────────────────────
        // 2) Job Seekers جدد - تخصصات متنوّعة (مش معلوماتية)
        // ─────────────────────────────────────────────────────────────────
        $newSeekers = [
            [
                'name'        => 'Karim Haddad',
                'email'       => 'karim.chef@test.com',
                'governorate' => 'Damascus / دمشق',
                'phone'       => '0993200001',
                'target_job'  => 'Head Chef',
                'current_skills' => ['Mediterranean Cuisine', 'Menu Planning', 'Kitchen Management', 'Food Safety'],
                'missing_skills' => ['French Pastry', 'Molecular Gastronomy', 'Cost Control'],
                'cv_text' => 'Professional chef with 8 years experience in fine dining restaurants.',
            ],
            [
                'name'        => 'Reem Saadeh',
                'email'       => 'reem.lawyer@test.com',
                'governorate' => 'Damascus / دمشق',
                'phone'       => '0993200002',
                'target_job'  => 'Corporate Lawyer',
                'current_skills' => ['Contract Law', 'Legal Research', 'Litigation', 'Arabic & English Drafting'],
                'missing_skills' => ['International Trade Law', 'IP Law', 'Arbitration'],
                'cv_text' => 'Lawyer with 5 years experience in corporate and commercial law.',
            ],
            [
                'name'        => 'Yara Khoury',
                'email'       => 'yara.pharma@test.com',
                'governorate' => 'Lattakia / اللاذقية',
                'phone'       => '0993200003',
                'target_job'  => 'Clinical Pharmacist',
                'current_skills' => ['Drug Dispensing', 'Patient Counseling', 'Inventory Management', 'Prescription Review'],
                'missing_skills' => ['Hospital Pharmacy', 'Clinical Trials', 'Pharmacovigilance'],
                'cv_text' => 'Licensed pharmacist with 4 years retail and hospital pharmacy experience.',
            ],
            [
                'name'        => 'Ziad Ibrahim',
                'email'       => 'ziad.translator@test.com',
                'governorate' => 'Aleppo / حلب',
                'phone'       => '0993200004',
                'target_job'  => 'English-Arabic Translator',
                'current_skills' => ['Translation', 'Proofreading', 'CAT Tools', 'Subtitling'],
                'missing_skills' => ['Legal Translation', 'Medical Translation', 'Localization'],
                'cv_text' => 'Certified translator with 6 years freelance experience across multiple domains.',
            ],
            [
                'name'        => 'Maya Suleiman',
                'email'       => 'maya.journalist@test.com',
                'governorate' => 'Damascus / دمشق',
                'phone'       => '0993200005',
                'target_job'  => 'Investigative Journalist',
                'current_skills' => ['News Writing', 'Interviewing', 'Research', 'Editing'],
                'missing_skills' => ['Data Journalism', 'Documentary Production', 'Multimedia Storytelling'],
                'cv_text' => 'Journalist with 3 years experience in print and online news outlets.',
            ],
            [
                'name'        => 'Fadi Aboud',
                'email'       => 'fadi.mechanic@test.com',
                'governorate' => 'Homs / حمص',
                'phone'       => '0993200006',
                'target_job'  => 'Auto Mechanic',
                'current_skills' => ['Engine Repair', 'Brake Systems', 'Electrical Diagnostics', 'Transmission Service'],
                'missing_skills' => ['Hybrid/EV Systems', 'Computer Diagnostics', 'European Car Specialization'],
                'cv_text' => 'Auto mechanic with 7 years hands-on experience in vehicle repair and maintenance.',
            ],
            [
                'name'        => 'Nadia Tannous',
                'email'       => 'nadia.photo@test.com',
                'governorate' => 'Tartus / طرطوس',
                'phone'       => '0993200007',
                'target_job'  => 'Wedding Photographer',
                'current_skills' => ['Portrait Photography', 'Lightroom', 'Photoshop', 'Studio Lighting'],
                'missing_skills' => ['Drone Photography', 'Video Editing', 'Cinematic Storytelling'],
                'cv_text' => 'Professional photographer with 5 years specializing in events and portraits.',
            ],
            [
                'name'        => 'Samer Najjar',
                'email'       => 'samer.guide@test.com',
                'governorate' => 'Damascus / دمشق',
                'phone'       => '0993200008',
                'target_job'  => 'Tour Guide',
                'current_skills' => ['Syrian History', 'English Communication', 'Customer Service', 'Group Management'],
                'missing_skills' => ['French Language', 'Tour Operations Software', 'First Aid Certification'],
                'cv_text' => 'Licensed tour guide with deep knowledge of Syrian heritage and ancient sites.',
            ],
        ];

        foreach ($newSeekers as $s) {
            $user = User::updateOrCreate(
                ['email' => $s['email']],
                [
                    'name'        => $s['name'],
                    'password'    => Hash::make('password123'),
                    'role'        => 'job',
                    'phone'       => $s['phone'],
                    'governorate' => $s['governorate'],
                ]
            );

            JobSeeker::firstOrCreate(
                ['user_id' => $user->id],
                ['phone' => $s['phone']]
            );

            UserResume::firstOrCreate(
                ['user_id' => $user->id, 'target_job' => $s['target_job']],
                [
                    'original_text'  => $s['cv_text'],
                    'current_skills' => $s['current_skills'],
                    'missing_skills' => $s['missing_skills'],
                ]
            );
        }

        // ─────────────────────────────────────────────────────────────────
        // 3) شركات جدد - قطاعات متنوّعة (مش كلها تقنية)
        // ─────────────────────────────────────────────────────────────────
        $newCompanies = [
            [
                'name' => 'Damasco Restaurant Group',
                'email' => 'damasco@test.com',
                'governorate' => 'Damascus / دمشق',
                'phone' => '0116100001',
                'business_type' => 'Restaurants / Food Service',
                'description' => 'Chain of fine-dining restaurants serving authentic Syrian and Mediterranean cuisine.',
                'jobs' => [
                    ['title' => 'Head Chef', 'job_type' => 'Full-time', 'location' => 'Damascus', 'salary' => '$900/month',
                     'description' => 'Lead our kitchen team and develop seasonal menus.',
                     'requirements' => 'Mediterranean Cuisine, Menu Planning, Kitchen Management, 5+ years experience'],
                    ['title' => 'Restaurant Manager', 'job_type' => 'Full-time', 'location' => 'Damascus', 'salary' => '$700/month',
                     'description' => 'Manage daily operations and staff of our flagship restaurant.',
                     'requirements' => 'Hospitality Management, Customer Service, Inventory Control'],
                ],
            ],
            [
                'name' => 'Sham Properties',
                'email' => 'sham.properties@test.com',
                'governorate' => 'Damascus / دمشق',
                'phone' => '0116100002',
                'business_type' => 'Real Estate',
                'description' => 'Leading real estate agency in Syria handling sales, rentals, and property management.',
                'jobs' => [
                    ['title' => 'Real Estate Agent', 'job_type' => 'Full-time', 'location' => 'Damascus', 'salary' => 'Commission-based',
                     'description' => 'Sell and rent residential and commercial properties.',
                     'requirements' => 'Sales Skills, Negotiation, Customer Relations, Driving License'],
                ],
            ],
            [
                'name' => 'Justice Law Office',
                'email' => 'justice.law@test.com',
                'governorate' => 'Aleppo / حلب',
                'phone' => '0116100003',
                'business_type' => 'Legal Services',
                'description' => 'Established law firm specializing in corporate, commercial, and family law.',
                'jobs' => [
                    ['title' => 'Corporate Lawyer', 'job_type' => 'Full-time', 'location' => 'Aleppo', 'salary' => '$1100/month',
                     'description' => 'Handle corporate legal matters and contract negotiations.',
                     'requirements' => 'Contract Law, Litigation, Legal Research, Bar Admission'],
                    ['title' => 'Legal Assistant', 'job_type' => 'Full-time', 'location' => 'Aleppo', 'salary' => '$500/month',
                     'description' => 'Support attorneys with research, filing, and client coordination.',
                     'requirements' => 'Legal Documentation, MS Office, Arabic & English'],
                ],
            ],
            [
                'name' => 'Ancient Syria Tours',
                'email' => 'ancientsyria@test.com',
                'governorate' => 'Damascus / دمشق',
                'phone' => '0116100004',
                'business_type' => 'Tourism / Travel',
                'description' => 'Tour operator offering cultural and historical tours across Syria.',
                'jobs' => [
                    ['title' => 'Tour Guide', 'job_type' => 'Part-time', 'location' => 'Damascus', 'salary' => '$500/month',
                     'description' => 'Lead cultural tours of historical sites in Damascus and beyond.',
                     'requirements' => 'Syrian History, English/French, Customer Service, Tour Guide License'],
                    ['title' => 'Travel Consultant', 'job_type' => 'Full-time', 'location' => 'Damascus', 'salary' => '$600/month',
                     'description' => 'Plan custom travel packages and assist with bookings.',
                     'requirements' => 'Travel Planning, GDS Systems, Customer Service'],
                ],
            ],
            [
                'name' => 'Aleppo Textile Industries',
                'email' => 'aleppo.textile@test.com',
                'governorate' => 'Aleppo / حلب',
                'phone' => '0116100005',
                'business_type' => 'Manufacturing',
                'description' => 'Traditional textile manufacturer producing fabrics for local and export markets.',
                'jobs' => [
                    ['title' => 'Production Supervisor', 'job_type' => 'Full-time', 'location' => 'Aleppo', 'salary' => '$700/month',
                     'description' => 'Supervise production lines and ensure quality standards.',
                     'requirements' => 'Manufacturing Operations, Quality Control, Team Leadership'],
                    ['title' => 'Quality Control Inspector', 'job_type' => 'Full-time', 'location' => 'Aleppo', 'salary' => '$450/month',
                     'description' => 'Inspect textile products to ensure they meet quality specifications.',
                     'requirements' => 'Quality Assurance, Attention to Detail, ISO Standards'],
                ],
            ],
            [
                'name' => 'Swift Cargo Syria',
                'email' => 'swift.cargo@test.com',
                'governorate' => 'Lattakia / اللاذقية',
                'phone' => '0116100006',
                'business_type' => 'Logistics / Shipping',
                'description' => 'International logistics and freight forwarding company operating from Lattakia port.',
                'jobs' => [
                    ['title' => 'Logistics Coordinator', 'job_type' => 'Full-time', 'location' => 'Lattakia', 'salary' => '$650/month',
                     'description' => 'Coordinate shipments and manage customs documentation.',
                     'requirements' => 'Supply Chain, Customs Procedures, MS Excel, English'],
                    ['title' => 'Warehouse Manager', 'job_type' => 'Full-time', 'location' => 'Lattakia', 'salary' => '$800/month',
                     'description' => 'Oversee warehouse operations and inventory management.',
                     'requirements' => 'Warehouse Management, Inventory Systems, Team Leadership'],
                ],
            ],
            [
                'name' => 'Sham News Network',
                'email' => 'shamnews@test.com',
                'governorate' => 'Damascus / دمشق',
                'phone' => '0116100007',
                'business_type' => 'Media / Press',
                'description' => 'Independent news network covering local and regional events.',
                'jobs' => [
                    ['title' => 'News Reporter', 'job_type' => 'Full-time', 'location' => 'Damascus', 'salary' => '$600/month',
                     'description' => 'Report on local news and conduct field interviews.',
                     'requirements' => 'News Writing, Interviewing, Research, Arabic & English'],
                    ['title' => 'Video Editor', 'job_type' => 'Full-time', 'location' => 'Damascus', 'salary' => '$700/month',
                     'description' => 'Edit news segments and documentaries.',
                     'requirements' => 'Adobe Premiere, After Effects, Color Grading'],
                ],
            ],
            [
                'name' => 'Elegance Boutique',
                'email' => 'elegance@test.com',
                'governorate' => 'Damascus / دمشق',
                'phone' => '0116100008',
                'business_type' => 'Retail / Fashion',
                'description' => 'Upscale fashion boutique chain offering local and imported designer wear.',
                'jobs' => [
                    ['title' => 'Fashion Buyer', 'job_type' => 'Full-time', 'location' => 'Damascus', 'salary' => '$750/month',
                     'description' => 'Source and purchase fashion items for our retail stores.',
                     'requirements' => 'Fashion Trends, Negotiation, Vendor Management, Travel Flexibility'],
                    ['title' => 'Sales Associate', 'job_type' => 'Full-time', 'location' => 'Damascus', 'salary' => '$400/month',
                     'description' => 'Assist customers and drive sales in our boutique.',
                     'requirements' => 'Customer Service, Sales, Fashion Knowledge'],
                ],
            ],
        ];

        foreach ($newCompanies as $c) {
            $user = User::updateOrCreate(
                ['email' => $c['email']],
                [
                    'name'          => $c['name'],
                    'password'      => Hash::make('password123'),
                    'role'          => 'company',
                    'phone'         => $c['phone'],
                    'governorate'   => $c['governorate'],
                    'business_type' => $c['business_type'],
                ]
            );

            Company::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'phone'               => $c['phone'],
                    'business_type'       => $c['business_type'],
                    'description'         => $c['description'],
                    'verification_status' => 'approved',
                ]
            );

            foreach ($c['jobs'] as $j) {
                Job::firstOrCreate(
                    ['user_id' => $user->id, 'title' => $j['title']],
                    [
                        'job_type'     => $j['job_type'],
                        'location'     => $j['location'],
                        'salary'       => $j['salary'],
                        'description'  => $j['description'],
                        'requirements' => $j['requirements'],
                        'is_paid'      => true,
                    ]
                );
            }
        }

        $this->command->info('✅ Enhancement seeded: incomplete users fixed + 8 new seekers + 8 new companies (diverse fields).');
        $this->command->info('🔑 New users password: password123');
    }
}
