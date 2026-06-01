<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\JobCandidateScore;
use App\Models\User;
use App\Models\UserResume;
use App\Services\AiCareerService;
use App\Services\CandidateScoringService;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JobController extends Controller
{
    protected AiCareerService $aiService;
    protected CandidateScoringService $scoringService;

    public function __construct(AiCareerService $aiService, CandidateScoringService $scoringService)
    {
        $this->aiService      = $aiService;
        $this->scoringService = $scoringService;
    }
    /**
     * Create a job and return Stripe Checkout URL
     */
    public function postJob(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'job_type' => 'required|string',
            'location' => 'required|string',
            'salary' => 'required|string',
            'description' => 'required|string',
            'requirements' => 'required|string',
        ]);

        // Create the pending job
        $job = Job::create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'job_type' => $request->job_type,
            'location' => $request->location,
            'salary' => $request->salary,
            'description' => $request->description,
            'requirements' => $request->requirements,
            'is_paid' => false,
        ]);

        $stripeSecret = env('STRIPE_SECRET');

        // Success and cancel URLs
        $successUrl = url("/api/jobs/payment-success?job_id={$job->id}");
        $cancelUrl = url("/api/jobs/payment-cancel?job_id={$job->id}");

        if ($stripeSecret) {
            // Real Stripe Checkout Session Creation via HTTP API
            try {
                $response = Http::asForm()
                    ->withBasicAuth($stripeSecret, '')
                    ->post('https://api.stripe.com/v1/checkout/sessions', [
                        'payment_method_types' => ['card'],
                        'line_items' => [
                            [
                                'price_data' => [
                                    'currency' => 'usd',
                                    'product_data' => [
                                        'name' => "Job Listing: {$job->title}",
                                    ],
                                    'unit_amount' => 2500, // $25.00
                                ],
                                'quantity' => 1,
                            ],
                        ],
                        'mode' => 'payment',
                        'success_url' => $successUrl . "&session_id={CHECKOUT_SESSION_ID}",
                        'cancel_url' => $cancelUrl,
                        'client_reference_id' => $job->id,
                    ]);

                if ($response->successful()) {
                    $session = $response->json();
                    $job->update([
                        'payment_session_id' => $session['id'],
                    ]);

                    return response()->json([
                        'success' => true,
                        'checkout_url' => $session['url'],
                    ]);
                } else {
                    Log::error("Stripe Checkout Error: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("Stripe Checkout Exception: " . $e->getMessage());
            }
        }

        // Fallback or Simulated Checkout Page URL
        $mockCheckoutUrl = url("/jobs/checkout/{$job->id}");
        return response()->json([
            'success' => true,
            'checkout_url' => $mockCheckoutUrl,
        ]);
    }

    /**
     * Get all paid jobs
     */
    public function getActiveJobs()
    {
        $jobs = Job::where('is_paid', true)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jobs
        ]);
    }

    /**
     * Handle payment success
     */
    public function paymentSuccess(Request $request)
    {
        $jobId = $request->query('job_id');
        $job = Job::with('user')->find($jobId);

        if ($job) {
            $job->update(['is_paid' => true]);

            // ── حساب نسبة التوافق مع كل المرشحين وحفظها في DB ────────────────
            try {
                $this->scoringService->scoreAllCandidatesForJob($job);
            } catch (\Exception $e) {
                Log::error("Scoring after job activation failed: " . $e->getMessage());
            }

            // 🔔 Notify the company that their job post is now live
            if ($job->user && $job->user->fcm_token) {
                (new FcmService())->send(
                    $job->user->fcm_token,
                    '🎉 Job Post Approved!',
                    "Your job post \"{$job->title}\" is now live and visible to candidates.",
                    ['type' => 'job_approved', 'job_id' => (string) $job->id]
                );
            }
        }

        return response()->make("
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Payment Successful</title>
                <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap' rel='stylesheet'>
                <style>
                    body {
                        font-family: 'Outfit', sans-serif;
                        background-color: #F8F9FA;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                    }
                    .card {
                        background: white;
                        padding: 40px;
                        border-radius: 24px;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
                        text-align: center;
                        max-width: 400px;
                        width: 90%;
                    }
                    .icon {
                        font-size: 60px;
                        color: #2ECC71;
                        margin-bottom: 20px;
                    }
                    h1 {
                        color: #1A1A1A;
                        margin-bottom: 10px;
                        font-size: 24px;
                    }
                    p {
                        color: #666;
                        font-size: 16px;
                        line-height: 1.5;
                        margin-bottom: 30px;
                    }
                    .btn {
                        background-color: #0052FF;
                        color: white;
                        border: none;
                        padding: 14px 28px;
                        border-radius: 12px;
                        font-size: 16px;
                        font-weight: 600;
                        text-decoration: none;
                        display: inline-block;
                        cursor: pointer;
                        transition: background-color 0.2s;
                    }
                    .btn:hover {
                        background-color: #0040D4;
                    }
                </style>
            </head>
            <body>
                <div class='card'>
                    <div class='icon'>🎉</div>
                    <h1>Payment Successful!</h1>
                    <p>Your job post has been published successfully. You can close this window now and return to the app.</p>
                    <a href='#' onclick='window.close();' class='btn'>Close Window</a>
                </div>
            </body>
            </html>
        ", 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Handle payment cancellation
     */
    public function paymentCancel(Request $request)
    {
        return response()->make("
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>Payment Cancelled</title>
                <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap' rel='stylesheet'>
                <style>
                    body {
                        font-family: 'Outfit', sans-serif;
                        background-color: #F8F9FA;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        height: 100vh;
                        margin: 0;
                    }
                    .card {
                        background: white;
                        padding: 40px;
                        border-radius: 24px;
                        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
                        text-align: center;
                        max-width: 400px;
                        width: 90%;
                    }
                    .icon {
                        font-size: 60px;
                        color: #E74C3C;
                        margin-bottom: 20px;
                    }
                    h1 {
                        color: #1A1A1A;
                        margin-bottom: 10px;
                        font-size: 24px;
                    }
                    p {
                        color: #666;
                        font-size: 16px;
                        line-height: 1.5;
                        margin-bottom: 30px;
                    }
                    .btn {
                        background-color: #666;
                        color: white;
                        border: none;
                        padding: 14px 28px;
                        border-radius: 12px;
                        font-size: 16px;
                        font-weight: 600;
                        text-decoration: none;
                        display: inline-block;
                        cursor: pointer;
                    }
                </style>
            </head>
            <body>
                <div class='card'>
                    <div class='icon'>❌</div>
                    <h1>Payment Cancelled</h1>
                    <p>The payment process was cancelled. You can close this window now and try again from the app.</p>
                    <a href='#' onclick='window.close();' class='btn'>Close Window</a>
                </div>
            </body>
            </html>
        ", 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Render Simulated Stripe Checkout Page
     */
    public function showMockCheckout($job_id)
    {
        $job = Job::findOrFail($job_id);
        $successUrl = url("/api/jobs/payment-success?job_id={$job->id}");
        $cancelUrl = url("/api/jobs/payment-cancel?job_id={$job->id}");

        return view('mock_checkout', [
            'job' => $job,
            'successUrl' => $successUrl,
            'cancelUrl' => $cancelUrl
        ]);
    }

    /**
     * Build candidate payload — يقرأ النسبة من DB (محسوبة مسبقاً بالـ AI).
     * إذا لم تُحسب بعد يستخدم الـ local fallback.
     */
    /**
     * Build candidate payload — يقرأ النسبة من DB (محسوبة مسبقاً بالـ AI).
     * إذا لم تُحسب بعد يستخدم الـ local fallback.
     */
    private function buildCandidatePayload(UserResume $resume, Collection $companyJobs): ?array
    {
        if (!$resume->user || $resume->user->role !== 'job') {
            return null;
        }

        $bestDetails = $this->getBestStoredScoreDetails($resume->user_id, $companyJobs);

        return [
            'user_id'           => $resume->user->id,
            'name'              => $resume->user->name,
            'role'              => $resume->target_job ?? 'Job Seeker',
            'match'             => "{$bestDetails['score']}%",
            'matched_job_id'    => $bestDetails['job_id'],
            'matched_job_title' => $bestDetails['job_title'],
            'email'             => $resume->user->email,
            'phone'             => $resume->user->phone ?? 'N/A',
            'governorate'       => $resume->user->governorate ?? 'N/A',
            'skills'            => $resume->current_skills ?? [],
            'missing_skills'    => $resume->missing_skills ?? [],
            'target_job'        => $resume->target_job,
            'has_cv'            => !empty($resume->original_text),
        ];
    }

    /**
     * يجلب أعلى نسبة مخزونة في DB لهذا المرشح مقابل وظائف الشركة وتفاصيل الوظيفة.
     * إذا لم توجد نسبة محسوبة بعد → يحسب محلياً ويحفظ.
     */
    private function getBestStoredScoreDetails(int $candidateUserId, Collection $companyJobs): array
    {
        if ($companyJobs->isEmpty()) {
            // لا توجد وظائف → نقيّم جودة الـ profile
            $resume = UserResume::where('user_id', $candidateUserId)->latest()->first();
            $score = $resume ? $this->scoreWithoutJob($resume) : 0;
            return [
                'score' => $score,
                'job_id' => null,
                'job_title' => null,
            ];
        }

        $jobIds = $companyJobs->pluck('id');

        // جلب أعلى نسبة مخزونة
        $bestScoreRecord = JobCandidateScore::where('candidate_user_id', $candidateUserId)
            ->whereIn('job_id', $jobIds)
            ->orderByDesc('match_score')
            ->first();

        if ($bestScoreRecord !== null) {
            $job = $companyJobs->firstWhere('id', $bestScoreRecord->job_id);
            return [
                'score' => (int) $bestScoreRecord->match_score,
                'job_id' => $bestScoreRecord->job_id,
                'job_title' => $job ? $job->title : null,
            ];
        }

        // لم تُحسب بعد → نحسب محلياً كـ fallback مؤقت ونختار الأفضل
        $resume = UserResume::where('user_id', $candidateUserId)->latest()->first();
        if (!$resume) {
            return [
                'score' => 0,
                'job_id' => null,
                'job_title' => null,
            ];
        }

        $bestLocal = 0;
        $bestJob = null;
        foreach ($companyJobs as $job) {
            $score = $this->computeScoreForJob($resume, $job);
            if ($score > $bestLocal) {
                $bestLocal = $score;
                $bestJob = $job;
            }
        }

        if ($bestJob === null && $companyJobs->isNotEmpty()) {
            $bestJob = $companyJobs->first();
        }

        return [
            'score' => $bestLocal,
            'job_id' => $bestJob ? $bestJob->id : null,
            'job_title' => $bestJob ? $bestJob->title : null,
        ];
    }

    /**
     * حساب النسبة عندما لا توجد وظائف نشطة للشركة.
     */
    private function scoreWithoutJob(UserResume $resume): int
    {
        $score = 0;
        if (!empty($resume->original_text))      { $score += 30; }
        if (!empty($resume->target_job))          { $score += 20; }
        $score += min(count($resume->current_skills ?? []) * 5, 50);
        return min($score, 100);
    }

    /**
     * حساب محلي احتياطي لوظيفة واحدة (يُستخدم فقط إذا لم تُحسب النسبة بعد).
     */
    private function computeScoreForJob(UserResume $resume, Job $job): int
    {
        $jobText         = strtolower($job->requirements . ' ' . $job->description . ' ' . $job->title);
        $candidateSkills = array_map('strtolower', $resume->current_skills ?? []);
        $totalSkills     = max(count($candidateSkills), 1);

        $matched = 0;
        foreach ($candidateSkills as $skill) {
            if (str_contains($jobText, $skill)) {
                $matched++;
            } else {
                foreach (explode(' ', $skill) as $part) {
                    if (strlen($part) > 2 && str_contains($jobText, $part)) {
                        $matched += 0.5;
                        break;
                    }
                }
            }
        }
        $skillScore = (int) round(($matched / $totalSkills) * 50);

        $candidateRole = strtolower(trim($resume->target_job ?? ''));
        $jobTitle      = strtolower(trim($job->title));
        $titleScore    = 0;
        if ($candidateRole !== '' && $jobTitle !== '') {
            if ($candidateRole === $jobTitle) {
                $titleScore = 25;
            } elseif (str_contains($jobTitle, $candidateRole) || str_contains($candidateRole, $jobTitle)) {
                $titleScore = 18;
            } else {
                $common = array_intersect(explode(' ', $candidateRole), explode(' ', $jobTitle));
                if ($common) {
                    $titleScore = (int) round(
                        (count($common) / max(count(explode(' ', $candidateRole)), count(explode(' ', $jobTitle)))) * 15
                    );
                }
            }
        }

        $profileScore = 0;
        if (!empty($resume->original_text)) {
            $profileScore += 15;
            if (strlen($resume->original_text) > 500) { $profileScore += 5; }
        }
        if (count($candidateSkills) >= 5) { $profileScore += 5; }

        return min(max($skillScore + $titleScore + $profileScore, 0), 100);
    }

    /**
     * GET /api/jobs/{jobId}/candidates
     * يرجع المرشحين المتوافقين مع وظيفة محددة مرتبين تنازلياً حسب النسبة.
     * النسب مأخوذة من job_candidate_scores (محسوبة بالـ AI).
     */
    public function getJobCandidates(Request $request, int $jobId)
    {
        $companyUser = auth()->user();

        // التحقق أن الوظيفة تخص هذه الشركة
        $job = Job::where('id', $jobId)
            ->where('user_id', $companyUser->id)
            ->first();

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found or does not belong to your account.',
            ], 404);
        }

        // جلب كل النسب المحسوبة لهذه الوظيفة مرتبة تنازلياً
        $scores = JobCandidateScore::where('job_id', $jobId)
            ->orderByDesc('match_score')
            ->with(['candidate', 'candidate.jobSeeker'])
            ->get();

        $candidates = [];

        foreach ($scores as $score) {
            $candidate = $score->candidate;
            if (!$candidate || $candidate->role !== 'job') {
                continue;
            }

            $resume = UserResume::where('user_id', $candidate->id)->latest()->first();

            $phone = $candidate->phone
                ?? optional($candidate->jobSeeker)->phone
                ?? 'N/A';

            $candidates[] = [
                'user_id'        => $candidate->id,
                'name'           => $candidate->name,
                'email'          => $candidate->email,
                'phone'          => $phone,
                'governorate'    => $candidate->governorate ?? 'N/A',
                'role'           => $resume?->target_job ?? 'Job Seeker',
                'target_job'     => $resume?->target_job,
                'match'          => "{$score->match_score}%",
                'match_score'    => $score->match_score,
                'justification'  => $score->justification,
                'skills'         => $resume?->current_skills ?? [],
                'missing_skills' => $resume?->missing_skills ?? [],
                'has_cv'         => $resume && !empty($resume->original_text),
            ];
        }

        // إذا لم تُحسب النسب بعد (الوظيفة جديدة) → نرجع fallback محلي
        if ($candidates === [] ) {
            foreach ($this->getJobSeekerResumes() as $resume) {
                if (!$resume->user) continue;
                $score = $this->computeScoreForJob($resume, $job);
                $candidates[] = [
                    'user_id'        => $resume->user->id,
                    'name'           => $resume->user->name,
                    'email'          => $resume->user->email,
                    'phone'          => $resume->user->phone ?? 'N/A',
                    'governorate'    => $resume->user->governorate ?? 'N/A',
                    'role'           => $resume->target_job ?? 'Job Seeker',
                    'target_job'     => $resume->target_job,
                    'match'          => "{$score}%",
                    'match_score'    => $score,
                    'justification'  => 'Calculated locally',
                    'skills'         => $resume->current_skills ?? [],
                    'missing_skills' => $resume->missing_skills ?? [],
                    'has_cv'         => !empty($resume->original_text),
                ];
            }
            usort($candidates, fn($a, $b) => $b['match_score'] <=> $a['match_score']);
        }

        return response()->json([
            'success'    => true,
            'job_id'     => $job->id,
            'job_title'  => $job->title,
            'job_description' => $job->description,
            'job_requirements' => $job->requirements,
            'job_location' => $job->location,
            'job_salary'  => $job->salary,
            'job_type'    => $job->job_type,
            'is_paid'     => $job->is_paid,
            'data'        => $candidates,
        ]);
    }

    /**
     * Get job seeker resumes for candidate listings.
     */
    private function getJobSeekerResumes()
    {
        return UserResume::with('user')
            ->whereHas('user', function ($q) {
                $q->where('role', 'job');
            })
            ->latest()
            ->get()
            ->unique('user_id')
            ->values();
    }

    /**
     * Get Company Dashboard details dynamically
     */
    public function getCompanyDashboardData()
    {
        $user = auth()->user();
        
        // Active Jobs count for this company
        $activeJobsCount = Job::where('user_id', $user->id)
            ->where('is_paid', true)
            ->count();
            
        $stripeSpend = $activeJobsCount * 25;
        
        // Total suggested candidates (job seekers with CV or manual profile data)
        $suggestedCandidatesCount = UserResume::whereHas('user', function ($q) {
            $q->where('role', 'job');
        })->distinct('user_id')->count('user_id');
        
        // Load the company's active jobs to perform AI matching
        $companyJobs = Job::where('user_id', $user->id)
            ->where('is_paid', true)
            ->get();

        $topCandidates = [];

        foreach ($this->getJobSeekerResumes() as $resume) {
            $payload = $this->buildCandidatePayload($resume, $companyJobs);
            if ($payload) {
                $topCandidates[] = $payload;
            }

        }

        usort($topCandidates, fn ($a, $b) => intval($b['match']) <=> intval($a['match']));

        // Take top 5 candidates
        $topCandidates = array_slice($topCandidates, 0, 5);
        
        // Recent jobs posted by this company (limit to 3)
        $recentJobsRaw = Job::where('user_id', $user->id)
            ->latest()
            ->take(3)
            ->get();
            
        $recentJobs = [];
        foreach ($recentJobsRaw as $job) {
            // عدد المرشحين الذين نسبتهم مع هذه الوظيفة ≥ 50% (محسوبة بالـ AI)
            $matchCount = JobCandidateScore::where('job_id', $job->id)
                ->where('match_score', '>=', 50)
                ->count();

            $recentJobs[] = [
                'id'            => $job->id,
                'title'         => $job->title,
                'created_at'    => $job->created_at->toIso8601String(),
                'is_paid'       => $job->is_paid,
                'salary'        => $job->salary,
                'location'      => $job->location,
                'job_type'      => $job->job_type,
                'matches_count' => "{$matchCount} AI Matches",
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'company_name' => $user->name,
                'active_jobs_count' => $activeJobsCount,
                'stripe_spend' => "\${$stripeSpend}",
                'suggested_candidates_count' => $suggestedCandidatesCount,
                'top_candidates' => $topCandidates,
                'recent_jobs' => $recentJobs,
            ]
        ]);
    }

    /**
     * Get a single candidate profile by user ID
     * Optionally accepts job_id to calculate match score against a specific job
     */
    public function getCandidateProfile(Request $request, int $userId)
    {
        $jobId = $request->query('job_id');

        $resume = \App\Models\UserResume::with('user')
            ->where('user_id', $userId)
            ->first();

        if (!$resume || !$resume->user) {
            return response()->json(['success' => false, 'message' => 'Candidate not found'], 404);
        }

        $user = $resume->user;

        // Calculate match score
        $matchScore = 70;
        $matchedJobTitle = null;

        if ($jobId) {
            $job = \App\Models\Job::find($jobId);
            if ($job) {
                $matchedJobTitle = $job->title;
                if (
                    stripos($job->title, $resume->target_job) !== false ||
                    stripos($resume->target_job, $job->title) !== false
                ) {
                    $matchScore = 95;
                } else {
                    $overlap = array_intersect(
                        array_map('strtolower', $resume->current_skills ?? []),
                        array_map('strtolower', explode(' ', $job->requirements . ' ' . $job->description))
                    );
                    if (count($overlap) > 0) {
                        $matchScore = min(70 + count($overlap) * 5, 94);
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id'           => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'phone'             => $user->phone ?? 'N/A',
                'governorate'       => $user->governorate ?? 'N/A',
                'role'              => $resume->target_job ?? 'Job Seeker',
                'target_job'        => $resume->target_job,
                'match'             => "{$matchScore}%",
                'matched_job_title' => $matchedJobTitle,
                'skills'            => $resume->current_skills ?? [],
                'missing_skills'    => $resume->missing_skills ?? [],
            ],
        ]);
    }

    /**
     * Get all AI-matched candidates for a specific job posting
     */
    public function getJobCandidates(Request $request, int $jobId)
    {
        $job = \App\Models\Job::find($jobId);

        if (!$job) {
            return response()->json(['success' => false, 'message' => 'Job not found'], 404);
        }

        $resumes = \App\Models\UserResume::with('user')
            ->whereHas('user', function ($q) {
                $q->where('role', 'job');
            })
            ->latest()
            ->get();

        $candidates = [];

        foreach ($resumes as $resume) {
            if (!$resume->user) continue;

            $matchScore = 70;

            if (
                stripos($job->title, $resume->target_job) !== false ||
                stripos($resume->target_job, $job->title) !== false
            ) {
                $matchScore = 95;
            } else {
                $overlap = array_intersect(
                    array_map('strtolower', $resume->current_skills ?? []),
                    array_map('strtolower', explode(' ', $job->requirements . ' ' . $job->description))
                );
                if (count($overlap) > 0) {
                    $matchScore = min(70 + count($overlap) * 5, 94);
                }
            }

            $candidates[] = [
                'user_id'           => $resume->user->id,
                'name'              => $resume->user->name,
                'email'             => $resume->user->email,
                'phone'             => $resume->user->phone ?? 'N/A',
                'governorate'       => $resume->user->governorate ?? 'N/A',
                'role'              => $resume->target_job ?? 'Job Seeker',
                'match'             => "{$matchScore}%",
                'matched_job_title' => $job->title,
                'matched_job_id'    => $job->id,
                'skills'            => $resume->current_skills ?? [],
                'missing_skills'    => $resume->missing_skills ?? [],
            ];
        }

        // Sort by match score descending
        usort($candidates, fn($a, $b) => intval($b['match']) <=> intval($a['match']));

        return response()->json([
            'success' => true,
            'data'    => $candidates,
            'job'     => [
                'id'    => $job->id,
                'title' => $job->title,
            ],
        ]);
    }

    /**
     * Shortlist a candidate — saves to DB and sends a real FCM notification
     */
    public function shortlistCandidate(Request $request, int $userId)
    {
        $company = auth()->user();

        // جيب بيانات المرشح
        $resume = \App\Models\UserResume::with('user')
            ->where('user_id', $userId)
            ->first();

        if (!$resume || !$resume->user) {
            return response()->json(['success' => false, 'message' => 'Candidate not found'], 404);
        }

        $candidate = $resume->user;

        // احسب الـ match score إذا أُرسل job_id
        $matchScore = '70%';
        $jobId = $request->input('job_id');
        if ($jobId) {
            $job = \App\Models\Job::find($jobId);
            if ($job) {
                $score = 70;
                if (
                    stripos($job->title, $resume->target_job) !== false ||
                    stripos($resume->target_job, $job->title) !== false
                ) {
                    $score = 95;
                } else {
                    $overlap = array_intersect(
                        array_map('strtolower', $resume->current_skills ?? []),
                        array_map('strtolower', explode(' ', $job->requirements . ' ' . $job->description))
                    );
                    if (count($overlap) > 0) {
                        $score = min(70 + count($overlap) * 5, 94);
                    }
                }
                $matchScore = "{$score}%";
            }
        }

        // احفظ في جدول shortlists (أو تجاهل إذا موجود مسبقاً)
        \App\Models\Shortlist::firstOrCreate(
            [
                'company_user_id' => $company->id,
                'candidate_email' => $candidate->email,
            ],
            [
                'candidate_name'        => $candidate->name,
                'candidate_phone'       => $candidate->phone ?? 'N/A',
                'candidate_governorate' => $candidate->governorate ?? 'N/A',
                'candidate_role'        => $resume->target_job ?? 'Job Seeker',
                'match_score'           => $matchScore,
            ]
        );

        // 🔔 ابعت إشعار حقيقي للمرشح فقط هون
        if ($candidate->fcm_token) {
            (new FcmService())->send(
                $candidate->fcm_token,
                '🌟 You\'ve Been Shortlisted!',
                "{$company->name} has shortlisted you for a position. Keep your CV updated to stand out!",
                ['type' => 'shortlisted', 'company' => $company->name]
            );
        }

        Log::info("Candidate {$candidate->name} shortlisted by {$company->name}");

        return response()->json([
            'success' => true,
            'message' => "Candidate {$candidate->name} has been shortlisted successfully.",
        ]);
    }


    public function getSuggestedCandidates(Request $request)
    {
        $user = auth()->user();

        $companyJobs = Job::where('user_id', $user->id)
            ->where('is_paid', true)
            ->get();

        $candidates = [];

        foreach ($this->getJobSeekerResumes() as $resume) {
            $payload = $this->buildCandidatePayload($resume, $companyJobs);
            if ($payload) {
                $candidates[] = $payload;
            }
        }

        usort($candidates, fn ($a, $b) => intval($b['match']) <=> intval($a['match']));

        return response()->json([
            'success' => true,
            'data'    => $candidates,
        ]);
    }

    /**
     * Get full profile for a single job seeker candidate (company users only).
     */
    public function getCandidateProfile(Request $request, int $userId)
    {
        $companyUser = auth()->user();

        if ($companyUser->role !== 'company') {
            return response()->json([
                'success' => false,
                'message' => 'Only company accounts can view candidate profiles.',
            ], 403);
        }

        $candidate = User::where('id', $userId)
            ->where('role', 'job')
            ->with('jobSeeker')
            ->first();

        if (!$candidate) {
            return response()->json([
                'success' => false,
                'message' => 'Candidate not found.',
            ], 404);
        }

        $resume = UserResume::where('user_id', $candidate->id)->latest()->first();

        $companyJobs = Job::where('user_id', $companyUser->id)
            ->where('is_paid', true)
            ->get();

        $phone = $candidate->phone
            ?? optional($candidate->jobSeeker)->phone
            ?? 'N/A';

        $matchScore = 0;
        $matchedJobTitle = null;

        $jobId = $request->query('job_id');

        if ($resume) {
            if ($jobId) {
                // إذا تم تمرير وظيفة معينة، نجلب النسبة الخاصة بها فقط
                $job = Job::where('id', $jobId)
                    ->where('user_id', $companyUser->id)
                    ->first();
                if ($job) {
                    $matchedJobTitle = $job->title;
                    $stored = JobCandidateScore::where('candidate_user_id', $candidate->id)
                        ->where('job_id', $jobId)
                        ->first();
                    if ($stored !== null) {
                        $matchScore = (int) $stored->match_score;
                    } else {
                        $matchScore = $this->computeScoreForJob($resume, $job);
                    }
                } else {
                    // إذا لم نجد الوظيفة للشركة، نقع على الافتراضي
                    $bestDetails = $this->getBestStoredScoreDetails($candidate->id, $companyJobs);
                    $matchScore = $bestDetails['score'];
                    $matchedJobTitle = $bestDetails['job_title'];
                }
            } else {
                // الافتراضي: نجلب النسبة لأفضل وظيفة مطابقة
                $bestDetails = $this->getBestStoredScoreDetails($candidate->id, $companyJobs);
                $matchScore = $bestDetails['score'];
                $matchedJobTitle = $bestDetails['job_title'];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id'           => $candidate->id,
                'name'              => $candidate->name,
                'email'             => $candidate->email,
                'phone'             => $phone,
                'governorate'       => $candidate->governorate ?? 'N/A',
                'role'              => $resume?->target_job ?? 'Job Seeker',
                'target_job'        => $resume?->target_job,
                'match'             => "{$matchScore}%",
                'matched_job_title' => $matchedJobTitle,
                'skills'            => $resume?->current_skills ?? [],
                'missing_skills'    => $resume?->missing_skills ?? [],
                'has_cv'            => $resume && !empty($resume->original_text),
                'profile_source'    => ($resume && !empty($resume->original_text)) ? 'cv_upload' : 'manual_entry',
            ],
        ]);
    }
}
