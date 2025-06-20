<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    protected $table = 'paitents';
    protected $primaryKey = 'paitent_id';
    public $timestamps = false;

    protected $fillable = [
        'paitent_unique_id',
        'customer_id',
        'paitent_name',
        'gender',
        'paitent_email',
        'paitent_mobile',
        'dob',
        'address',
        'email_otp',
        'mobile_otp',
        'age',
        'inserted_date',
        'inserted_time'
    ];

    /**
     * Belongs to Customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}
