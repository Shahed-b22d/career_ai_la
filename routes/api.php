<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\AdminController;

// مسارات الذكاء الاصطناعي المجانية والاحترافية
// Admin (public login only)
Route::post('/admin/login', [AdminController::class, 'login']);

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::post('/logout', [AdminController::class, 'logout']);
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/verifications/companies', [AdminController::class, 'pendingCompanies']);
    Route::post('/verifications/companies/{company}/approve', [AdminController::class, 'approveCompany']);
    Route::post('/verifications/companies/{company}/reject', [AdminController::class, 'rejectCompany']);
    Route::get('/payments/jobs', [AdminController::class, 'pendingPayments']);
    Route::post('/payments/jobs/{job}/confirm', [AdminController::class, 'confirmPayment']);
    Route::get('/complaints', [AdminController::class, 'complaints']);
    Route::get('/complaints/{complaint}', [AdminController::class, 'showComplaint']);
    Route::patch('/complaints/{complaint}', [AdminController::class, 'updateComplaint']);
    Route::get('/talent-activity', [AdminController::class, 'talentActivity']);
});

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login'])->name('login');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/auth/fcm-token', [AuthController::class, 'updateFcmToken']);
    
    // Job posting & Retrieval
    Route::post('/jobs', [JobController::class, 'postJob']);
    Route::get('/jobs', [JobController::class, 'getActiveJobs']);
    Route::get('/company/dashboard', [JobController::class, 'getCompanyDashboardData']);
    Route::get('/candidates/suggested', [JobController::class, 'getSuggestedCandidates']);

    Route::get('/complaints/mine', [ComplaintController::class, 'mine']);
    Route::post('/complaints', [ComplaintController::class, 'store']);

    Route::prefix('ai')->group(function () {
        // 1 & 3: قراءة الـ CV وتحديد المهارات الناقصة بناءً على الشاغر المطلوب
        Route::post('/cv/gap-analysis', [AiController::class, 'analyzeGap']);
        Route::get('/cv/latest', [AiController::class, 'getLatestCv']);

        // 4 & 5: توليد خريطة التعلم واقتراح الكورسات
        Route::post('/career/roadmap', [AiController::class, 'generateRoadmap']);
        Route::get('/career/my-roadmap', [AiController::class, 'getActiveRoadmap']);
        Route::post('/career/update-progress', [AiController::class, 'updateProgress']);

        // 6: توليد كويز اختباري
        Route::post('/career/quiz', [AiController::class, 'generateQuiz']);
        Route::post('/career/quiz/submit', [AiController::class, 'submitQuiz']);

        // 2: توليد CV احترافي (ATS)
        Route::post('/cv/generate', [AiController::class, 'generateAtsCv']);
    });
});

// Public payment success/cancel endpoints
Route::get('/jobs/payment-success', [JobController::class, 'paymentSuccess']);
Route::get('/jobs/payment-cancel', [JobController::class, 'paymentCancel']);
