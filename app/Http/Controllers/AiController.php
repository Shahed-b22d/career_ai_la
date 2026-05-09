<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AiCareerService;
use App\Models\UserResume;
use App\Models\UserRoadmap;
use App\Models\UserQuiz;
use Exception;

class AiController extends Controller
{
    protected AiCareerService $aiService;

    public function __construct(AiCareerService $aiService)
    {
        set_time_limit(120); // زيادة وقت التنفيذ ليناسب استجابات الذكاء الاصطناعي
        $this->aiService = $aiService;
    }

    /**
     * 1 & 3: قراءة الـ CV (أو إدخال يدوي) واستخراج المهارات الناقصة بناءً على الشاغر المطلوب
     * POST /api/ai/cv/gap-analysis
     */
    public function analyzeGap(Request $request)
    {
        $request->validate([
            'target_job' => 'required|string',
            'cv_file' => 'nullable|file|mimes:pdf|max:2048',
            'manual_text' => 'nullable|string', // في حال لم يكن لديه CV
        ]);

        try {
            $cvText = "";
            if ($request->hasFile('cv_file')) {
                // قراءة الـ PDF
                $cvText = $this->aiService->readCv($request->file('cv_file')->getPathname());
            } elseif ($request->filled('manual_text')) {
                $cvText = $request->input('manual_text');
            } else {
                return response()->json(['message' => 'Please provide a CV file or manual text describing experiences and degree.'], 400);
            }

            // استخراج المهارات الناقصة
            $analysisResult = $this->aiService->analyzeGap($cvText, $request->input('target_job'));

            // حفظ البيانات في قاعدة البيانات
            $resume = UserResume::create([
                'user_id' => auth()->id(), // null if not authenticated
                'target_job' => $request->input('target_job'),
                'original_text' => $cvText,
                'current_skills' => $analysisResult['current_skills'] ?? [],
                'missing_skills' => $analysisResult['missing_skills'] ?? [],
            ]);

            return response()->json([
                'success' => true,
                'resume_id' => $resume->id,
                'cv_text' => $cvText,
                'data' => $analysisResult 
            ]);

        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 4 & 5: اقتراح الكورسات وتوليد المسار التعليمي (Roadmap)
     * POST /api/ai/career/roadmap
     */
    public function generateRoadmap(Request $request)
    {
        $request->validate([
            'target_job' => 'required|string',
            'missing_skills' => 'required|array',
        ]);

        try {
            $roadmapAndCourses = $this->aiService->generateRoadmapAndCourses(
                $request->input('missing_skills'),
                $request->input('target_job')
            );

            // حفظ المسار التعليمي للمستخدم
            $roadmap = UserRoadmap::create([
                'user_id' => auth()->id(),
                'target_job' => $request->input('target_job'),
                'roadmap_text' => $roadmapAndCourses['roadmap'] ?? '',
                'missing_skills' => $request->input('missing_skills'),
                'suggested_courses' => $roadmapAndCourses['suggested_courses'] ?? [],
            ]);

            return response()->json([
                'success' => true,
                'roadmap_id' => $roadmap->id,
                'data' => $roadmapAndCourses
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 6: توليد كويز للتأكد من تعلم المهارات
     * POST /api/ai/career/quiz
     */
    public function generateQuiz(Request $request)
    {
        $request->validate([
            'skills_to_test' => 'required|array',
        ]);

        try {
            $quiz = $this->aiService->generateQuiz($request->input('skills_to_test'));

            // حفظ الاختبار لاستخدامه لاحقاً عند تصحيح الأجوبة
            $userQuiz = UserQuiz::create([
                'user_id' => auth()->id(),
                'tested_skills' => $request->input('skills_to_test'),
                'quiz_data' => $quiz['quiz'] ?? [],
            ]);

            return response()->json([
                'success' => true,
                'quiz_id' => $userQuiz->id,
                'data' => $quiz
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * 2: توليد سيرة ذاتية احترافية بصيغة ATS وإرجاعها كملف PDF
     * POST /api/ai/cv/generate
     */
    public function generateAtsCv(Request $request)
    {
        $request->validate([
            'user_data_text' => 'required|string',
            'new_skills' => 'required|array',
        ]);

        try {
            $htmlCv = $this->aiService->generateAtsCv(
                $request->input('user_data_text'),
                $request->input('new_skills')
            );

            // تحويل الـ HTML الذي أرجعه الذكاء الاصطناعي إلى PDF باستخدام laravel-dompdf
            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($htmlCv);

            // يمكنك حفظه في الـ storage وإرجاع مسار الملف، أو إرجاعه مباشرة كـ Response للتحميل
            return $pdf->stream('Professional_ATS_CV.pdf');

        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
