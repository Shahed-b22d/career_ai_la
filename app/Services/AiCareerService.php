<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;
use Symfony\Component\DomCrawler\Crawler;

class AiCareerService
{
    protected string $geminiApiKey;
    protected string $geminiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';

    public function __construct()
    {
        // يجب تعيين قيمة GEMINI_API_KEY في ملف .env
        $this->geminiApiKey = config('services.gemini.key');    }

    /**
     * دالة مساعدة للاتصال بـ Gemini API
     */
    protected function callGemini(string $prompt, ?string $systemInstruction = null): string
    {
        if (empty($this->geminiApiKey)) {
            throw new Exception("Gemini API Key is missing. Please add GEMINI_API_KEY to your .env file.");
        }

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
            ]
        ];

        if ($systemInstruction) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemInstruction]
                ]
            ];
        }

        $response = Http::retry(3, 2000)
            ->timeout(120)
            ->withoutVerifying()
            ->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->geminiUrl . '?key=' . $this->geminiApiKey, $payload);

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }
        }

        Log::error("Gemini API Error: " . $response->body());
        throw new Exception("Failed to get response from AI. " . $response->body());
    }

    /**
     * 1. قراءة الـ CV باستخدام Spatie/pdf-to-text
     */
    public function readCv(string $pdfFilePath): string
    {
        if (!file_exists($pdfFilePath)) {
            throw new Exception("The uploaded CV file was not found.");
        }

        // ملاحظة: يتطلب تثبيت أداة pdftotext على نظام التشغيل لتشغيل الحزمة بنجاح
        $text = Pdf::getText($pdfFilePath);
        
        return $text;
    }

    /**
     * 3. استخراج المهارات الناقصة بناءً على ملف الـ CV والوظيفة المستهدفة
     */
    public function analyzeGap(string $cvText, string $targetJob): array
    {
        $systemInstruction = "You are a 'Hiring Panel & Career Advisory Board' consisting of 4 distinct personas:
1. ATS & HR Specialist (Focuses on keywords, formatting, and soft skills)
2. Senior Tech Lead (Focuses on technical depth, tooling, and modern stack relevance)
3. Hiring Manager / CTO (Focuses on business impact, leadership, and problem-solving)
4. Career Advisor (Synthesizes the critiques into constructive feedback and actionable steps)

Your task is to review the candidate's CV for the target job role. First, internally simulate a discussion among the HR, Tech Lead, and Manager to evaluate the CV. Then, as the Career Advisor, synthesize their findings into a cohesive, hallucination-free JSON response.
Format your output strictly as a raw JSON object (No markdown wrappers like ```json, no explanations outside the JSON).
Schema:
{
  \"current_skills\": [\"list of existing skills extracted accurately from the CV\"],
  \"missing_skills\": [\"list of critical missing hard/soft skills required for the target job based on the panel's consensus\"],
  \"panel_feedback\": \"A concise, encouraging summary from the Career Advisor combining the perspectives of the HR, Tech Lead, and Hiring Manager.\"
}";
        
        $prompt = "Target Job Role: {$targetJob}

Below is the user's raw CV text (which may contain messy formatting or OCR errors due to PDF extraction - please interpret intelligently):
---
{$cvText}
---

Instructions:
1. HR Specialist Step: Analyze for soft skills and ATS keyword matches for '{$targetJob}'.
2. Tech Lead Step: Analyze for required hard skills, technical depth, and missing modern tools.
3. Hiring Manager Step: Analyze for leadership, impact, and overall market readiness.
4. Career Advisor Step: Synthesize the 3 reviews, extract the definitive list of 'current_skills', pinpoint the most critical 'missing_skills', and write a concise, actionable summary ('panel_feedback') combining all views.
5. DO NOT invent or hallucinate fake skills. Rely strictly on legitimate, globally recognized industry standard skills.
Return ONLY the JSON.";

        $response = $this->callGemini($prompt, $systemInstruction);
        
        // تنظيف الرد إذا كان يحتوي على markdown block (```json ... ```)
        $response = preg_replace('/```json|```/', '', $response);
        $response = trim($response);

        $parsed = json_decode($response, true);
        
        return $parsed ?: ['current_skills' => [], 'missing_skills' => []];
    }

    /**
     * Scrape simple search results to simulate RAG for courses (Web Scraper)
     */
    protected function scrapeGoogleForCourses(string $skill): string
    {
        // هذا مجرد نموذج مبسط لمحاكاة عملية الـ Web Scraping 
        $searchQuery = urlencode("best free course OR tutorial for " . $skill);
        $response = Http::withoutVerifying()->get("https://html.duckduckgo.com/html/?q={$searchQuery}");
        
        if ($response->successful()) {
            try {
                $crawler = new Crawler($response->body());
                $results = $crawler->filter('.result__title .result__a')->slice(0, 5)->each(function (Crawler $node, $i) {
                    return $node->text() . ' - URL: ' . $node->attr('href');
                });
                return implode("\n", $results);
            } catch (\Exception $e) {
                return '';
            }
        }
        return '';
    }

    /**
     * 4 & 5. اقتراح الكورسات من الويب وتوليد رود ماب
     */
    public function generateRoadmapAndCourses(array $missingSkills, string $targetJob): array
    {
        // استخدام سكرابر مبسط لجلب عناوين دروس وكورسات من الويب للمهارات الأساسية
        $scrapedData = "";
        foreach (array_slice($missingSkills, 0, 3) as $skill) {
            $scraped = $this->scrapeGoogleForCourses($skill);
            $scrapedData .= "Courses found online for {$skill}:\n{$scraped}\n\n";
        }

        $systemInstruction = "You are a 'Hiring Panel & Career Advisory Board' consisting of 4 distinct personas:
1. ATS & HR Specialist (Ensures the roadmap focuses on employable skills)
2. Senior Tech Lead (Designs the technical learning phases and validates course quality)
3. Hiring Manager (Ensures the learning outcomes align with real-world business needs)
4. Career Advisor (Structures the roadmap to be highly motivating and practical for the user)

Your collective goal is to construct a highly structured, practical learning roadmap and map it to specific courses.
Output strictly as a raw JSON object (No markdown wrappers like ```json, no preamble).
Schema:
{
  \"roadmap\": \"Highly detailed step-by-step markdown text defining the learning phases, written by the Career Advisor based on the panel's input...\",
  \"suggested_courses\": [
    {\"title\": \"Course Title\", \"url\": \"Course URL\", \"skill\": \"Target Skill\"}
  ]
}";

        $skillsStr = implode(", ", $missingSkills);
        $prompt = "Target Role: {$targetJob}. 
The user urgently needs to acquire these missing skills: {$skillsStr}.

Available scraped course data from the web:
---
{$scrapedData}
---

Instructions:
1. Tech Lead Step: Filter the scraped courses and add your own top-tier suggestions (Coursera, Udemy, YouTube) for the missing skills.
2. Hiring Manager Step: Prioritize the learning sequence based on what makes the candidate hirable fastest.
3. Career Advisor Step: Write a detailed, phase-by-phase learning roadmap (in markdown format) tailored to mastering these skills, integrating the chosen courses.
4. DO NOT invent or hallucinate fake course links. Only suggest real, verifiable courses.
Return ONLY the JSON.";

        $response = $this->callGemini($prompt, $systemInstruction);
        $response = preg_replace('/```json|```/', '', $response);
        $response = trim($response);
        
        return json_decode($response, true) ?: ['roadmap' => '', 'suggested_courses' => []];
    }

    /**
     * 6. توليد كويز للتأكد من تعلم المهارات
     */
    public function generateQuiz(array $skillsToTest): array
    {
        $systemInstruction = "You are an Expert Technical Assessor. Your job is to create challenging, practical multiple-choice questions to verify a user's comprehension of specific skills.
Output strictly as a raw JSON object (No markdown wrappers like ```json).
Schema:
{
  \"quiz\": [
    {
       \"question\": \"Clear, scenario-based or technical question?\",
       \"options\": [\"A) ...\", \"B) ...\", \"C) ...\", \"D) ...\"],
       \"correct_answer\": \"The exact string of the correct option\"
    }
  ]
}";
        $skillsStr = implode(", ", $skillsToTest);
        $prompt = "Generate a comprehensive 5-question multiple-choice quiz covering these newly acquired skills: {$skillsStr}. 
Ensure the questions range from basic concepts to practical, intermediate scenarios. Every question must have exactly 4 options. The incorrect options (distractors) MUST be highly plausible and not trivially easy to guess. Return ONLY the JSON.";

        $response = $this->callGemini($prompt, $systemInstruction);
        $response = preg_replace('/```json|```/', '', $response);
        $response = trim($response);

        return json_decode($response, true) ?: ['quiz' => []];
    }

    /**
     * 2. توليد CV احترافي بنظام ATS
     */
    public function generateAtsCv(string $userDataText, array $newSkills): string
    {
        $systemInstruction = "You are a 'Hiring Panel & Career Advisory Board' consisting of 4 distinct personas:
1. ATS & HR Specialist (Focuses on strict ATS compatibility, semantic structure, and keyword density)
2. Senior Tech Lead (Focuses on highlighting technical depth, achievements, and project complexity)
3. Hiring Manager (Focuses on emphasizing business impact, leadership, and value)
4. Career Advisor (Ensures the overall tone is professional, confident, and authentic)

Your task is to collaboratively rewrite the user's CV data into a pristine, highly-optimized ATS-friendly HTML format, incorporating their newly mastered skills.
Rules:
1. Output ONLY valid, clean HTML code (No markdown like ```html).
2. DO NOT use complex CSS, tables, columns, graphics, or ANY colors (strict black and white semantic text only).
3. The layout MUST include: Contact Info (Header), Professional Summary, Core Competencies (Skills), Professional Experience, and Education.
4. The HR ensures the format is ATS-friendly. The Tech Lead ensures technical skills are prominent. The Hiring Manager ensures impact is highlighted. The Career Advisor finalizes the professional tone.";
        
        $skillsStr = implode(", ", $newSkills);
        $prompt = "User's Original Information/CV:
---
{$userDataText}
---

NEWLY Mastered Skills: {$skillsStr}.

Instructions:
1. HR Specialist Step: Organize the structure for flawless ATS parsing.
2. Tech Lead Step: Integrate the 'NEWLY Mastered Skills' naturally and highlight technical achievements.
3. Hiring Manager Step: Elevate the professional tone using strong action verbs to show business impact.
4. Career Advisor Step: Finalize the CV to ensure it accurately and impressively represents the candidate.
Return ONLY the clean HTML code.";

        $response = $this->callGemini($prompt, $systemInstruction);
        $htmlCv = preg_replace('/```html|```/', '', $response);
        
        return trim($htmlCv);
    }
}
