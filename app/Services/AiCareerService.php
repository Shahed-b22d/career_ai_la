<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class AiCareerService
{
    protected string $geminiApiKey;
    protected string $geminiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $this->geminiApiKey = config('services.gemini.key');
    }

    /**
     * دالة عامة للاتصال بـ Gemini
     */
    protected function callGemini(string $prompt, string $systemInstruction = null): string
    {
        // Clean UTF-8 strings to prevent json_encode failures on malformed text
        $prompt = mb_convert_encoding($prompt, 'UTF-8', 'UTF-8');
        if ($systemInstruction !== null) {
            $systemInstruction = mb_convert_encoding($systemInstruction, 'UTF-8', 'UTF-8');
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

        Log::info("DEBUG: API Key Hint: " . substr($this->geminiApiKey, 0, 4) . "...");
        
        $url = $this->geminiUrl . '?key=' . $this->geminiApiKey;

        $encodedPayload = json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encodedPayload === false) {
            Log::error("JSON encode failed in callGemini: " . json_last_error_msg());
            throw new Exception("Failed to encode request payload: " . json_last_error_msg());
        }

        // Retry logic: up to 3 attempts on 429 RESOURCE_EXHAUSTED
        $maxAttempts = 3;
        $httpCode    = 0;
        $responseRaw = '';
        $curlError   = '';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);

            $responseRaw = curl_exec($ch);
            $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError   = curl_error($ch);
            curl_close($ch);

            Log::info("DEBUG: Gemini CURL HTTP Status (attempt {$attempt}): " . $httpCode);
            if ($curlError) {
                Log::info("DEBUG: CURL Error: " . $curlError);
            }

            if ($httpCode == 200) {
                break; // success – exit retry loop
            }

            if ($httpCode == 429 && $attempt < $maxAttempts) {
                // Parse retryDelay from the response (e.g. "7s" → 7 seconds)
                $retryDelay = 20; // default seconds
                $decoded429 = json_decode($responseRaw, true);
                if (isset($decoded429['error']['details'])) {
                    foreach ($decoded429['error']['details'] as $detail) {
                        if (isset($detail['retryDelay'])) {
                            $retryDelay = (int) filter_var($detail['retryDelay'], FILTER_SANITIZE_NUMBER_INT);
                            $retryDelay = max($retryDelay, 5); // minimum 5s
                            break;
                        }
                    }
                }
                Log::warning("Gemini 429 quota exceeded. Waiting {$retryDelay}s before retry (attempt {$attempt}/{$maxAttempts})...");
                sleep($retryDelay);
                continue;
            }

            // For non-429 errors, break immediately
            break;
        }

        Log::info("DEBUG: Gemini Raw Response: " . substr($responseRaw, 0, 500));

        if ($httpCode == 200) {
            $data = json_decode($responseRaw, true);
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }
        }

        // Friendly message for quota errors
        if ($httpCode == 429) {
            throw new Exception("AI service is busy due to high usage. Please wait a moment and try again.");
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

            // Clean UTF-8 strings to prevent DB insertion and JSON encoding issues on malformed PDF text
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            
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

    /**
     * وظيفة لترشيح أفضل المرشحين بناءً على وصف وظيفة معين من قبل الشركة
     */
    public function rankCandidatesForCompany(string $jobDescription, array $candidatesResumes): array
    {
        $systemInstruction = "You are a Senior Technical Recruiter. Your task is to evaluate a list of candidates against a specific job description.
Analyze their current skills and experience. Output strictly as a JSON array of objects.
Schema:
[
  {
    \"resume_id\": \"id of the resume\",
    \"match_score\": \"percentage (0-100)\",
    \"justification\": \"short 1-sentence reason for this score\"
  }
]";

        // تحويل بيانات المرشحين إلى نص مبسط ليتمكن الذكاء الاصطناعي من تحليله بسرعة
        $candidatesData = array_map(function($item) {
            return [
                'resume_id' => $item['id'],
                'job_target' => $item['target_job'],
                'skills' => $item['current_skills'],
                'summary' => substr($item['original_text'], 0, 500) // نرسل جزء من النص لتوفير الـ tokens
            ];
        }, $candidatesResumes);

        $prompt = "Job Description: {$jobDescription}

List of Candidates:
" . json_encode($candidatesData) . "

Instructions:
1. Compare each candidate with the job description.
2. Calculate a 'match_score' based on skill overlap and experience.
3. Provide a brief 'justification'.
4. Return ONLY the JSON array.";

        Log::info("DEBUG: Ranking " . count($candidatesResumes) . " candidates for a job.");

        try {
            $response = $this->callGemini($prompt, $systemInstruction);
            if (preg_match('/\[.*\]/s', $response, $matches)) {
                $response = $matches[0];
            }
            $rankedData = json_decode($response, true);
            return $rankedData ?: [];
        } catch (Exception $e) {
            Log::error("Error in ranking candidates: " . $e->getMessage());
            return [];
        }
    }

    /**
     * حساب نسبة التوافق الحقيقية بين مرشح واحد ووظيفة واحدة باستخدام Gemini AI.
     *
     * يُرجع مصفوفة تحتوي على:
     *   - match_score  : int  (0-100)
     *   - justification: string (جملة واحدة تشرح السبب)
     *
     * معايير التقييم التي يستخدمها الـ AI (مجموعها 100):
     *   - تطابق المهارات مع متطلبات الوظيفة  (50 نقطة)
     *   - تطابق المسمى الوظيفي               (25 نقطة)
     *   - الخبرة والسياق من نص الـ CV         (25 نقطة)
     */
    public function scoreOneCandidate(
        string $jobTitle,
        string $jobDescription,
        string $jobRequirements,
        array  $candidateSkills,
        string $candidateTargetJob,
        string $candidateCvText = ''
    ): array {
        $systemInstruction = "You are a Senior Technical Recruiter and ATS Specialist.
Evaluate how well a candidate matches a specific job posting.
Scoring breakdown (total = 100 points):
  - Skills match (50 pts): Count how many of the job's required skills the candidate already has.
  - Target job alignment (25 pts): Does the candidate's desired job title match the posted job title?
  - Experience & context (25 pts): Based on the CV text, does the candidate have relevant experience?

Output ONLY a raw JSON object — no markdown, no explanation outside JSON.
Schema: {\"match_score\": <integer 0-100>, \"justification\": \"<one sentence>\"}";

        $skillsList = implode(', ', $candidateSkills) ?: 'No skills listed';
        $cvSnippet  = $candidateCvText !== ''
            ? substr($candidateCvText, 0, 800)
            : 'No CV text available.';

        $prompt = "JOB POSTING:
Title: {$jobTitle}
Requirements: {$jobRequirements}
Description: {$jobDescription}

CANDIDATE PROFILE:
Desired Job Title: {$candidateTargetJob}
Skills: {$skillsList}
CV Summary: {$cvSnippet}

Return ONLY the JSON object:";

        try {
            $raw = trim($this->callGemini($prompt, $systemInstruction));

            // استخراج JSON من الرد
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                $raw = $m[0];
            }
            $raw = preg_replace('/```json\s*|\s*```/', '', $raw);

            $data = json_decode(trim($raw), true);

            if (json_last_error() === JSON_ERROR_NONE && isset($data['match_score'])) {
                return [
                    'match_score'   => max(0, min(100, (int) $data['match_score'])),
                    'justification' => $data['justification'] ?? '',
                ];
            }

            // إذا فشل الـ JSON، نحاول استخراج رقم فقط
            preg_match('/\d+/', $raw, $nm);
            $score = isset($nm[0]) ? max(0, min(100, (int) $nm[0])) : 0;
            return ['match_score' => $score, 'justification' => ''];

        } catch (Exception $e) {
            Log::error("AI scoreOneCandidate error: " . $e->getMessage());
            // نرجع -1 لتمييز فشل الـ AI عن نسبة صفر حقيقية
            return ['match_score' => -1, 'justification' => ''];
        }
    }
}
