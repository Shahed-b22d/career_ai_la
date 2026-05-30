<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shortlists')) {
            Schema::rename('shortlists', 'company_candidates');
        }

        if (! Schema::hasColumn('company_candidates', 'candidate_user_id')) {
            Schema::table('company_candidates', function (Blueprint $table) {
                $table->foreignId('candidate_user_id')
                    ->after('company_user_id')
                    ->constrained('users')
                    ->onDelete('cascade');
            });
        }

        $existingIndexes = collect(DB::select('SHOW INDEX FROM company_candidates'))
            ->pluck('Key_name')
            ->unique();

        // FK on company_user_id currently uses the legacy unique index prefix
        if ($existingIndexes->contains('shortlists_company_user_id_candidate_email_unique')) {
            Schema::table('company_candidates', function (Blueprint $table) {
                $table->index('company_user_id', 'company_candidates_company_user_id_index');
            });

            Schema::table('company_candidates', function (Blueprint $table) {
                $table->dropUnique('shortlists_company_user_id_candidate_email_unique');
            });
        }

        $legacyColumns = array_values(array_filter([
            'candidate_name',
            'candidate_email',
            'candidate_phone',
            'candidate_governorate',
            'candidate_role',
        ], fn (string $column) => Schema::hasColumn('company_candidates', $column)));

        if ($legacyColumns !== []) {
            Schema::table('company_candidates', function (Blueprint $table) use ($legacyColumns) {
                $table->dropColumn($legacyColumns);
            });
        }

        $foreignKeys = collect(DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'company_candidates'
              AND COLUMN_NAME = 'candidate_user_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        "))->pluck('CONSTRAINT_NAME');

        if ($foreignKeys->isEmpty() && Schema::hasColumn('company_candidates', 'candidate_user_id')) {
            Schema::table('company_candidates', function (Blueprint $table) {
                $table->foreign('candidate_user_id', 'company_candidates_candidate_user_id_foreign')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            });
        }

        $existingIndexes = collect(DB::select('SHOW INDEX FROM company_candidates'))
            ->pluck('Key_name')
            ->unique();

        if (! $existingIndexes->contains('company_candidates_company_user_id_candidate_user_id_unique')) {
            Schema::table('company_candidates', function (Blueprint $table) {
                $table->unique(
                    ['company_user_id', 'candidate_user_id'],
                    'company_candidates_company_user_id_candidate_user_id_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('company_candidates', function (Blueprint $table) {
            $table->dropUnique('company_candidates_company_user_id_candidate_user_id_unique');
            $table->dropForeign('company_candidates_candidate_user_id_foreign');
            $table->dropColumn('candidate_user_id');

            $table->string('candidate_name');
            $table->string('candidate_email');
            $table->string('candidate_phone')->nullable();
            $table->string('candidate_governorate')->nullable();
            $table->string('candidate_role')->nullable();

            $table->unique(['company_user_id', 'candidate_email'], 'shortlists_company_user_id_candidate_email_unique');
            $table->dropIndex('company_candidates_company_user_id_index');
        });

        Schema::rename('company_candidates', 'shortlists');
    }
};
