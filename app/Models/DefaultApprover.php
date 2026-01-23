<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Procurement;
use App\Models\Approval;

class DefaultApprover extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'sequence',
        'module',
        'designation',
        'office_section',
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            if ($model->module === 'request_for_quotation') {
                $model->sequence = 1; // Always sequence 1 for RFQ
            }
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvals()
    {
        return $this->hasMany(Approval::class, 'employee_id', 'employee_id')
                    ->where('module', $this->module);
    }

    public static function syncToApprovals($module = 'purchase_request')
    {
        $procurements = Procurement::where('module', $module)
                                   ->where('status', '!=', 'Locked')
                                   ->get();

        foreach ($procurements as $procurement) {
            // Check if the previous module is fully approved
            if (!static::isPreviousModuleApproved($procurement, $module)) {
                continue; // Skip syncing approvals if previous module is not fully approved
            }

            Approval::where('procurement_id', $procurement->id)
                    ->where('module', $module)
                    ->delete();

            $defaultApprovers = self::where('module', $module)
                                   ->orderBy('sequence')
                                   ->get();

            if ($module === 'request_for_quotation') {
                $approver = $defaultApprovers->firstWhere('office_section', $procurement->office_section);
                if ($approver) {
                    Approval::create([
                        'procurement_id' => $procurement->id,
                        'module' => $module,
                        'employee_id' => $approver->employee_id,
                        'sequence' => 1,
                        'designation' => $approver->designation,
                        'status' => 'Pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } else {
                foreach ($defaultApprovers as $approver) {
                    Approval::create([
                        'procurement_id' => $procurement->id,
                        'module' => $module,
                        'employee_id' => $approver->employee_id,
                        'sequence' => $approver->sequence,
                        'designation' => $approver->designation,
                        'status' => 'Pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public static function isPreviousModuleApproved(Procurement $procurement, string $currentModule): bool
    {
        $moduleOrder = [
            'ppmp',
            'purchase_request',
            'request_for_quotation',
            'abstract_of_quotation',
            'minutes_of_opening',
            'bac_resolution_recommending_award',
            'purchase_order'
        ];

        $currentIndex = array_search($currentModule, $moduleOrder);
        if ($currentIndex === 0 || $currentIndex === false) {
            return true; // No previous module for ppmp or invalid module
        }

        $previousModule = $moduleOrder[$currentIndex - 1];
        $parent = $procurement->parent ?? $procurement;
        $previousProcurement = Procurement::where('parent_id', $parent->id)
            ->where('module', $previousModule)
            ->first();

        if (!$previousProcurement) {
            return false; // Previous module does not exist
        }

        $approvals = Approval::where('procurement_id', $previousProcurement->id)
            ->where('module', $previousModule)
            ->get();

        return $approvals->isNotEmpty() && $approvals->every(fn ($approval) => $approval->status === 'Approved');
    }
}