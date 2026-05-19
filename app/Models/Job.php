<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'job_type',
        'location',
        'salary',
        'description',
        'requirements',
        'is_paid',
        'payment_session_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
