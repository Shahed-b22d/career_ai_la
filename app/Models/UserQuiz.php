<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserQuiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tested_skills',
        'quiz_data',
        'score',
    ];

    protected $casts = [
        'tested_skills' => 'array',
        'quiz_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
