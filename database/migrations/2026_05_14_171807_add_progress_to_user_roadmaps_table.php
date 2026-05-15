<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_roadmaps', function (Blueprint $table) {
            $table->json('completed_skills')->nullable()->after('suggested_courses');
            $table->boolean('is_active')->default(true)->after('completed_skills');
        });
    }

    public function down(): void
    {
        Schema::table('user_roadmaps', function (Blueprint $table) {
            $table->dropColumn(['completed_skills', 'is_active']);
        });
    }
};
