<?php
namespace App\Filament\Resources\ReviewProcurementResource\Pages;

use App\Filament\Resources\ReviewProcurementResource;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use App\Models\Approval;
use App\Models\DefaultApprover;

class ReviewProcurementResourceView extends ViewRecord
{
    protected static string $resource = ReviewProcurementResource::class;

    public function getTitle(): string
    {
        return "Procurement No. " . ($this->record->procurement_id ?? 'N/A');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $record = $this->record;
        $employeeId = Auth::user()->employee->id ?? null;

        if (!$employeeId) {
            abort(403, 'Unauthorized: No employee associated with this user.');
        }

        // Define modules
        $modules = [
            'ppmp' => 'Project Procurement Management Plan',
            'purchase_request' => 'Purchase Request',
            'request_for_quotation' => 'Request for Quotation',
            'abstract_of_quotation' => 'Abstract of Quotation',
            'bac_resolution_recommending_award' => 'BAC Resolution Recommending Award',
            'purchase_order' => 'Purchase Order',
        ];

        // Map module keys to their approver page routes
        $approverPages = [
            'ppmp' => 'approver-view-ppmp',
            'purchase_request' => 'approver-view-pr',
            'request_for_quotation' => 'approver-view-rfq',
            'abstract_of_quotation' => 'approver-view-aoq',
            'bac_resolution_recommending_award' => 'approver-view-bac',
            'purchase_order' => 'approver-view-po',
        ];

        $sections = [];

        // Procurement Details
        $procurementTypeMap = [
            'small_value_procurement' => 'Small Value Procurement',
            'public_bidding' => 'Public Bidding',
        ];
        $procurementType = $procurementTypeMap[$record->procurement_type]
            ?? ucwords(str_replace('_', ' ', $record->procurement_type));

        $prChild = $record->children()->where('module', 'purchase_request')->first();
        $requestedBy = $prChild && $prChild->requester
            ? $prChild->requester->firstname . ' ' .
              ($prChild->requester->middlename ? $prChild->requester->middlename . ' ' : '') .
              $prChild->requester->lastname
            : 'N/A';

        $sections[] = Section::make('Procurement Details')
            ->schema([
                TextEntry::make('procurement_id')->label('Procurement ID')->state($record->procurement_id ?? 'N/A'),
                TextEntry::make('title')->label('Title')->state($record->title ?? 'N/A'),
                TextEntry::make('procurement_type')->label('Procurement Type')->state($procurementType),
                TextEntry::make('created_at')->label('Date Created')->date('Y-m-d')->state($record->created_at),
                TextEntry::make('creator.name')->label('Created By')->state($record->creator->name ?? 'N/A'),
                TextEntry::make('requested_by')->label('Requested By')->state($requestedBy),
            ])
            ->columns(3)
            ->collapsible();

        // Module Sections
        foreach ($modules as $moduleKey => $label) {
            $child = $record->children()->where('module', $moduleKey)->first();
            $isAssigned = $employeeId && $child && $child->approvals()
                ->where('employee_id', $employeeId)
                ->where('status', 'Pending')
                ->exists();

            // Determine module status
            $status = 'Not Started';
            if ($child) {
                if ($moduleKey === 'ppmp') {
                    $hasDocument = $child->documents()->where('module', 'ppmp')->exists();
                    $status = $hasDocument ? 'Uploaded' : 'Not Started';
                } else {
                    if ($child->status === 'Locked') {
                        $status = 'Locked';
                    } elseif ($child->approvals->isEmpty()) {
                        $status = 'Not Started';
                    } elseif ($child->approvals->contains('status', 'Rejected')) {
                        $status = 'Rejected';
                    } elseif ($child->approvals->every(fn($approval) => $approval->status === 'Approved')) {
                        $status = 'Approved';
                    } else {
                        $status = 'Pending';
                    }
                }
            }

            // Determine link
            $url = $child && $approverPages[$moduleKey]
                ? static::getResource()::getUrl($approverPages[$moduleKey], ['record' => $child->parent_id])
                : '#';

            // Build schema without assignment for PPMP
            $schema = [
                TextEntry::make('doc_no')->label($label . ' No.')->state($child ? $child->procurement_id : 'N/A'),
                TextEntry::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(match ($status) {
                        'Approved' => 'success',
                        'Rejected' => 'danger',
                        'Pending' => 'warning',
                        'Not Started' => 'gray',
                        'Locked' => 'danger',
                        'Uploaded' => 'info',
                        default => 'gray',
                    })
                    ->state($status),
                TextEntry::make('document')
                    ->label('Document')
                    ->html()
                    ->state(fn() => $url === '#'
                        ? 'Not Available'
                        : "<a href='{$url}' class='text-blue-600 underline'>View</a>"),
            ];

            // Add assignment field only if NOT PPMP
            if ($moduleKey !== 'ppmp') {
                $assignmentText = $isAssigned ? 'Assigned to you' : 'Read-only';
                $schema[] = TextEntry::make('assignment')
                    ->label('Assignment')
                    ->state($assignmentText)
                    ->badge()
                    ->color($isAssigned ? 'danger' : 'gray');
            }

            $sections[] = Section::make($label)
                ->schema($schema)
                ->columns(4)
                ->extraAttributes($isAssigned
                    ? ['class' => 'bg-red-100 dark:bg-red-900']
                    : [])
                ->collapsible();
        }

        return $infolist->schema($sections);
    }

    // Fixed: removed all approve/reject header buttons
    protected function getHeaderActions(): array
    {
        return [];
    }
}
