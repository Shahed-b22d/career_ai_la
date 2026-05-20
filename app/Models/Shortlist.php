<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shortlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_user_id',
        'candidate_name',
        'candidate_email',
        'candidate_phone',
        'candidate_governorate',
        'candidate_role',
        'match_score',
    ];

    public function company()
    {
        return $this->belongsTo(User::class, 'company_user_id');
    }
}
