<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('job', 'company', 'admin') NOT NULL DEFAULT 'job'");

        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'verification_status')) {
                $table->enum('verification_status', ['pending', 'approved', 'rejected'])
                    ->default('pending')
                    ->after('commercial_register_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'verification_status')) {
                $table->dropColumn('verification_status');
            }
        });

        DB::statement("ALTER TABLE users MODIFY role ENUM('job', 'company') NOT NULL DEFAULT 'job'");
    }
};
