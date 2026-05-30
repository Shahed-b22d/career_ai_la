<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobCandidateScore extends Model
{
    protected $fillable = [
        'job_id',
        'candidate_user_id',
        'match_score',
        'justification',
    ];

    protected $casts = [
        'match_score' => 'integer',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function candidate()
    {
        return $this->belongsTo(User::class, 'candidate_user_id');
    }
}
