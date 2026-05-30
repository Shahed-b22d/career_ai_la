<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * جدول يخزّن نسبة التوافق المحسوبة بالذكاء الاصطناعي
     * بين كل مرشح (job seeker) وكل وظيفة منشورة.
     *
     * يُملأ تلقائياً في حالتين:
     *  1. عند رفع/تحديث CV المرشح  → نحسب نسبته مع كل الوظائف النشطة
     *  2. عند نشر وظيفة جديدة      → نحسب نسبتها مع كل المرشحين الموجودين
     */
    public function up(): void
    {
        Schema::create('job_candidate_scores', function (Blueprint $table) {
            $table->id();

            // الوظيفة
            $table->foreignId('job_id')
                ->constrained('jobs')
                ->onDelete('cascade');

            // المرشح (user بدور job)
            $table->foreignId('candidate_user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // نسبة التوافق 0-100 محسوبة بالذكاء الاصطناعي
            $table->unsignedTinyInteger('match_score')->default(0);

            // مبرر قصير من الـ AI
            $table->string('justification')->nullable();

            $table->timestamps();

            // كل مرشح مع كل وظيفة مرة واحدة فقط
            $table->unique(['job_id', 'candidate_user_id']);

            // فهرس للبحث السريع
            $table->index('candidate_user_id');
            $table->index('job_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_candidate_scores');
    }
};
