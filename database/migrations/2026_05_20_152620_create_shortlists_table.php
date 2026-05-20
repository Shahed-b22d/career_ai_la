<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shortlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_user_id')->constrained('users')->onDelete('cascade');
            $table->string('candidate_name');
            $table->string('candidate_email');
            $table->string('candidate_phone')->nullable();
            $table->string('candidate_governorate')->nullable();
            $table->string('candidate_role')->nullable();
            $table->string('match_score')->nullable();
            $table->timestamps();

            // منع تكرار نفس المرشح من نفس الشركة
            $table->unique(['company_user_id', 'candidate_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shortlists');
    }
};
