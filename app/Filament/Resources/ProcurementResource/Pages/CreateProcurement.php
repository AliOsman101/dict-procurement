<?php
namespace App\Filament\Resources\ProcurementResource\Pages;

use App\Filament\Resources\ProcurementResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\ActivityLog;


class CreateProcurement extends CreateRecord
{
    protected static string $resource = ProcurementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $userId = Auth::id();
        $year = Carbon::now()->format('Y');
        $month = Carbon::now()->format('m');

        $procurementCount = \App\Models\Procurement::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereNull('module')
            ->count() + 1;

        $procurementId = sprintf('PROC-%s-%s-%03d', $year, $month, $procurementCount);

        // Check if procurement_id already exists
        while (\App\Models\Procurement::where('procurement_id', $procurementId)->exists()) {
            $procurementCount++;
            $procurementId = sprintf('PROC-%s-%s-%03d', $year, $month, $procurementCount);
        }

        $data['procurement_id'] = $procurementId;
        $data['module'] = null;
        $data['status'] = 'Pending';
        $data['created_by'] = $userId;
        $data['requested_by'] = null;

        $this->procurementCount = $procurementCount;

        return $data;
    }
protected function afterCreate(): void
{
    if (isset($this->data['employees']) && is_array($this->data['employees'])) {
        $this->record->employees()->sync($this->data['employees']);
    }

    // ✅ Send Gmail notification to all assigned employees
    foreach ($this->record->employees as $employee) {
        if ($employee->user && $employee->user->email) {
            try {
                \Mail::to($employee->user->email)
                    ->send(new \App\Mail\ProcurementEmployeeAssignedMail($this->record));
            } catch (\Exception $e) {
                \Log::error("Failed to send Procurement email to {$employee->user->email}: {$e->getMessage()}");
            }
        }
    }

    $year = \Carbon\Carbon::now()->format('Y');
    $month = \Carbon\Carbon::now()->format('m');

    $modules = [
        'ppmp' => 'PPMP',
        'purchase_request' => 'PR',
        'request_for_quotation' => 'RFQ',
        'abstract_of_quotation' => 'AOQ',
        'bac_resolution_recommending_award' => 'BAC',
        'purchase_order' => 'PO',
    ];

    foreach ($modules as $module => $prefix) {
        $procurementCount = \App\Models\Procurement::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('procurement_id', 'like', "{$prefix}-{$year}-{$month}-%")
            ->count() + 1;

        $procurementId = sprintf('%s-%s-%s-%03d', $prefix, $year, $month, $procurementCount);

        $child = \App\Models\Procurement::create([
            'module' => $module,
            'procurement_id' => $procurementId,
            'title' => $this->record->title,
            'status' => 'Pending',
            'parent_id' => $this->record->id,
            'fund_cluster_id' => $this->record->fund_cluster_id,
            'category_id' => $this->record->category_id,
            'procurement_type' => $this->record->procurement_type,
            'office_section' => $this->record->office_section,
            'created_by' => \Auth::id(),
            'requested_by' => null,
        ]);

        $employeeIds = $this->record->employees()->pluck('employee_id')->toArray();
        $child->employees()->sync($employeeIds);

        $defaultApprovers = \App\Models\DefaultApprover::where('module', $module)
            ->orderBy('sequence')
            ->distinct('employee_id')
            ->get();

        if ($module === 'request_for_quotation') {
        $sectionApprovers = \App\Models\DefaultApprover::where('module', 'request_for_quotation')
            ->where('office_section', $this->record->office_section)
            ->orderBy('sequence')
            ->get();

        foreach ($sectionApprovers as $approver) {
            \App\Models\Approval::updateOrCreate(
                [
                    'procurement_id' => $child->id,
                    'employee_id' => $approver->employee_id,
                    'module' => $module,
                ],
                [
                    'sequence' => 1,
                    'designation' => $approver->designation,
                    'status' => 'Pending',
                ]
            );
        }
    } else {
            foreach ($defaultApprovers as $approver) {
                try {
                    \App\Models\Approval::firstOrCreate(
                        [
                            'procurement_id' => $child->id,
                            'employee_id' => $approver->employee_id,
                            'module' => $module,
                        ],
                        [
                            'sequence' => $approver->sequence,
                            'designation' => $approver->designation,
                            'status' => 'Pending',
                        ]
                    );
                } catch (\Exception $e) {
                    \Log::error("Approval creation failed for procurement_id={$child->id}, module={$module}: {$e->getMessage()}");
                }
            }
        }
    }

    // ✅ Logging: only once per created procurement
    static $logged = [];
    if (!in_array($this->record->procurement_id, $logged)) {
        $logged[] = $this->record->procurement_id;

        $user = \Auth::user();

        \App\Models\ActivityLog::create([
            'user_id' => $user->id ?? null,
            'role' => $user && $user->roles ? $user->roles->pluck('name')->implode(', ') : 'Unknown',
            'action' => 'Created Procurement',
            'details' => $this->record->procurement_id . ' (' . $this->record->title . ')',
            'ip_address' => request()->ip(),
        ]);
    }
}
}