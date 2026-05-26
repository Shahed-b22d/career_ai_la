<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Complaint;
use App\Models\Job;
use App\Models\User;
use App\Models\UserQuiz;
use App\Models\UserResume;
use App\Models\UserRoadmap;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    /**
     * POST /api/admin/login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|string',
            'password' => 'required|string',
        ]);

        $email = $request->email;
        if ($email === 'admin') {
            $email = env('ADMIN_EMAIL', 'admin@career.ai');
        }

        $user = User::where('email', $email)->where('role', 'admin')->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid admin credentials',
            ], 401);
        }

        $user->tokens()->delete();
        $token = $user->createToken('admin_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role,
            ],
        ]);
    }

    /**
     * GET /api/admin/dashboard
     */
    public function dashboard()
    {
        $paidJobsCount = Job::where('is_paid', true)->count();
        $revenue = $paidJobsCount * 25;
        $pendingComplaints = Complaint::where('status', 'pending')->count();
        $pendingVerifications = Company::where('verification_status', 'pending')
            ->whereNotNull('commercial_register_path')
            ->count();

        $companiesByMonth = Company::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month')
            ->toArray();

        $barChart = [];
        for ($m = 1; $m <= 7; $m++) {
            $barChart[] = (int) ($companiesByMonth[$m] ?? 0);
        }

        $jobSeekers = User::where('role', 'job')->count();
        $companies = User::where('role', 'company')->count();
        $totalUsers = max($jobSeekers + $companies, 1);

        $complaints = Complaint::with('user:id,name,email')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($c) => [
                'id'      => $c->id,
                'user'    => $c->user?->name ?? 'Unknown',
                'subject' => $c->subject,
                'message' => $c->message,
                'status'  => $c->status,
                'role'    => $c->role,
                'created_at' => $c->created_at->diffForHumans(),
            ]);

        $notifications = $this->buildNotifications();

        return response()->json([
            'success' => true,
            'data'    => [
                'stats' => [
                    'revenue'           => $revenue,
                    'revenue_formatted' => '$' . number_format($revenue),
                    'active_jobs'       => $paidJobsCount,
                    'tickets'           => $pendingComplaints,
                    'pending_verifications' => $pendingVerifications,
                ],
                'charts' => [
                    'companies_growth' => $barChart,
                    'talents_pie'      => [
                        'job_seekers' => $jobSeekers,
                        'companies'   => $companies,
                        'job_seekers_pct' => round(($jobSeekers / $totalUsers) * 100),
                        'companies_pct'   => round(($companies / $totalUsers) * 100),
                    ],
                ],
                'complaints'    => $complaints,
                'notifications' => $notifications,
            ],
        ]);
    }

    /**
     * GET /api/admin/verifications/companies
     */
    public function pendingCompanies()
    {
        $companies = Company::with('user:id,name,email,business_type')
            ->where('verification_status', 'pending')
            ->latest()
            ->get()
            ->map(fn ($c) => [
                'id'           => $c->id,
                'company_name' => $c->user?->name ?? 'N/A',
                'email'        => $c->user?->email,
                'business_type'=> $c->business_type ?? $c->user?->business_type,
                'license_url'  => $c->commercial_register_path
                    ? url(Storage::url($c->commercial_register_path))
                    : null,
                'status'       => $c->verification_status,
                'created_at'   => $c->created_at->format('Y-m-d'),
            ]);

        return response()->json(['success' => true, 'data' => $companies]);
    }

    /**
     * POST /api/admin/verifications/companies/{company}/approve
     */
    public function approveCompany(Company $company)
    {
        $company->update(['verification_status' => 'approved']);

        // 🔔 Notify the company owner
        $owner = $company->user;
        if ($owner && $owner->fcm_token) {
            (new FcmService())->send(
                $owner->fcm_token,
                '✅ Company Verified!',
                'Congratulations! Your company has been verified. You can now post jobs and access all features.',
                ['type' => 'company_approved']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Company verified successfully',
        ]);
    }

    /**
     * POST /api/admin/verifications/companies/{company}/reject
     */
    public function rejectCompany(Company $company)
    {
        $company->update(['verification_status' => 'rejected']);

        // 🔔 Notify the company owner
        $owner = $company->user;
        if ($owner && $owner->fcm_token) {
            (new FcmService())->send(
                $owner->fcm_token,
                '❌ Verification Rejected',
                'Your company verification was not approved. Please contact support for more details.',
                ['type' => 'company_rejected']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Company verification rejected',
        ]);
    }

    /**
     * GET /api/admin/payments/jobs
     */
    public function pendingPayments()
    {
        $jobs = Job::with('user:id,name,email')
            ->where('is_paid', false)
            ->latest()
            ->get()
            ->map(fn ($j) => [
                'id'                 => $j->id,
                'title'              => $j->title,
                'company_name'       => $j->user?->name ?? 'N/A',
                'amount'             => '$25',
                'payment_session_id' => $j->payment_session_id ?? 'N/A',
                'created_at'         => $j->created_at->format('Y-m-d'),
            ]);

        return response()->json(['success' => true, 'data' => $jobs]);
    }

    /**
     * POST /api/admin/payments/jobs/{job}/confirm
     */
    public function confirmPayment(Job $job)
    {
        $job->update(['is_paid' => true]);

        // 🔔 Notify the company that their job post is now live
        $job->load('user');
        if ($job->user && $job->user->fcm_token) {
            (new FcmService())->send(
                $job->user->fcm_token,
                '🎉 Job Post is Now Live!',
                "Your job post \"{$job->title}\" has been approved by admin and is now visible to candidates.",
                ['type' => 'job_approved', 'job_id' => (string) $job->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment confirmed. Job is now active.',
        ]);
    }

    /**
     * GET /api/admin/complaints?status=pending|in_progress|resolved|all
     */
    public function complaints(Request $request)
    {
        $query = Complaint::with('user:id,name,email,role');

        $status = $request->query('status', 'all');
        if ($status !== 'all' && in_array($status, ['pending', 'in_progress', 'resolved'])) {
            $query->where('status', $status);
        }

        $complaints = $query->latest()->get()->map(fn ($c) => [
            'id'             => $c->id,
            'user_id'        => $c->user_id,
            'user_name'      => $c->user?->name ?? 'Unknown',
            'user_email'     => $c->user?->email,
            'user_role'      => $c->role,
            'subject'        => $c->subject,
            'message'        => $c->message,
            'status'         => $c->status,
            'admin_response' => $c->admin_response,
            'resolved_at'    => $c->resolved_at?->diffForHumans(),
            'created_at'     => $c->created_at->format('Y-m-d H:i'),
            'created_human'  => $c->created_at->diffForHumans(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $complaints,
            'counts'  => [
                'pending'     => Complaint::where('status', 'pending')->count(),
                'in_progress' => Complaint::where('status', 'in_progress')->count(),
                'resolved'    => Complaint::where('status', 'resolved')->count(),
                'total'       => Complaint::count(),
            ],
        ]);
    }

    /**
     * GET /api/admin/complaints/{complaint}
     */
    public function showComplaint(Complaint $complaint)
    {
        $complaint->load('user:id,name,email,phone,role');

        return response()->json([
            'success' => true,
            'data'    => [
                'id'             => $complaint->id,
                'user_name'      => $complaint->user?->name,
                'user_email'     => $complaint->user?->email,
                'user_role'      => $complaint->role,
                'subject'        => $complaint->subject,
                'message'        => $complaint->message,
                'status'         => $complaint->status,
                'admin_response' => $complaint->admin_response,
                'resolved_at'    => $complaint->resolved_at,
                'created_at'     => $complaint->created_at->format('Y-m-d H:i'),
            ],
        ]);
    }

    /**
     * PATCH /api/admin/complaints/{complaint} — تحديث الحالة + رد الأدمن
     */
    public function updateComplaint(Request $request, Complaint $complaint)
    {
        $request->validate([
            'status'         => 'required|in:pending,in_progress,resolved',
            'admin_response' => 'nullable|string|max:2000',
        ]);

        $data = ['status' => $request->status];

        if ($request->filled('admin_response')) {
            $data['admin_response'] = $request->admin_response;
        }

        if ($request->status === 'resolved') {
            $data['resolved_at'] = now();
        } elseif ($request->status !== 'resolved') {
            $data['resolved_at'] = null;
        }

        $complaint->update($data);
        $complaint->load('user:id,name,email');

        return response()->json([
            'success'   => true,
            'message'   => $request->status === 'resolved'
                ? 'Complaint marked as resolved'
                : 'Complaint updated',
            'complaint' => $complaint,
        ]);
    }

    /**
     * GET /api/admin/talent-activity
     */
    public function talentActivity()
    {
        $activities = collect();

        UserResume::with('user:id,name')
            ->latest()
            ->take(15)
            ->get()
            ->each(function ($r) use ($activities) {
                $activities->push([
                    'name'       => $r->user?->name ?? 'User',
                    'action'     => 'Uploaded CV',
                    'icon'       => 'cv',
                    'time'       => $r->created_at->diffForHumans(),
                    'created_at' => $r->created_at,
                ]);
            });

        UserRoadmap::with('user:id,name')
            ->latest()
            ->take(15)
            ->get()
            ->each(function ($r) use ($activities) {
                $activities->push([
                    'name'       => $r->user?->name ?? 'User',
                    'action'     => 'Roadmap generated',
                    'icon'       => 'roadmap',
                    'time'       => $r->created_at->diffForHumans(),
                    'created_at' => $r->created_at,
                ]);
            });

        UserQuiz::with('user:id,name')
            ->whereNotNull('score')
            ->latest()
            ->take(15)
            ->get()
            ->each(function ($q) use ($activities) {
                $activities->push([
                    'name'       => $q->user?->name ?? 'User',
                    'action'     => 'Quiz completed (' . $q->score . '%)',
                    'icon'       => 'quiz',
                    'time'       => $q->updated_at->diffForHumans(),
                    'created_at' => $q->updated_at,
                ]);
            });

        $sorted = $activities->sortByDesc('created_at')->values()->take(30);

        return response()->json(['success' => true, 'data' => $sorted]);
    }

    /**
     * POST /api/admin/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    protected function buildNotifications(): array
    {
        $items = [];

        Company::with('user')
            ->where('verification_status', 'pending')
            ->whereNotNull('commercial_register_path')
            ->latest()
            ->take(3)
            ->get()
            ->each(function ($c) use (&$items) {
                $items[] = [
                    'type'  => 'verification',
                    'title' => 'New Company: ' . ($c->user?->name ?? 'Unknown'),
                    'time'  => $c->created_at->diffForHumans(),
                ];
            });

        Job::where('is_paid', true)
            ->latest()
            ->take(3)
            ->get()
            ->each(function ($j) use (&$items) {
                $items[] = [
                    'type'  => 'payment',
                    'title' => 'Payment: ' . $j->title,
                    'time'  => $j->updated_at->diffForHumans(),
                ];
            });

        Complaint::where('status', 'pending')
            ->latest()
            ->take(3)
            ->get()
            ->each(function ($c) use (&$items) {
                $items[] = [
                    'type'  => 'complaint',
                    'title' => 'Complaint: ' . $c->subject,
                    'time'  => $c->created_at->diffForHumans(),
                ];
            });

        return array_slice($items, 0, 8);
    }
}
