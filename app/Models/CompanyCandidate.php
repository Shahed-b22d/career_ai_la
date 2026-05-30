<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_user_id',
        'candidate_user_id',
        'match_score',
    ];

    public function company()
    {
        return $this->belongsTo(User::class, 'company_user_id');
    }

    public function candidate()
    {
        return $this->belongsTo(User::class, 'candidate_user_id');
    }
}
