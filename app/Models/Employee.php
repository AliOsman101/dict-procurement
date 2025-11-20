<?php
namespace App\Models;

use App\Models\Hr\Dtr;
use App\Models\Hr\LeaveUsage;
use App\Models\Hr\LeaveAccrual;
use App\Models\Hr\LeaveBalance;
use App\Models\Hr\CocApplication;
use App\Models\Hr\OvertimeOrder;
use App\Models\Hr\OvertimeOrderDetail;
use App\Models\Travel\TravelOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $appends = ['full_name'];

    public $incrementing = true;

    protected $fillable = [
        'id',
        'firstname',
        'middlename',
        'lastname',
        'employee_no',
        'position_id',
        'designation',
        'division_id',
        'entrance_to_duty',
        'gsis_no',
        'birthday',
        'gender',
        'civil_status',
        'mobile',
        'employment_status',
        'tin',
        'is_active',
        'project_id',
        'region',
        'office',
        'user_id',
        'supervisor_id',
        'device_serial_no',
    ];

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where('lastname', 'like', "%{$search}%")
                  ->orWhere('firstname', 'like', "%{$search}%")
                  ->orWhere('middlename', 'like', "%{$search}%");
        })->when($filters['trashed'] ?? null, function ($query, $trashed) {
            if ($trashed === 'with') {
                $query->withTrashed();
            } elseif ($trashed === 'only') {
                $query->onlyTrashed();
            }
        });
    }

    public function getFullNameAttribute()
    {
        $middlename = $this->middlename ? " {$this->middlename} " : ' ';
        return ucwords(strtolower("{$this->firstname}{$middlename}{$this->lastname}"));
    }

    public function position()
    {
        return $this->belongsTo('App\Models\Setup\Position');
    }

    public function division()
    {
        return $this->belongsTo('App\Models\Setup\Division');
    }

    public function project()
    {
        return $this->belongsTo('App\Models\Setup\Project');
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User');
    }

    public function certificate()
    {
        return $this->hasOne(EmployeeCertificate::class);
    }
}