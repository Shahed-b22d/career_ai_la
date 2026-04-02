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
        $this->geminiApiKey = env('GEMINI_API_KEY', '');
    }

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
        $systemInstruction = "You are an expert HR and Career Advisor. Analyze the user's CV against the job requirements for the target role. Return the response ONLY as a valid JSON object format containing 'current_skills' (array) and 'missing_skills' (array). Do not add any markdown formatting like ```json.";
        
        $prompt = "Target Job Role: {$targetJob}\n\nHere is the user's CV in raw text:\n{$cvText}\n\nWhat are the vital missing skills they need to acquire to be competitive for this role in the current job market?";

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

        $systemInstruction = "You are a career and learning path expert. Output ONLY valid JSON containing 'roadmap' (string: step-by-step markdown) and 'suggested_courses' (array of objects containing 'title', 'url', 'skill'). Do not use ```json wrappers.";

        $skillsStr = implode(", ", $missingSkills);
        $prompt = "The user is aiming to become a {$targetJob} and is missing the following skills: {$skillsStr}.\n";
        $prompt .= "Here is some recently scraped course data from the web:\n{$scrapedData}\n";
        $prompt .= "Create a step-by-step learning roadmap and suggest specific courses (utilizing the scraped data if relevant, or your own knowledge) to help them land the job.";

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
        $systemInstruction = "You are a technical examiner. Generate a quiz. Output ONLY valid JSON format containing 'quiz' (array of objects with 'question', 'options' as array, and 'correct_answer'). No markdown wrappers.";
        $skillsStr = implode(", ", $skillsToTest);
        $prompt = "Generate a 5-question multiple choice quiz to test someone's basic to intermediate knowledge on the following newly acquired skills: {$skillsStr}.";

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
        $systemInstruction = "You are an expert resume writer specialized in ATS-friendly formatting. Return ONLY valid HTML code for an ATS-friendly CV. Do not include ```html or any other markdown. The CV should be heavily optimized to pass through ATS systems.";
        
        $skillsStr = implode(", ", $newSkills);
        $prompt = "Here is the user's initial information (from their old CV or manual entry):\n{$userDataText}\n\n";
        $prompt .= "The user has now successfully learned and mastered these NEW skills: {$skillsStr}.\n\n";
        $prompt .= "Rewrite their whole CV professionally. Incorporate the new skills naturally into their profile summary and skills section. Structure it nicely using basic inline HTML CSS so it looks clean when converted to PDF. Focus on simple, ATS compliant fonts and structure (Title, Summary, Skills, Experience, Education).";

        $response = $this->callGemini($prompt, $systemInstruction);
        $htmlCv = preg_replace('/```html|```/', '', $response);
        
        return trim($htmlCv);
    }
}
