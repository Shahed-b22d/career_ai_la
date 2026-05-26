<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Job;
use App\Models\JobSeeker;
use App\Models\User;
use App\Models\UserResume;
use App\Models\UserRoadmap;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // ─── 1. Job Seekers ──────────────────────────────────────────────────
        $seekers = [
            [
                'name'        => 'Ahmad Khalil',
                'email'       => 'ahmad@test.com',
                'governorate' => 'Damascus / دمشق',
                'phone'       => '0991234567',
                'target_job'  => 'Accountant',
                'current_skills'  => ['Financial Reporting', 'Excel', 'QuickBooks', 'Tax Filing', 'Budgeting'],
                'missing_skills'  => ['SAP ERP', 'IFRS Standards', 'Financial Forecasting'],
                'cv_text'     => 'Accountant with 3 years experience in financial reporting, tax filing, and budget management for SMEs.',
            ],
            [
                'name'        => 'Sara Mahmoud',
                'email'       => 'sara@test.com',
                'governorate' => 'Aleppo / حلب',
                'phone'       => '0992345678',
                'target_job'  => 'Graphic Designer',
                'current_skills'  => ['Adobe Photoshop', 'Illustrator', 'InDesign', 'Brand Identity', 'Typography'],
                'missing_skills'  => ['Motion Graphics', 'After Effects', '3D Design'],
                'cv_text'     => 'Graphic designer with strong portfolio in branding, print, and digital media design.',
            ],
            [
                'name'        => 'Omar Hassan',
                'email'       => 'omar@test.com',
                'governorate' => 'Homs / حمص',
                'phone'       => '0993456789',
                'target_job'  => 'Civil Engineer',
                'current_skills'  => ['AutoCAD', 'Structural Analysis', 'Project Management', 'Site Supervision', 'Revit'],
                'missing_skills'  => ['BIM Modeling', 'Cost Estimation Software', 'Green Building Standards'],
                'cv_text'     => 'Civil engineer with 4 years experience in residential and commercial construction projects.',
            ],
            [
                'name'        => 'Lina Nasser',
                'email'       => 'lina@test.com',
                'governorate' => 'Lattakia / اللاذقية',
                'phone'       => '0994567890',
                'target_job'  => 'Marketing Specialist',
                'current_skills'  => ['Social Media Marketing', 'Content Creation', 'SEO', 'Google Ads', 'Analytics'],
                'missing_skills'  => ['Email Marketing Automation', 'Video Production', 'Influencer Marketing'],
                'cv_text'     => 'Marketing specialist with expertise in digital campaigns, social media growth, and brand awareness.',
            ],
            [
                'name'        => 'Kareem Saleh',
                'email'       => 'kareem@test.com',
                'governorate' => 'Tartus / طرطوس',
                'phone'       => '0995678901',
                'target_job'  => 'Sales Representative',
                'current_skills'  => ['B2B Sales', 'CRM Tools', 'Negotiation', 'Lead Generation', 'Client Relations'],
                'missing_skills'  => ['Salesforce CRM', 'Sales Forecasting', 'Key Account Management'],
                'cv_text'     => 'Sales professional with proven track record in B2B sales and client acquisition across multiple industries.',
            ],
            [
                'name'        => 'Nour Issa',
                'email'       => 'nour@test.com',
                'governorate' => 'Damascus / دمشق',
                'phone'       => '0996789012',
                'target_job'  => 'HR Specialist',
                'current_skills'  => ['Recruitment', 'Onboarding', 'Employee Relations', 'Labor Law', 'Performance Reviews'],
                'missing_skills'  => ['HR Analytics', 'Compensation & Benefits Design', 'HRIS Systems'],
                'cv_text'     => 'HR specialist with 3 years experience in talent acquisition, employee engagement, and HR operations.',
            ],
            [
                'name'        => 'Tarek Yousef',
                'email'       => 'tarek@test.com',
                'governorate' => 'Hama / حماة',
                'phone'       => '0997890123',
                'target_job'  => 'Electrical Engineer',
                'current_skills'  => ['Circuit Design', 'AutoCAD Electrical', 'PLC Programming', 'Power Systems', 'MATLAB'],
                'missing_skills'  => ['Renewable Energy Systems', 'IoT Integration', 'Energy Auditing'],
                'cv_text'     => 'Electrical engineer specializing in industrial automation and power distribution systems.',
            ],
            [
                'name'        => 'Rima Darwish',
                'email'       => 'rima@test.com',
                'governorate' => 'Aleppo / حلب',
                'phone'       => '0998901234',
                'target_job'  => 'Nurse',
                'current_skills'  => ['Patient Care', 'IV Therapy', 'Vital Signs Monitoring', 'Emergency Response', 'Medical Documentation'],
                'missing_skills'  => ['ICU Protocols', 'Ventilator Management', 'Advanced Cardiac Life Support'],
                'cv_text'     => 'Registered nurse with 5 years experience in general ward and emergency department settings.',
            ],
            [
                'name'        => 'Bassam Khoury',
                'email'       => 'bassam@test.com',
                'governorate' => 'Damascus / دمشق',
                'phone'       => '0999012345',
                'target_job'  => 'Architect',
                'current_skills'  => ['AutoCAD', 'SketchUp', 'Revit', 'Interior Design', '3D Rendering'],
                'missing_skills'  => ['Sustainable Architecture', 'BIM Advanced', 'Urban Planning'],
                'cv_text'     => 'Architect with experience in residential and commercial building design, interior spaces, and 3D visualization.',
            ],
            [
                'name'        => 'Hala Mansour',
                'email'       => 'hala@test.com',
                'governorate' => 'Homs / حمص',
                'phone'       => '0990123456',
                'target_job'  => 'English Teacher',
                'current_skills'  => ['Curriculum Development', 'Classroom Management', 'IELTS Preparation', 'Grammar Instruction', 'Student Assessment'],
                'missing_skills'  => ['Online Teaching Tools', 'TEFL Certification', 'Differentiated Instruction'],
                'cv_text'     => 'English language teacher with 6 years experience teaching all levels from beginner to advanced.',
            ],
        ];

        foreach ($seekers as $s) {
            $user = User::firstOrCreate(
                ['email' => $s['email']],
                [
                    'name'        => $s['name'],
                    'password'    => Hash::make('password123'),
                    'role'        => 'job',
                    'phone'       => $s['phone'],
                    'governorate' => $s['governorate'],
                ]
            );

            JobSeeker::firstOrCreate(['user_id' => $user->id], ['phone' => $s['phone']]);

            UserResume::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'target_job'     => $s['target_job'],
                    'original_text'  => $s['cv_text'],
                    'current_skills' => $s['current_skills'],
                    'missing_skills' => $s['missing_skills'],
                ]
            );

            // Add active roadmap
            UserRoadmap::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'target_job'        => $s['target_job'],
                    'missing_skills'    => $s['missing_skills'],
                    'completed_skills'  => array_slice($s['missing_skills'], 0, 1),
                    'is_active'         => true,
                ]
            );
        }

        // ─── 2. Companies ────────────────────────────────────────────────────
        $companies = [
            [
                'name'          => 'Al-Noor Accounting Firm',
                'email'         => 'alnoor@test.com',
                'governorate'   => 'Damascus / دمشق',
                'phone'         => '0111234567',
                'business_type' => 'Finance / Banking',
                'description'   => 'Leading accounting and auditing firm serving businesses across Syria.',
                'jobs'          => [
                    [
                        'title'        => 'Senior Accountant',
                        'job_type'     => 'Full-time',
                        'location'     => 'Damascus',
                        'salary'       => '$800/month',
                        'description'  => 'We need an experienced accountant to manage financial reporting and tax compliance.',
                        'requirements' => 'Financial Reporting, Excel, QuickBooks, Tax Filing, 3+ years experience',
                        'is_paid'      => true,
                    ],
                    [
                        'title'        => 'HR Specialist',
                        'job_type'     => 'Full-time',
                        'location'     => 'Damascus',
                        'salary'       => '$700/month',
                        'description'  => 'HR specialist to manage recruitment and employee relations.',
                        'requirements' => 'Recruitment, Labor Law, Employee Relations, 2+ years experience',
                        'is_paid'      => true,
                    ],
                ],
            ],
            [
                'name'          => 'Horizon Creative Agency',
                'email'         => 'horizon@test.com',
                'governorate'   => 'Aleppo / حلب',
                'phone'         => '0112345678',
                'business_type' => 'Marketing / Advertising',
                'description'   => 'Full-service creative agency specializing in branding, design, and digital marketing.',
                'jobs'          => [
                    [
                        'title'        => 'Senior Graphic Designer',
                        'job_type'     => 'Full-time',
                        'location'     => 'Aleppo',
                        'salary'       => '$750/month',
                        'description'  => 'Creative designer to lead visual identity and branding projects.',
                        'requirements' => 'Photoshop, Illustrator, InDesign, Brand Identity, Portfolio required',
                        'is_paid'      => true,
                    ],
                    [
                        'title'        => 'Marketing Specialist',
                        'job_type'     => 'Full-time',
                        'location'     => 'Aleppo',
                        'salary'       => '$700/month',
                        'description'  => 'Digital marketing specialist to manage campaigns and social media.',
                        'requirements' => 'Social Media Marketing, SEO, Google Ads, Content Creation',
                        'is_paid'      => true,
                    ],
                ],
            ],
            [
                'name'          => 'Al-Bina Construction Group',
                'email'         => 'albina@test.com',
                'governorate'   => 'Homs / حمص',
                'phone'         => '0113456789',
                'business_type' => 'Construction',
                'description'   => 'Major construction company handling residential and commercial projects across Syria.',
                'jobs'          => [
                    [
                        'title'        => 'Civil Engineer',
                        'job_type'     => 'Full-time',
                        'location'     => 'Homs',
                        'salary'       => '$1000/month',
                        'description'  => 'Civil engineer to supervise construction sites and manage project timelines.',
                        'requirements' => 'AutoCAD, Structural Analysis, Site Supervision, Revit, 3+ years experience',
                        'is_paid'      => true,
                    ],
                    [
                        'title'        => 'Architect',
                        'job_type'     => 'Full-time',
                        'location'     => 'Damascus',
                        'salary'       => '$950/month',
                        'description'  => 'Architect to design residential and commercial buildings.',
                        'requirements' => 'AutoCAD, SketchUp, Revit, 3D Rendering, Interior Design',
                        'is_paid'      => true,
                    ],
                ],
            ],
            [
                'name'          => 'Syrian Medical Center',
                'email'         => 'smc@test.com',
                'governorate'   => 'Lattakia / اللاذقية',
                'phone'         => '0114567890',
                'business_type' => 'Healthcare',
                'description'   => 'Private medical center providing comprehensive healthcare services.',
                'jobs'          => [
                    [
                        'title'        => 'Registered Nurse',
                        'job_type'     => 'Full-time',
                        'location'     => 'Lattakia',
                        'salary'       => '$600/month',
                        'description'  => 'Experienced nurse for our general ward and emergency department.',
                        'requirements' => 'Patient Care, IV Therapy, Emergency Response, Medical Documentation',
                        'is_paid'      => true,
                    ],
                ],
            ],
            [
                'name'          => 'Future Academy',
                'email'         => 'futureacademy@test.com',
                'governorate'   => 'Damascus / دمشق',
                'phone'         => '0115678901',
                'business_type' => 'Education',
                'description'   => 'Private educational institution offering language courses and academic programs.',
                'jobs'          => [
                    [
                        'title'        => 'English Teacher',
                        'job_type'     => 'Part-time',
                        'location'     => 'Damascus',
                        'salary'       => '$500/month',
                        'description'  => 'English teacher for all levels from beginner to advanced.',
                        'requirements' => 'Curriculum Development, IELTS Preparation, Classroom Management, 3+ years experience',
                        'is_paid'      => true,
                    ],
                    [
                        'title'        => 'Sales Representative',
                        'job_type'     => 'Full-time',
                        'location'     => 'Damascus',
                        'salary'       => '$600/month',
                        'description'  => 'Sales rep to enroll new students and manage client relationships.',
                        'requirements' => 'B2B Sales, CRM Tools, Negotiation, Client Relations',
                        'is_paid'      => true,
                    ],
                ],
            ],
        ];

        foreach ($companies as $c) {
            $user = User::firstOrCreate(
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

            $company = Company::firstOrCreate(
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
                        'job_type'    => $j['job_type'],
                        'location'    => $j['location'],
                        'salary'      => $j['salary'],
                        'description' => $j['description'],
                        'requirements'=> $j['requirements'],
                        'is_paid'     => $j['is_paid'],
                    ]
                );
            }
        }

        $this->command->info('✅ Test data seeded successfully!');
        $this->command->info('');
        $this->command->info('📱 Job Seekers (password: password123):');
        foreach ($seekers as $s) {
            $this->command->info("   {$s['email']} — {$s['target_job']}");
        }
        $this->command->info('');
        $this->command->info('🏢 Companies (password: password123):');
        foreach ($companies as $c) {
            $this->command->info("   {$c['email']} — {$c['name']}");
        }
    }
}
