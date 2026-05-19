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

// مسارات الذكاء الاصطناعي المجانية والاحترافية
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

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

        // 2: توليد CV احترافي (ATS)
        Route::post('/cv/generate', [AiController::class, 'generateAtsCv']);
    });
});
