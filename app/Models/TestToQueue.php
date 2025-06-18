<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestToQueue extends Model
{
    protected $table = 'test_to_queue';
    protected $fillable = [
        'queue_id', 'test_name', 'test_key', 'test_value', 'inserted_time', 'inserted_date'
    ];

    public function queue()
    {
        return $this->belongsTo(TestQueue::class, 'queue_id');
    }
}
