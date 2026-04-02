<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserResume extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'target_job',
        'original_text',
        'current_skills',
        'missing_skills',
    ];

    protected $casts = [
        'current_skills' => 'array',
        'missing_skills' => 'array',
    ];
}
