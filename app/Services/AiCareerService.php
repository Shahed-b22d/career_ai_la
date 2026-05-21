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
     * دالة عامة للاتصال بـ Gemini
     */
    protected function callGemini(string $prompt, string $systemInstruction = null): string
    {
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

        Log::info("DEBUG: API Key Hint: " . substr($this->geminiApiKey, 0, 4) . "...");
        
        $url = $this->geminiUrl . '?key=' . $this->geminiApiKey;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $responseRaw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        Log::info("DEBUG: Gemini CURL HTTP Status: " . $httpCode);
        if ($curlError) {
            Log::info("DEBUG: CURL Error: " . $curlError);
        }

        Log::info("DEBUG: Gemini Raw Response: " . $responseRaw);

        if ($httpCode == 200) {
            $data = json_decode($responseRaw, true);
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }
        }

        Log::error("Gemini API Error: " . ($curlError ?: $responseRaw));
        throw new Exception("Failed to get response from AI. " . ($curlError ?: $responseRaw));
    }

    /**
     * 1. قراءة الـ CV باستخدام PDF Parser
     */
    public function readCv(string $pdfFilePath): string
    {
        if (!file_exists($pdfFilePath)) {
            throw new Exception("The uploaded CV file was not found.");
        }

        try {
            // استخدام مكتبة Smalot لاستخراج النص من PDF
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($pdfFilePath);
            $text = $pdf->getText();
            
            // تنظيف النص من الأحرف الغريبة والمسافات الزائدة
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);
            
            if (empty($text)) {
                throw new Exception("Could not extract text from PDF. The file might be image-based or corrupted.");
            }
            
            Log::info("DEBUG: CV Text Extracted Successfully. Length: " . strlen($text));
            return $text;
            
        } catch (\Exception $e) {
            Log::error("PDF Reading Error: " . $e->getMessage());
            throw new Exception("Failed to read CV file: " . $e->getMessage());
        }
    }


    /**
     * 3. استخراج المهارات الناقصة بناءً على ملف الـ CV والوظيفة المستهدفة
     */
    public function analyzeGap(string $cvText, string $targetJob): array
    {
        $systemInstruction = "You are a Career Analysis Expert. Analyze the user's CV/information and compare it with the target job requirements.
Format your output strictly as a raw JSON object (No markdown wrappers like ```json, no explanations outside the JSON).
Schema:
{
  \"current_skills\": [\"list of EXISTING skills found in CV (CONCISE, 1-3 words each)\"],
  \"missing_skills\": [\"list of MISSING skills needed for target job (CONCISE, 1-3 words each)\"],
  \"panel_feedback\": \"A concise, encouraging summary from the Career Advisor.\"
}";
        
        $prompt = "Target Job Role: {$targetJob}

User's CV/Information:
---
{$cvText}
---

Instructions:
1. Carefully analyze the CV/information text above.
2. Extract 'current_skills' that the user ALREADY HAS based on their experience, education, and mentioned skills.
3. Identify 'missing_skills' that are required for the '{$targetJob}' role but NOT present in the user's CV.
4. KEEP SKILL NAMES SHORT and SPECIFIC (e.g., 'Flutter', 'React', 'Python', 'SQL', 'Git').
5. Provide encouraging feedback in 'panel_feedback'.
6. Return ONLY the JSON object, no other text.";

        Log::info("DEBUG: Calling Gemini for Gap Analysis...");
        Log::info("DEBUG: CV Text Length: " . strlen($cvText));
        Log::info("DEBUG: Target Job: " . $targetJob);
        
        $response = $this->callGemini($prompt, $systemInstruction);
        Log::info("DEBUG: Gemini Raw Response: " . $response);
        
        // استخراج الـ JSON فقط من بين الأقواس المجعدة { ... }
        $cleanResponse = $response;
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $cleanResponse = $matches[0];
        }
        
        // إزالة أي markdown wrappers
        $cleanResponse = preg_replace('/```json\s*|\s*```/', '', $cleanResponse);
        $cleanResponse = trim($cleanResponse);

        Log::info("DEBUG: Clean JSON Response: " . $cleanResponse);
        
        $parsed = json_decode($cleanResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("JSON Decode Error: " . json_last_error_msg());
            Log::error("Failed Response: " . $cleanResponse);
            throw new Exception("Failed to parse AI response: " . json_last_error_msg());
        }
        
        return $parsed ?: ['current_skills' => [], 'missing_skills' => [], 'panel_feedback' => ''];
    }

    /**
     * Scrape DuckDuckGo for REAL free courses across multiple platforms
     */
    protected function scrapeRealCoursesForSkill(string $skill): array
    {
        $platforms = [
            'YouTube' => 'site:youtube.com "full course" OR tutorial',
            'Coursera' => 'site:coursera.org "free course"',
            'Udemy' => 'site:udemy.com "free tutorial"'
        ];

        $courses = [];

        foreach ($platforms as $platformName => $searchQuery) {
            $query = urlencode($searchQuery . ' ' . $skill);
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9'
                ])
                ->get("https://html.duckduckgo.com/html/?q={$query}");
            
            if ($response->successful()) {
                try {
                    $crawler = new Crawler($response->body());
                    $firstResult = $crawler->filter('.result__title .result__a')->first();
                    
                    if ($firstResult->count() > 0) {
                        $url = $firstResult->attr('href');
                        // DuckDuckGo redirects wrapper: //duckduckgo.com/l/?uddg=...
                        if (str_contains($url, 'uddg=')) {
                            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $queryArgs);
                            if (isset($queryArgs['uddg'])) {
                                $url = urldecode($queryArgs['uddg']);
                            }
                        }
                        
                        $courses[] = [
                            'platform' => $platformName,
                            'title' => trim($firstResult->text()),
                            'url' => $url
                        ];
                    }
                } catch (\Exception $e) {
                    // Ignore extraction errors for this platform and continue
                }
            }
        }

        return $courses;
    }

    /**
     * 4 & 5. اقتراح الكورسات من الويب وتوليد رود ماب
     */
    public function generateRoadmapAndCourses(array $missingSkills, string $targetJob): array
    {
        $systemInstruction = "You are a 'Hiring Panel & Career Advisory Board'.
Your goal is to construct a highly structured, practical learning roadmap and suggest real courses.
Output strictly as a raw JSON object.
Schema:
{
  \"roadmap\": \"Highly detailed step-by-step markdown text defining the learning phases...\",
  \"skills_courses\": [
    {
      \"skill\": \"Skill Name\",
      \"courses\": [
        {\"platform\": \"YouTube\", \"title\": \"Course Title\", \"url\": \"link\"},
        {\"platform\": \"Coursera/Udemy\", \"title\": \"Course Title\", \"url\": \"link\"}
      ]
    }
  ]
}";
        $skillsStr = implode(", ", $missingSkills);
        $prompt = "Target Role: {$targetJob}. 
The user needs to acquire: {$skillsStr}.
Instructions:
1. Write a detailed learning roadmap (markdown).
2. For each skill, suggest 2-3 real courses with titles and URLs.
Return ONLY the JSON.";

        $response = $this->callGemini($prompt, $systemInstruction);
        // استخراج الـ JSON فقط من بين الأقواس المجعدة { ... }
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $response = $matches[0];
        }
        
        $aiData = json_decode($response, true);
        
        return $aiData ?: ['roadmap' => '', 'skills_courses' => []];
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
        $prompt = "Create a 5-question multiple-choice quiz about: {$skillsStr}. 
Each question must have 4 options (labeled A, B, C, D) and one 'correct_answer' (matching one of the options exactly). 
Make questions practical and scenario-based when possible.
Return ONLY raw JSON, no other text.";

        Log::info("DEBUG: Calling Gemini for Quiz Generation...");
        Log::info("DEBUG: Skills to Test: " . $skillsStr);
        
        $response = $this->callGemini($prompt, $systemInstruction);
        Log::info("DEBUG: Gemini Quiz Raw Response: " . $response);
        
        // استخراج الـ JSON فقط من بين الأقواس المجعدة { ... }
        $cleanResponse = $response;
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $cleanResponse = $matches[0];
        }
        
        // إزالة أي markdown wrappers
        $cleanResponse = preg_replace('/```json\s*|\s*```/', '', $cleanResponse);
        $cleanResponse = trim($cleanResponse);

        Log::info("DEBUG: Clean Quiz JSON: " . $cleanResponse);

        $parsed = json_decode($cleanResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("JSON Decode Error in Quiz: " . json_last_error_msg());
            Log::error("Failed Response: " . $cleanResponse);
            throw new Exception("Failed to parse quiz response: " . json_last_error_msg());
        }
        
        return $parsed ?: ['quiz' => []];
    }

    /**
     * 2. توليد CV احترافي بنظام ATS
     */
    public function generateAtsCv(string $userDataText, array $allSkills, string $personalInfo = ""): string
    {
        $systemInstruction = "You are a 'Hiring Panel & Career Advisory Board' consisting of 4 distinct personas:
1. ATS & HR Specialist (Focuses on strict ATS compatibility, semantic structure, and keyword density)
2. Senior Tech Lead (Focuses on highlighting technical depth, achievements, and project complexity)
3. Hiring Manager (Focuses on emphasizing business impact, leadership, and value)
4. Career Advisor (Ensures the overall tone is professional, confident, and authentic)

Your task is to collaboratively rewrite the user's CV data into a pristine, highly-optimized ATS-friendly HTML format.
Rules:
1. Output ONLY valid, clean HTML code (No markdown like ```html).
2. DO NOT use complex CSS, tables, columns, graphics, or ANY colors (strict black and white semantic text only).
3. The layout MUST include: Contact Info (Header), Professional Summary, Core Competencies (Skills), Professional Experience, and Education.
4. The HR ensures the format is ATS-friendly. The Tech Lead ensures technical skills are prominent. The Hiring Manager ensures impact is highlighted. The Career Advisor finalizes the professional tone.
5. Use simple HTML tags: <h1>, <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em> only.
6. Ensure proper spacing and readability for ATS parsers.";
        
        $skillsStr = implode(", ", $allSkills);
        $prompt = "User's Personal Information (MUST be used in the header of the CV exactly as provided):
---
{$personalInfo}
---

User's Original CV/Information:
---
{$userDataText}
---

User's Complete Skill Set (including newly mastered skills): {$skillsStr}

Instructions:
1. HR Specialist Step: Organize the structure for flawless ATS parsing using simple semantic HTML.
2. Tech Lead Step: Integrate ALL skills from the skill set naturally into a 'Core Competencies' or 'Technical Skills' section. Highlight technical achievements from the original CV.
3. Hiring Manager Step: Elevate the professional tone using strong action verbs to show business impact in the experience section.
4. Career Advisor Step: Finalize the CV to ensure it accurately and impressively represents the candidate. Make sure the CV flows naturally and professionally.
5. Use the personal information provided for the contact header, replacing any old contact info in the original text.
6. Return ONLY the clean HTML code with no markdown wrappers.";

        Log::info("DEBUG: Generating ATS CV with " . count($allSkills) . " skills");
        
        $response = $this->callGemini($prompt, $systemInstruction);
        
        // إزالة أي markdown wrappers
        $htmlCv = preg_replace('/```html\s*|\s*```/', '', $response);
        $htmlCv = trim($htmlCv);
        
        Log::info("DEBUG: ATS CV Generated Successfully");
        
        return $htmlCv;
    }
}
