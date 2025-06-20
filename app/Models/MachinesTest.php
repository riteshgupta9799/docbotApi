<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachinesTest extends Model
{
    protected $table = 'machines_tests';
    public $timestamps = false;
    protected $primaryKey = 'machines_tests_id';
}
