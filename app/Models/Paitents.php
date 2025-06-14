<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;


class Paitents extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $table = 'paitents'; // ✅ explicitly define table
    protected $primaryKey = 'paitent_id'; // ✅ corrected to match DB

    protected $fillable = [
        'paitent_name', 'gender', 'paitent_email', 'paitent_mobile', 'dob', 'address',
        'email_otp', 'mobile_otp','age',
        'inserted_date', 'inserted_time','customer_id',
        'paitent_unique_id'
    ];

    public $timestamps = false;

    protected $hidden = [
        'password',
        'paitent_id'
    ];

    protected $keyType = 'int';
    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->paitent_id;
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}

