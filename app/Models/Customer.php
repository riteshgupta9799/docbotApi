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
        'customer_unique_id', 'customer_id', 'customer_profile', 'name', 'username', 'password',
         'email', 'machine_id',
        'inserted_date', 'inserted_time',
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



}
