<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestQueue extends Model
{
    protected $table = 'test_queue';
    protected $fillable = [
        'machine_id', 'patient_id', 'inserted_time', 'inserted_date'
    ];

    public function tests()
    {
        return $this->hasMany(TestToQueue::class, 'queue_id');
    }
}
