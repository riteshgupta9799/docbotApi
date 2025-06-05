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
        'user_id',
        'user_unique_id',
        'user_profile',
        'name',
        'email',
        'mobile',
        'password',
        'country',
        'state',
        'city',
        'address',
        'zip_code',
        'fcm_token',
        'status',
        'inserted_date',
        'inserted_time',

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
