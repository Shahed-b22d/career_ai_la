<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRoadmap extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'target_job',
        'roadmap_text',
        'missing_skills',
        'suggested_courses',
        'completed_skills',
        'is_active',
    ];

    protected $casts = [
        'missing_skills' => 'array',
        'suggested_courses' => 'array',
        'completed_skills' => 'array',
        'is_active' => 'boolean',
    ];
}
