<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AiCareerService;
use App\Services\CandidateScoringService;
use App\Models\UserResume;
use App\Models\UserRoadmap;
use App\Models\UserQuiz;
use Exception;
use Illuminate\Support\Facades\Log;

class AiController extends Controller
{
    protected AiCareerService $aiService;
    protected CandidateScoringService $scoringService;

    public function __construct(AiCareerService $aiService, CandidateScoringService $scoringService)
    {
        set_time_limit(300); // Allow up to 5 min for AI retries on quota limits
        $this->aiService      = $aiService;
        $this->scoringService = $scoringService;
    }

    /**
     * 1 & 3: قراءة الـ CV (أو إدخال يدوي) واستخراج المهارات الناقصة بناءً على الشاغر المطلوب
     * POST /api/ai/cv/gap-analysis
     */
    public function analyzeGap(Request $request)
    {
        Log::info("DEBUG: analyzeGap Request Started");
        $request->validate([
            'target_job' => 'required|string',
            'cv_file' => 'nullable|file|mimes:pdf|max:2048',
            'manual_text' => 'nullable|string', // في حال لم يكن لديه CV
        ]);

        try {
            Log::info("DEBUG: Validation Passed. Target: " . $request->target_job);
            $cvText = "";
            if ($request->hasFile('cv_file')) {
                // قراءة الـ PDF
                Log::info("DEBUG: Calling Gemini for Analysis...");
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
                'user_id' => auth()->id(),
                'target_job' => $request->input('target_job'),
                'original_text' => $cvText,
                'current_skills' => $analysisResult['current_skills'] ?? [],
                'missing_skills' => $analysisResult['missing_skills'] ?? [],
            ]);

            // ── حساب نسبة التوافق مع كل الوظائف النشطة وحفظها في DB ──────────
            try {
                $this->scoringService->scoreAllJobsForCandidate($resume);
            } catch (\Exception $e) {
                Log::error("Scoring after CV upload failed: " . $e->getMessage());
            }

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
            // جعل كل المسارات القديمة غير نشطة
            UserRoadmap::where('user_id', auth()->id())->update(['is_active' => false]);

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
                'suggested_courses' => $roadmapAndCourses['skills_courses'] ?? [],
                'is_active' => true, // ✅ تحديد أنه المسار الحالي النشط
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
        Log::info("DEBUG: Quiz Request Started");
        
        $request->validate([
            'skills_to_test' => 'required|array',
        ]);

        try {
            Log::info("DEBUG: Calling AI for skills: " . json_encode($request->input('skills_to_test')));
            $quiz = $this->aiService->generateQuiz($request->input('skills_to_test'));
            Log::info("DEBUG: AI Response received");

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
     * تقييم إجابات الكويز وتحديث المهارات المكتسبة
     * POST /api/ai/career/quiz/submit
     */
    public function submitQuiz(Request $request)
    {
        $request->validate([
            'quiz_id' => 'required|exists:user_quizzes,id',
            'answers' => 'required|array',
        ]);

        try {
            $userQuiz = UserQuiz::findOrFail($request->quiz_id);
            
            // التحقق من أن الكويز يخص المستخدم الحالي
            if ($userQuiz->user_id !== auth()->id()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // حساب النتيجة
            $quizData = $userQuiz->quiz_data;
            $userAnswers = $request->answers;
            $correctCount = 0;
            $totalQuestions = count($quizData);

            foreach ($quizData as $index => $question) {
                if (isset($userAnswers[$index]) && $userAnswers[$index] === $question['correct_answer']) {
                    $correctCount++;
                }
            }

            $score = ($correctCount / $totalQuestions) * 100;
            
            // حفظ النتيجة
            $userQuiz->update(['score' => $score]);

            // إذا نجح (أكثر من 70%)، نضيف المهارات إلى المهارات المكتسبة في الـ Roadmap
            $passed = $score >= 70;
            
            if ($passed) {
                $roadmap = UserRoadmap::where('user_id', auth()->id())
                    ->where('is_active', true)
                    ->latest()
                    ->first();

                if ($roadmap) {
                    $completedSkills = $roadmap->completed_skills ?? [];
                    foreach ($userQuiz->tested_skills as $skill) {
                        if (!in_array($skill, $completedSkills)) {
                            $completedSkills[] = $skill;
                        }
                    }
                    $roadmap->update(['completed_skills' => $completedSkills]);
                }
            }

            return response()->json([
                'success' => true,
                'score' => $score,
                'passed' => $passed,
                'correct_answers' => $correctCount,
                'total_questions' => $totalQuestions,
                'message' => $passed ? 'Congratulations! You passed the quiz.' : 'You need to score at least 70% to pass.'
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
            'include_new_skills' => 'boolean', // هل نضيف المهارات الجديدة المكتسبة؟
        ]);

        try {
            $user = auth()->user();
            
            // جلب معلومات المستخدم الشخصية
            $personalInfo = "";
            if ($user) {
                $user->load('jobSeeker');
                $phone = optional($user->jobSeeker)->phone ?? $user->phone ?? 'Not specified';
                $gov = optional($user->jobSeeker)->governorate ?? $user->governorate ?? 'Not specified';
                $personalInfo = "Name: {$user->name}\nEmail: {$user->email}\nPhone: {$phone}\nLocation: {$gov}";
            }

            // جلب آخر CV تم رفعه أو المعلومات اليدوية
            $latestResume = UserResume::where('user_id', auth()->id())
                ->latest()
                ->first();

            if (!$latestResume) {
                return response()->json([
                    'success' => false, 
                    'message' => 'No CV or manual information found. Please upload a CV or enter your information first.'
                ], 404);
            }

            $userDataText = $latestResume->original_text;
            $currentSkills = $latestResume->current_skills ?? [];

            // جلب المهارات الجديدة المكتسبة من الكويزات الناجحة
            $newSkills = [];
            if ($request->input('include_new_skills', true)) {
                $roadmap = UserRoadmap::where('user_id', auth()->id())
                    ->where('is_active', true)
                    ->latest()
                    ->first();

                if ($roadmap) {
                    $newSkills = $roadmap->completed_skills ?? [];
                }
            }

            // دمج المهارات الحالية مع الجديدة
            $allSkills = array_unique(array_merge($currentSkills, $newSkills));

            Log::info("DEBUG: Generating ATS CV with skills: " . json_encode($allSkills));

            $htmlCv = $this->aiService->generateAtsCv(
                $userDataText,
                $allSkills,
                $personalInfo
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

    /**
     * جلب المسار التعليمي النشط للمستخدم
     */
    public function getActiveRoadmap()
    {
        $roadmap = UserRoadmap::where('user_id', auth()->id())
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$roadmap) {
            return response()->json(['success' => false, 'message' => 'No active roadmap found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $roadmap->id,
                'target_job' => $roadmap->target_job,
                'roadmap' => $roadmap->roadmap_text,
                'skills_courses' => $roadmap->suggested_courses,
                'completed_skills' => $roadmap->completed_skills ?? [],
                'missing_skills' => $roadmap->missing_skills,
                'created_at' => $roadmap->created_at,
            ]
        ]);
    }

    /**
     * تحديث التقدم في المسار (إكمال مهارة)
     */
    public function updateProgress(Request $request)
    {
        $request->validate([
            'skill' => 'required|string',
        ]);

        $roadmap = UserRoadmap::where('user_id', auth()->id())
            ->where('is_active', true)
            ->latest()
            ->first();

        if (!$roadmap) {
            return response()->json(['success' => false, 'message' => 'No active roadmap found.'], 404);
        }

        $completed = $roadmap->completed_skills ?? [];
        if (!in_array($request->skill, $completed)) {
            $completed[] = $request->skill;
            $roadmap->update(['completed_skills' => $completed]);
        }

        return response()->json([
            'success' => true,
            'completed_skills' => $completed
        ]);
    }

    /**
     * Get the latest resume/CV text of the user
     */
    public function getLatestCv()
    {
        $resume = UserResume::where('user_id', auth()->id())
            ->latest()
            ->first();

        if (!$resume) {
            return response()->json(['success' => false, 'message' => 'No resume found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $resume->id,
                'target_job' => $resume->target_job,
                'original_text' => $resume->original_text,
                'current_skills' => $resume->current_skills ?? [],
                'missing_skills' => $resume->missing_skills ?? [],
                'created_at' => $resume->created_at,
            ]
        ]);
    }
}
