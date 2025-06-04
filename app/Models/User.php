<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;


    protected $fillable = [
        'user_image', 'name', 'password', 'address', 'city', 'state', 'country',
        'inserted_date', 'inserted_time', 'email', 'role', 'mobile', 'mobile_otp',
        'verify_mobile', 'status', 'locality', 'unique_id', 'rating', 'verify_email',
        'gender', 'email_otp', 'valid_upto', 'permission', 'views', 'pin_code'
    ];


    public $timestamps = false;

    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    protected $primaryKey = 'user_id';
    public function getJWTIdentifier()
    {
        return $this->user_id;
    }


    public function getJWTCustomClaims()
    {
        return [];
    }

        // public function country()
        // {
        //     return $this->hasOne(Country::class, 'country_id', 'country');
        // }
        // public function state()
        // {
        //     return $this->hasOne(State::class, 'state_subdivision_id', 'state');
        // }
        // public function city()
        // {
        //     return $this->hasOne(City::class, 'cities_id', 'city');
        // }

}
