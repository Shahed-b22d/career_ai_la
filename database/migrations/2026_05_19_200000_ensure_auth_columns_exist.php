<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['job', 'company'])->default('job')->after('email');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'business_type')) {
                $table->string('business_type')->nullable()->after('phone');
            }
        });

        // Ensure job_seekers table exists
        if (!Schema::hasTable('job_seekers')) {
            Schema::create('job_seekers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('phone')->nullable();
                $table->timestamps();
            });
        }

        // Ensure companies table exists with all columns
        if (!Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('phone')->nullable();
                $table->string('business_type')->nullable();
                $table->string('commercial_register_path')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('companies', function (Blueprint $table) {
                if (!Schema::hasColumn('companies', 'commercial_register_path')) {
                    $table->string('commercial_register_path')->nullable()->after('business_type');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'business_type']);
        });
    }
};
