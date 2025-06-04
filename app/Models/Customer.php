<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Customer extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $primaryKey = 'customer_id';

    protected $fillable = [
        'unique_id', 'first_name', 'last_name', 'image', 'mobile', 'mobile_otp',
        'verify_mobile', 'email', 'email_otp', 'verify_email', 'gender', 'password',
        'inserted_date', 'inserted_time', 'status', 'timestamp', 'country', 'state',
        'latitude', 'pincode', 'longitude', 'timezone', 'city'
    ];


    public $timestamps = false;

    protected $hidden = [
        'password',
        'customer_id'
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
        return $this->customer_id;
    }


    public function getJWTCustomClaims()
    {
        return [];
    }

    public function country()
    {
        return $this->hasOne(Country::class, 'country_id', 'country');
    }
    public function state()
    {
        return $this->hasOne(State::class, 'state_subdivision_id', 'state');
    }
    public function city()
    {
        return $this->hasOne(City::class, 'cities_id', 'city');
    }
    public function art()
    {
        return $this->hasMany(Art::class, 'customer_id', 'customer_id');
    }

    public function artistArtStories()
    {
        return $this->hasMany(ArtistArtStories::class, 'customer_id', 'customer_id');
    }
    public function wishlist()
    {
        return $this->hasMany(Wishlists::class, 'customer_id', 'customer_id');
    }
    public function cart()
    {
        return $this->hasMany(ArtCart::class, 'customer_id', 'customer_id');
    }

}
