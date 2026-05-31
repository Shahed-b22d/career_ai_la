<?php

// Bootstrap Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Job;
use App\Models\UserResume;
use App\Models\JobCandidateScore;
use App\Http\Controllers\JobController;
use Illuminate\Http\Request;

// Bind request to container to avoid SessionGuard CLI issues
app()->instance('request', Request::create('/', 'GET'));

// Bind request to container to avoid SessionGuard CLI issues
app()->instance('request', Request::create('/', 'GET'));

echo "=== MATCH INTERCONNECTION BACKEND TESTS ===\n\n";

// 1. Find or create a company user
$companyUser = User::firstOrCreate(
    ['email' => 'test_company@example.com'],
    [
        'name' => 'Test Company LLC',
        'password' => bcrypt('password'),
        'role' => 'company',
        'governorate' => 'Damascus',
        'phone' => '0999999999'
    ]
);
echo "Using company user: {$companyUser->name} (ID: {$companyUser->id})\n";

// Authenticate as the company user
auth()->login($companyUser);

// 2. Find or create a job seeker user
$seekerUser = User::firstOrCreate(
    ['email' => 'test_seeker@example.com'],
    [
        'name' => 'Alice Seeker',
        'password' => bcrypt('password'),
        'role' => 'job',
        'governorate' => 'Damascus',
        'phone' => '0888888888'
    ]
);
echo "Using job seeker candidate: {$seekerUser->name} (ID: {$seekerUser->id})\n";

// 3. Create or update resume
$resume = UserResume::updateOrCreate(
    ['user_id' => $seekerUser->id],
    [
        'target_job' => 'Laravel Developer',
        'original_text' => 'Alice is a Senior PHP and Laravel Developer with 4 years of experience.',
        'current_skills' => ['PHP', 'Laravel', 'MySQL', 'Git'],
        'missing_skills' => ['Redis', 'Docker']
    ]
);
echo "Seeded candidate resume.\n";

// 4. Create two jobs for this company
$jobA = Job::updateOrCreate(
    ['user_id' => $companyUser->id, 'title' => 'Laravel Developer'],
    [
        'job_type' => 'Full-time',
        'location' => 'Damascus',
        'salary' => '$1,500',
        'description' => 'Looking for a backend developer.',
        'requirements' => 'Must know PHP, Laravel, MySQL, and Git.',
        'is_paid' => true
    ]
);

$jobB = Job::updateOrCreate(
    ['user_id' => $companyUser->id, 'title' => 'Flutter Developer'],
    [
        'job_type' => 'Full-time',
        'location' => 'Damascus',
        'salary' => '$1,600',
        'description' => 'Looking for an iOS/Android developer.',
        'requirements' => 'Must know Dart, Flutter, Firebase, and Git.',
        'is_paid' => true
    ]
);
echo "Seeded two company jobs: Job A (ID: {$jobA->id}), Job B (ID: {$jobB->id}).\n";

// 5. Seed scores
JobCandidateScore::updateOrCreate(
    ['job_id' => $jobA->id, 'candidate_user_id' => $seekerUser->id],
    ['match_score' => 90, 'justification' => 'Strong backend matching.']
);
JobCandidateScore::updateOrCreate(
    ['job_id' => $jobB->id, 'candidate_user_id' => $seekerUser->id],
    ['match_score' => 30, 'justification' => 'No mobile experience.']
);
echo "Seeded match scores: 90% for Laravel job, 30% for Flutter job.\n";

// Load the company's active jobs
$jobs = Job::where('user_id', $companyUser->id)->get();

// 1. Test Company Dashboard Endpoint Logic
echo "\n--- Testing Company Dashboard Endpoint ---\n";
$controller = app(JobController::class);
$dashboardResponse = $controller->getCompanyDashboardData();
$dashboardData = json_decode($dashboardResponse->getContent(), true);

if (isset($dashboardData['success']) && $dashboardData['success'] === true) {
    echo "SUCCESS: getCompanyDashboardData returned successfully.\n";
    $topCandidates = $dashboardData['data']['top_candidates'] ?? [];
    echo "Top Candidates count: " . count($topCandidates) . "\n";
    foreach ($topCandidates as $c) {
        echo "Candidate: {$c['name']} (ID: {$c['user_id']})\n";
        echo " - Desired Role: {$c['role']}\n";
        echo " - Best Match: {$c['match']}\n";
        echo " - Matched Job ID: " . ($c['matched_job_id'] ?? 'null') . "\n";
        echo " - Matched Job Title: " . ($c['matched_job_title'] ?? 'null') . "\n";
    }
} else {
    echo "FAILED: Company dashboard endpoint error.\n";
    print_r($dashboardData);
}

// 2. Test getCandidateProfile Endpoint Logic
echo "\n--- Testing Candidate Profile Context-Aware Scoring ---\n";
$firstCandidate = $resumes->first()->user;
echo "Selected Candidate: {$firstCandidate->name} (ID: {$firstCandidate->id})\n";

// A. Test without passing job_id (Should default to best match)
echo "\nCase A: Request profile without job_id (Default/Best Match)\n";
$request = Request::create('/api/candidates/' . $firstCandidate->id, 'GET');
$profileResponse = $controller->getCandidateProfile($request, $firstCandidate->id);
$profileData = json_decode($profileResponse->getContent(), true);

if (isset($profileData['success']) && $profileData['success'] === true) {
    $cProfile = $profileData['data'];
    echo "SUCCESS: Default profile loaded.\n";
    echo " - Match Score: {$cProfile['match']}\n";
    echo " - Matched Job Title: " . ($cProfile['matched_job_title'] ?? 'N/A') . "\n";
} else {
    echo "FAILED: Request without job_id failed.\n";
    print_r($profileData);
}

// B. Test with specific job_ids (if jobs exist)
if ($jobs->isNotEmpty()) {
    foreach ($jobs as $job) {
        echo "\nCase B: Request profile with specific Job ID {$job->id} (\"{$job->title}\")\n";
        $request = Request::create('/api/candidates/' . $firstCandidate->id, 'GET', ['job_id' => $job->id]);
        $profileResponse = $controller->getCandidateProfile($request, $firstCandidate->id);
        $profileData = json_decode($profileResponse->getContent(), true);
        
        if (isset($profileData['success']) && $profileData['success'] === true) {
            $cProfile = $profileData['data'];
            echo "SUCCESS: Context-aware profile loaded for Job {$job->id}.\n";
            echo " - Match Score: {$cProfile['match']}\n";
            echo " - Matched Job Title: " . ($cProfile['matched_job_title'] ?? 'N/A') . "\n";
        } else {
            echo "FAILED: Request with job_id failed.\n";
            print_r($profileData);
        }
    }
} else {
    echo "Skipping Case B: No company jobs found to test context-specific queries.\n";
}

echo "\n=== BACKEND TESTING COMPLETE ===\n";
