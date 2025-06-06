<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;


class Paitents extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $primaryKey = 'patient_id';

    protected $fillable = [
        'patient_name', 'gender', 'patient_email', 'patient_mobile', 'dob', 'address',
         'email_otp', 'mobile_otp','age',
        'inserted_date', 'inserted_time',
    ];


    public $timestamps = false;

    protected $hidden = [
        'password',
        'patient_id'
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
        return $this->patient_id;
    }


    public function getJWTCustomClaims()
    {
        return [];
    }



}
