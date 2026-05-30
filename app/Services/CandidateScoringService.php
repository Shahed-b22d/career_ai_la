<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobCandidateScore;
use App\Models\UserResume;
use Illuminate\Support\Facades\Log;

/**
 * CandidateScoringService
 *
 * مسؤول عن حساب وتخزين نسب التوافق بين المرشحين والوظائف.
 *
 * يُستدعى في حالتين:
 *  1. scoreAllJobsForCandidate()  → عند رفع CV جديد أو تحديثه
 *  2. scoreAllCandidatesForJob()  → عند نشر وظيفة جديدة (is_paid = true)
 *
 * النسبة تُحسب عبر Gemini AI وتُخزَّن في جدول job_candidate_scores.
 * إذا فشل الـ AI يُستخدم حساب محلي احتياطي.
 */
class CandidateScoringService
{
    public function __construct(protected AiCareerService $ai) {}

    // ──────────────────────────────────────────────────────────────────────────
    // 1. عند رفع CV جديد: احسب نسبة هذا المرشح مع كل الوظائف النشطة
    // ──────────────────────────────────────────────────────────────────────────
    public function scoreAllJobsForCandidate(UserResume $resume): void
    {
        $jobs = Job::where('is_paid', true)->get();

        if ($jobs->isEmpty()) {
            Log::info("CandidateScoringService: No active jobs to score against for resume#{$resume->id}");
            return;
        }

        foreach ($jobs as $job) {
            $this->computeAndSave($resume, $job);
        }

        Log::info("CandidateScoringService: Scored resume#{$resume->id} against {$jobs->count()} jobs.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. عند نشر وظيفة جديدة: احسب نسبة كل المرشحين مع هذه الوظيفة
    // ──────────────────────────────────────────────────────────────────────────
    public function scoreAllCandidatesForJob(Job $job): void
    {
        // جلب آخر resume لكل مرشح (job seeker)
        $resumes = UserResume::with('user')
            ->whereHas('user', fn($q) => $q->where('role', 'job'))
            ->latest()
            ->get()
            ->unique('user_id')
            ->values();

        if ($resumes->isEmpty()) {
            Log::info("CandidateScoringService: No candidates to score for job#{$job->id}");
            return;
        }

        foreach ($resumes as $resume) {
            $this->computeAndSave($resume, $job);
        }

        Log::info("CandidateScoringService: Scored job#{$job->id} against {$resumes->count()} candidates.");
    }

    // ──────────────────────────────────────────────────────────────────────────
    // الدالة الأساسية: تحسب النسبة وتحفظها (أو تحدّثها إن وُجدت)
    // ──────────────────────────────────────────────────────────────────────────
    private function computeAndSave(UserResume $resume, Job $job): void
    {
        if (!$resume->user) {
            return;
        }

        try {
            // استدعاء Gemini AI
            $result = $this->ai->scoreOneCandidate(
                jobTitle:           $job->title,
                jobDescription:     $job->description,
                jobRequirements:    $job->requirements,
                candidateSkills:    $resume->current_skills ?? [],
                candidateTargetJob: $resume->target_job ?? '',
                candidateCvText:    $resume->original_text ?? ''
            );

            $score         = $result['match_score'];
            $justification = $result['justification'];

            // إذا فشل الـ AI نستخدم الحساب المحلي الاحتياطي
            if ($score === -1) {
                $score         = $this->localFallbackScore($resume, $job);
                $justification = 'Calculated locally (AI unavailable)';
                Log::warning("CandidateScoringService: AI failed for resume#{$resume->id}/job#{$job->id}, used local fallback score={$score}");
            }

            // حفظ أو تحديث النسبة
            JobCandidateScore::updateOrCreate(
                [
                    'job_id'             => $job->id,
                    'candidate_user_id'  => $resume->user_id,
                ],
                [
                    'match_score'   => $score,
                    'justification' => $justification,
                ]
            );

            Log::info("CandidateScoringService: resume#{$resume->id} ↔ job#{$job->id} = {$score}%");

        } catch (\Exception $e) {
            Log::error("CandidateScoringService: Exception for resume#{$resume->id}/job#{$job->id}: " . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // حساب محلي احتياطي (يُستخدم فقط إذا فشل Gemini)
    //
    // الأوزان:
    //   تطابق المهارات   50 نقطة
    //   تطابق المسمى     25 نقطة
    //   جودة الـ profile 25 نقطة
    // ──────────────────────────────────────────────────────────────────────────
    private function localFallbackScore(UserResume $resume, Job $job): int
    {
        $jobText         = strtolower($job->requirements . ' ' . $job->description . ' ' . $job->title);
        $candidateSkills = array_map('strtolower', $resume->current_skills ?? []);
        $totalSkills     = max(count($candidateSkills), 1);

        // ── 1. تطابق المهارات (50 نقطة) ──────────────────────────────────────
        $matched = 0;
        foreach ($candidateSkills as $skill) {
            if (str_contains($jobText, $skill)) {
                $matched++;
            } else {
                foreach (explode(' ', $skill) as $part) {
                    if (strlen($part) > 2 && str_contains($jobText, $part)) {
                        $matched += 0.5;
                        break;
                    }
                }
            }
        }
        $skillScore = (int) round(($matched / $totalSkills) * 50);

        // ── 2. تطابق المسمى الوظيفي (25 نقطة) ───────────────────────────────
        $candidateRole = strtolower(trim($resume->target_job ?? ''));
        $jobTitle      = strtolower(trim($job->title));
        $titleScore    = 0;

        if ($candidateRole !== '' && $jobTitle !== '') {
            if ($candidateRole === $jobTitle) {
                $titleScore = 25;
            } elseif (str_contains($jobTitle, $candidateRole) || str_contains($candidateRole, $jobTitle)) {
                $titleScore = 18;
            } else {
                $common = array_intersect(
                    explode(' ', $candidateRole),
                    explode(' ', $jobTitle)
                );
                if ($common) {
                    $titleScore = (int) round(
                        (count($common) / max(count(explode(' ', $candidateRole)), count(explode(' ', $jobTitle)))) * 15
                    );
                }
            }
        }

        // ── 3. جودة الـ profile (25 نقطة) ────────────────────────────────────
        $profileScore = 0;
        if (!empty($resume->original_text)) {
            $profileScore += 15;
            if (strlen($resume->original_text) > 500) {
                $profileScore += 5;
            }
        }
        if (count($candidateSkills) >= 5) {
            $profileScore += 5;
        }

        return min(max($skillScore + $titleScore + $profileScore, 0), 100);
    }
}
