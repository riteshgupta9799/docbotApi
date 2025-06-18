<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientReport extends Model
{
    protected $table = 'patient_reports';

    protected $primaryKey = 'report_id';

    public $timestamps = false; // no created_at / updated_at in table

    protected $fillable = [
        'machine_id',
        'patient_id',
        'inserted_time',
        'inserted_date',
        'result_key',
        'result_value',
        'test_name',
        'que_id'
    ];

    public function queue()
    {
        return $this->belongsTo(TestQueue::class, 'que_id');
    }
}
