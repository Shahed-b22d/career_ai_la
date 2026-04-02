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

// مسارات الذكاء الاصطناعي المجانية والاحترافية
Route::prefix('ai')->group(function () {
    // 1 & 3: قراءة الـ CV وتحديد المهارات الناقصة بناءً على الشاغر المطلوب
    Route::post('/cv/gap-analysis', [AiController::class, 'analyzeGap']);

    // 4 & 5: توليد خريطة التعلم واقتراح الكورسات
    Route::post('/career/roadmap', [AiController::class, 'generateRoadmap']);

    // 6: توليد كويز اختباري
    Route::post('/career/quiz', [AiController::class, 'generateQuiz']);

    // 2: توليد CV احترافي (ATS)
    Route::post('/cv/generate', [AiController::class, 'generateAtsCv']);
});
