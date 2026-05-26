<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'business_type',
        'commercial_register_path',
        'description',
        'verification_status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
