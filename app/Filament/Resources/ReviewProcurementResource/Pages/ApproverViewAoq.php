<?php

namespace App\Filament\Resources\ReviewProcurementResource\Pages;

use App\Filament\Resources\ReviewProcurementResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Grid;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use App\Models\Procurement;
use App\Models\RfqResponse;
use App\Models\AoqEvaluation;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Helpers\ActivityLogger;
use Illuminate\Support\Facades\Mail;
use App\Mail\WinningSupplierMail;
use App\Mail\LosingSupplierMail;
use App\Models\Supplier;

class ApproverViewAoq extends ViewRecord
{
    protected static string $resource = ReviewProcurementResource::class;

    public function mount($record): void
    {
        $child = Procurement::where('parent_id', $record)
                            ->where('module', 'abstract_of_quotation')
                            ->firstOrFail();
        $this->record = $child;
        $this->record->refresh();
        $this->loadCustomCollections();
    }

    protected function loadCustomCollections(): void
    {
        // Load the parent procurement
        $parent = Procurement::find($this->record->parent_id);

        // Load the purchase_request and its procurementItems
        $pr = Procurement::where('parent_id', $this->record->parent_id)
            ->where('module', 'purchase_request')
            ->with([
                'procurementItems' => function ($query) {
                    $query->orderBy('sort');
                },
                'requester:id,firstname,lastname'
            ])
            ->first();

        // Load the request_for_quotation for delivery period and deadline
        $rfq = Procurement::where('parent_id', $this->record->parent_id)
            ->where('module', 'request_for_quotation')
            ->first();

        // Load rfqResponses with related data
        $rfqResponses = $rfq ? RfqResponse::where('procurement_id', $rfq->id)
            ->with([
                'supplier:id,business_name',
                'quotes.procurementItem:id,procurement_id,item_description,quantity,unit,unit_cost,total_cost,sort',
                'aoqEvaluations' => function ($query) {
                    $query->where('procurement_id', $this->record->id);
                }
            ])
            ->get() : collect();

        // Set procurementItems, requester, parent, rfq, and rfqResponses on the record
        $this->record->procurementItems = $pr ? $pr->procurementItems : collect();
        $this->record->requester = $pr ? $pr->requester : null;
        $this->record->rfq = $rfq;
        $this->record->rfqResponses = $rfqResponses;
        $this->record->setRelation('parent', $parent);

        // Load other necessary relationships
        $this->record->load([
            'approvals' => function ($query) {
                $query->where('module', 'abstract_of_quotation')
                    ->with('employee:id,firstname,lastname')
                    ->orderBy('sequence');
            },
            'fundCluster:id,name',
            'category:id,name',
        ]);
    }

    public function getTitle(): string
    {
        return "AOQ No. " . ($this->record->procurement_id ?? 'N/A');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Abstract of Quotation Details')
                    ->schema([
                        TextEntry::make('procurement_id')
                            ->label('AOQ No.'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Pending' => 'warning',
                                'Approved' => 'success',
                                'Locked' => 'danger',
                                'Rejected' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('evaluation_status')
                            ->label('Evaluation Status')
                            ->badge()
                            ->color(function ($record): string {
                                $totalDocs = $record->rfqResponses->sum(function($r) {
                                    $docCount = is_array($r->documents) ? count($r->documents) : 0;
                                    return $docCount + (!empty($r->rfq_document) ? 1 : 0);
                                });
                                
                                $evaluatedDocs = AoqEvaluation::where('procurement_id', $record->id)
                                    ->where(function($query) {
                                        $query->where('requirement', 'not like', 'quote_%')
                                              ->orWhere('requirement', 'rfq_document');
                                    })
                                    ->count();
                                
                                if ($totalDocs === 0) return 'gray';
                                if ($evaluatedDocs >= $totalDocs) return 'success';
                                return 'warning';
                            })
                            ->getStateUsing(function ($record) {
                                $totalDocs = $record->rfqResponses->sum(function($r) {
                                    $docCount = is_array($r->documents) ? count($r->documents) : 0;
                                    return $docCount + (!empty($r->rfq_document) ? 1 : 0);
                                });
                                
                                $evaluatedDocs = AoqEvaluation::where('procurement_id', $record->id)
                                    ->where(function($query) {
                                        $query->where('requirement', 'not like', 'quote_%')
                                              ->orWhere('requirement', 'rfq_document');
                                    })
                                    ->count();
                                    
                                if ($totalDocs === 0) return 'No Documents';
                                return $evaluatedDocs >= $totalDocs ? 'Complete' : "Partial ({$evaluatedDocs}/{$totalDocs})";
                            }),
                        TextEntry::make('created_at')
                            ->label('Date Filed')
                            ->date('Y-m-d'),
                        TextEntry::make('title'),
                        TextEntry::make('requested_by')
                            ->label('Requested By')
                            ->getStateUsing(fn ($record) => $record->requester?->full_name ?? 'Not set'),
                        TextEntry::make('procurement_type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                            ->color(fn (string $state) => $state === 'small_value_procurement' ? 'info' : 'primary'),
                        TextEntry::make('fundCluster.name')
                            ->label('Fund Cluster'),
                        TextEntry::make('category.name')
                            ->label('Category'),
                        TextEntry::make('grand_total')
                            ->label('Approved Budget for Contract (ABC)')
                            ->money('PHP')
                            ->weight('bold')
                            ->getStateUsing(fn ($record) => $record->procurementItems->sum('total_cost')),
                        TextEntry::make('delivery_period_display')
                            ->label('Delivery Period')
                            ->getStateUsing(function ($record) {
                                if ($record->rfq && $record->rfq->delivery_mode === 'days' && $record->rfq->delivery_value) {
                                    return "Within {$record->rfq->delivery_value} calendar days upon receipt of Purchase Order";
                                }
                                if ($record->rfq && $record->rfq->delivery_mode === 'date' && $record->rfq->delivery_value) {
                                    return Carbon::parse($record->rfq->delivery_value)->format('F j, Y');
                                }
                                return 'Not set';
                            }),
                        TextEntry::make('deadline_date')
                            ->label('Submission Deadline')
                            ->getStateUsing(function ($record) {
                                return $record->rfq && $record->rfq->deadline_date instanceof \Carbon\Carbon
                                    ? $record->rfq->deadline_date->format('F j, Y, g:i A')
                                    : 'Not set';
                            }),
                    ])
                    ->columns(4),

                Section::make('Procurement Items')
                    ->schema([
                        RepeatableEntry::make('procurementItems')
                            ->label('')
                            ->schema([
                                TextEntry::make('sort')->label('Item No.'),
                                TextEntry::make('item_description')->label('Item Description'),
                                TextEntry::make('quantity')->label('Quantity'),
                                TextEntry::make('unit')->label('Unit'),
                                TextEntry::make('unit_cost')->label('Unit Cost (ABC)')->money('PHP'),
                                TextEntry::make('total_cost')->label('Total Cost (ABC)')->money('PHP'),
                            ])
                            ->columns(6)
                            ->getStateUsing(fn ($record) => $record->procurementItems->sortBy('sort')),
                        TextEntry::make('no_items')
                            ->label('')
                            ->default('No items listed')
                            ->hidden(fn ($record) => $record->procurementItems->count() > 0),
                    ]),

                Section::make('Approval Stages')
                    ->schema([
                        Grid::make(5)
                            ->schema([
                                TextEntry::make('hdr_procurement_id')
                                    ->label('')
                                    ->state('Procurement ID'),
                                TextEntry::make('hdr_approver')
                                    ->label('')
                                    ->state('Approver'),
                                TextEntry::make('hdr_sequence')
                                    ->label('')
                                    ->state('Sequence'),
                                TextEntry::make('hdr_status')
                                    ->label('')
                                    ->state('Status'),
                                TextEntry::make('hdr_remarks')
                                    ->label('')
                                    ->state('Remarks'),
                            ])
                            ->extraAttributes(['class' => 'bg-gray-100 dark:bg-gray-800 border-b']),
                        RepeatableEntry::make('approvals')
                            ->label('')
                            ->schema([
                                TextEntry::make('procurement.procurement_id')->label(''),
                                TextEntry::make('employee.full_name')->label('')->default('N/A'),
                                TextEntry::make('sequence')->label('')->alignCenter(),
                                TextEntry::make('status')
                                    ->label('')
                                    ->formatStateUsing(function ($state) {
                                        return sprintf(
                                            '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium %s">%s</span>',
                                            match ($state) {
                                                'Approved' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                                                'Pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                                                'Rejected' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                                                default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100',
                                            },
                                            $state
                                        );
                                    })
                                    ->html(),
                                TextEntry::make('remarks')
                                    ->label('')
                                    ->default('N/A'),
                            ])
                            ->columns(5)
                            ->getStateUsing(function ($record) {
                                $approvals = $record->approvals()
                                    ->where('module', 'abstract_of_quotation')
                                    ->with('employee')
                                    ->orderBy('sequence')
                                    ->get();
                                return $approvals->isEmpty() ? collect() : $approvals;
                            }),
                        TextEntry::make('no_approvers')
                            ->label('')
                            ->default('No approvers assigned.')
                            ->hidden(fn ($record) => $record->approvals()->where('module', 'abstract_of_quotation')->count() > 0),
                    ]),

                Section::make('Supplier Evaluations')
                    ->schema([
                        TextEntry::make('supplier_list')
                            ->label('')
                            ->html()
                            ->getStateUsing(function ($record) {
                                $rfqResponses = $record->rfqResponses; // Use preloaded rfqResponses
                                if ($rfqResponses->isEmpty()) {
                                    return '<p class="text-gray-500">No RFQ responses received yet</p>';
                                }

                                $html = '';
                                $hasAnyEvaluations = AoqEvaluation::where('procurement_id', $record->id)->exists();

                                foreach ($rfqResponses as $rfqResponse) {
                                    $supplierName = $rfqResponse->supplier?->business_name ?? $rfqResponse->business_name ?? 'Unknown Supplier';

                                    $evaluations = $rfqResponse->aoqEvaluations; // Use preloaded aoqEvaluations
                                    $docEvals = $evaluations->keyBy('requirement');
                                    $hasFailedDocs = $evaluations->where('status', 'fail')->isNotEmpty();

                                    $hasWinningBids = $hasAnyEvaluations && $evaluations->where('lowest_bid', true)->isNotEmpty();

                                    $html .= '<div class="border rounded-lg p-6 mb-6 bg-white dark:bg-gray-800">';
                                    $html .= '<div class="flex items-center justify-between mb-4">';
                                    $html .= '<h3 class="text-xl font-bold">' . e($supplierName) . '</h3>';

                                    if ($hasFailedDocs) {
                                        $html .= '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">❌ DISQUALIFIED</span>';
                                    } elseif ($hasWinningBids) {
                                        $html .= '<span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">🏆 WINNING BID</span>';
                                    }

                                    $html .= '</div>';

                                    $html .= '<div class="mb-6">';
                                    $html .= '<details class="border rounded-lg">';
                                    $html .= '<summary class="cursor-pointer p-4 font-semibold bg-gray-50 dark:bg-gray-700">Document Evaluation</summary>';
                                    $html .= '<div class="p-4">';

                                    $hasDocuments = !empty($rfqResponse->rfq_document) || (is_array($rfqResponse->documents) && !empty($rfqResponse->documents));

                                    if ($hasDocuments) {
                                        $html .= '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
                                        $html .= '<thead class="bg-gray-50 dark:bg-gray-700">';
                                        $html .= '<tr>';
                                        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Document Type</th>';
                                        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Document</th>';
                                        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>';
                                        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Remarks</th>';
                                        $html .= '</tr>';
                                        $html .= '</thead>';
                                        $html .= '<tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">';

                                        if (!empty($rfqResponse->rfq_document)) {
                                            $rfqEval = $docEvals->get('rfq_document');
                                            if (!$rfqEval) {
                                                $rfqEval = $docEvals->whereIn('requirement', ['rfq_document', 'original_rfq_document'])->first();
                                            }

                                            $status = $rfqEval?->status ?? 'pending';
                                            $remarks = $rfqEval?->remarks ?? 'No remarks';

                                            $statusBadge = match($status) {
                                                'pass' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">✓ Pass</span>',
                                                'fail' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">✗ Fail</span>',
                                                default => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">⏳ Pending</span>',
                                            };

                                            $disk = Storage::disk('public');
                                            $rfqPath = $rfqResponse->rfq_document;
                                            $pathExists = filled($rfqPath) && $disk->exists($rfqPath);
                                            $documentUrl = $pathExists ? $disk->url($rfqPath) : '#';
                                            $filename = basename($rfqPath);
                                            $linkClass = $pathExists ? 'text-primary-600 hover:underline' : 'text-gray-500 cursor-not-allowed';
                                            $linkText = $pathExists ? e($filename) : e($filename) . ' (File not found)';

                                            $html .= '<tr class="bg-blue-50 dark:bg-blue-900/20">';
                                            $html .= '<td class="px-4 py-3 text-sm font-semibold">Original RFQ Document</td>';
                                            $html .= '<td class="px-4 py-3 text-sm"><a href="' . $documentUrl . '" target="_blank" class="' . $linkClass . '">' . $linkText . '</a></td>';
                                            $html .= '<td class="px-4 py-3 text-sm">' . $statusBadge . '</td>';
                                            $html .= '<td class="px-4 py-3 text-sm">' . e($remarks) . '</td>';
                                            $html .= '</tr>';
                                        }

                                        if (is_array($rfqResponse->documents) && !empty($rfqResponse->documents)) {
                                            foreach ($rfqResponse->documents as $requirement => $path) {
                                                $normalizedReq = strtolower(str_replace(' ', '_', $requirement));
                                                $eval = $docEvals->get($normalizedReq);
                                                $status = $eval?->status ?? 'pending';
                                                $remarks = $eval?->remarks ?? 'No remarks';

                                                $statusBadge = match($status) {
                                                    'pass' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">✓ Pass</span>',
                                                    'fail' => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100">✗ Fail</span>',
                                                    default => '<span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">⏳ Pending</span>',
                                                };

                                                $disk = Storage::disk('public');
                                                $docPath = $path;
                                                $pathExists = filled($docPath) && is_string($docPath) && $disk->exists($docPath);
                                                $documentUrl = $pathExists ? $disk->url($docPath) : '#';
                                                $filename = basename($docPath);
                                                $linkClass = $pathExists ? 'text-primary-600 hover:underline' : 'text-gray-500 cursor-not-allowed';
                                                $linkText = $pathExists ? e($filename) : e($filename) . ' (File not found)';

                                                $html .= '<tr>';
                                                $html .= '<td class="px-4 py-3 text-sm">' . ucwords(str_replace('_', ' ', $requirement)) . '</td>';
                                                $html .= '<td class="px-4 py-3 text-sm"><a href="' . $documentUrl . '" target="_blank" class="' . $linkClass . '">' . $linkText . '</a></td>';
                                                $html .= '<td class="px-4 py-3 text-sm">' . $statusBadge . '</td>';
                                                $html .= '<td class="px-4 py-3 text-sm">' . e($remarks) . '</td>';
                                                $html .= '</tr>';
                                            }
                                        }

                                        $html .= '</tbody></table>';
                                    } else {
                                        $html .= '<p class="text-gray-500">No documents submitted</p>';
                                    }

                                    $html .= '</div></details></div>';

                                    $html .= '<div class="mb-6"><details open>';
                                    $html .= '<summary class="cursor-pointer p-4 font-semibold bg-gray-50 dark:bg-gray-700 border rounded-lg">Quote Comparison</summary>';
                                    $html .= '<div class="p-4">';

                                    if ($rfqResponse->quotes->count() > 0) {
                                        $html .= '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
                                        $html .= '<thead class="bg-gray-50 dark:bg-gray-700"><tr>';
                                        $html .= '<th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">No.</th>';
                                        $html .= '<th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Item</th>';
                                        $html .= '<th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Qty</th>';
                                        $html .= '<th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Unit</th>';
                                        $html .= '<th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ABC Unit</th>';
                                        $html .= '<th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">ABC Total</th>';
                                        $html .= '<th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Unit Price</th>';
                                        $html .= '<th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Total</th>';
                                        $html .= '</tr></thead>';
                                        $html .= '<tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">';

                                        foreach ($rfqResponse->quotes as $quote) {
                                            if (!$quote->procurementItem) continue;

                                            $item = $quote->procurementItem;
                                            $evaluation = $docEvals->firstWhere('requirement', 'quote_' . $item->id);
                                            $isLowest = $evaluation?->lowest_bid ?? false;

                                            $html .= '<tr class="' . ($isLowest ? 'bg-green-50 dark:bg-green-900/20' : '') . '">';
                                            $html .= '<td class="px-2 py-3 text-sm">' . e($item->sort) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm">' . e($item->item_description) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm">' . e($item->quantity) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm">' . e($item->unit) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-right">₱' . number_format($item->unit_cost, 2) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-right">₱' . number_format($item->total_cost, 2) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-right font-semibold">₱' . number_format($quote->unit_value, 2) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-right font-semibold">₱' . number_format($quote->total_value, 2) . '</td>';
                                            $html .= '</tr>';
                                        }

                                        $html .= '</tbody></table></div>';
                                    } else {
                                        $html .= '<p class="text-gray-500">No quotes submitted</p>';
                                    }

                                    $html .= '</div></details></div>';

                                    $totalQuoted = $rfqResponse->quotes->sum('total_value');
                                    $html .= '<div class="mt-4 text-right">';
                                    $html .= '<span class="text-lg font-bold">Total Quoted Amount: ₱' . number_format($totalQuoted, 2) . '</span>';
                                    $html .= '</div></div>';
                                }

                                return $html;
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        $employeeId = Auth::user()->employee->id ?? null;
        $isAssigned = $employeeId && $this->record->approvals()
            ->where('employee_id', $employeeId)
            ->where('status', 'Pending')
            ->exists();

        $canAct = $this->record->status === 'Locked' && $isAssigned;
        if ($canAct) {
            $currentApproval = $this->record->approvals()
                ->where('employee_id', $employeeId)
                ->where('status', 'Pending')
                ->first();
            if ($currentApproval) {
                $hasPreviousPending = $this->record->approvals()
                    ->where('module', 'abstract_of_quotation')
                    ->where('sequence', '<', $currentApproval->sequence)
                    ->where('status', 'Pending')
                    ->exists();
                $hasRejection = $this->record->approvals()
                    ->where('module', 'abstract_of_quotation')
                    ->where('status', 'Rejected')
                    ->exists();
                $canAct = !$hasPreviousPending && !$hasRejection;
            } else {
                $canAct = false;
            }
        }

        if (!$canAct) {
            return [
                Action::make('viewPdf')
                    ->label('View PDF')
                    ->icon('heroicon-o-document-text')
                    ->url(fn () => route('procurements.aoq.pdf', $this->record->parent_id), true)
                    ->color('info'),
            ];
        }

        return [
            Action::make('viewPdf')
                ->label('View PDF')
                ->icon('heroicon-o-document-text')
                ->url(fn () => route('procurements.aoq.pdf', $this->record->parent_id), true)
                ->color('info'),
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () use ($employeeId) {
                    $approval = $this->record->approvals()
                        ->where('employee_id', $employeeId)
                        ->where('status', 'Pending')
                        ->first();

                    if ($approval) {
                        $approval->update([
                            'status' => 'Approved',
                            'date_approved' => now(),
                            'remarks' => null,
                        ]);

                        $allApproved = $this->record->approvals()->where('module', 'abstract_of_quotation')
                            ->where('status', 'Pending')
                            ->doesntExist();

                        if ($allApproved) {
                            $this->record->update(['status' => 'Approved']);
                    
                        }
                         ActivityLogger::log(
    'Approved Abstract of Quotation',
    'AOQ ' . $this->record->procurement_id . ' was approved by ' . Auth::user()->name
);

                         $procurement = $this->record;
                    $employees = \App\Models\Employee::whereIn('id', $procurement->employees()->pluck('employee_id'))->get();

                    foreach ($employees as $employee) {
                        if (!empty($employee->email)) {
                            \Mail::send('emails.aoq-status', [
                                'procurement' => $procurement,
                                'employee' => $employee,
                                'status' => 'Approved',
                                'link' => route('filament.admin.resources.procurements.view', $procurement->id),
                            ], function ($message) use ($employee, $procurement) {
                                $message->to($employee->email)
                                    ->subject('AOQ Approved: ' . $procurement->title);
                            });
                        }
                    }

                    Notification::make()->title('AOQ approved')->success()->send();
                    $this->record->refresh();
                    }
                }),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->form([
                    \Filament\Forms\Components\Textarea::make('remarks')
                        ->label('Remarks')
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function (array $data) use ($employeeId) {
                    $approval = $this->record->approvals()
                        ->where('employee_id', $employeeId)
                        ->where('status', 'Pending')
                        ->first();

                    if ($approval) {
                        $approval->update([
                            'status' => 'Rejected',
                            'date_approved' => now(),
                            'remarks' => $data['remarks'],
                        ]);

                        $this->record->update(['status' => 'Rejected']);
                        $this->record->parent->update(['status' => 'Rejected']);

                        $procurement = $this->record;

                        if (method_exists($procurement, 'participants')) {
                            $participantIds = $procurement->participants()->pluck('employee_id');
                        } else {
                            $participantIds = collect(); // fallback if method not defined
                        }

                        $employees = \App\Models\Employee::whereIn('id', $participantIds)->get();


                    foreach ($employees as $employee) {
                        if (!empty($employee->email)) {
                            \Mail::send('emails.aoq-status', [
                                'procurement' => $procurement,
                                'employee' => $employee,
                                'status' => 'Rejected',
                                'remarks' => $data['remarks'],
                                'link' => route('filament.admin.resources.procurements.view', $procurement->id),
                            ], function ($message) use ($employee, $procurement) {
                                $message->to($employee->email)
                                    ->subject('AOQ Rejected: ' . $procurement->title);
                            });
                        }
                    }
                    ActivityLogger::log(
    'Rejected Abstract of Quotation',
    'AOQ ' . $this->record->procurement_id . ' was rejected by ' . Auth::user()->name
);

                    Notification::make()->title('AOQ rejected')->danger()->send();
                    $this->record->refresh();
                    }
                }),






                
            Action::make('notifyBidders')
    ->label('Notify Bidders')
    ->icon('heroicon-o-envelope')
    ->color('success')
    ->requiresConfirmation()
    ->action(function () {
    $procurement = $this->record;

    // Notify the winning supplier
    $winningEval = AoqEvaluation::where('procurement_id', $procurement->id)
        ->where('lowest_bid', true)
        ->with(['rfqResponse.supplier', 'rfqResponse.quotes'])
        ->first();

    if ($winningEval) {
        $winner = $winningEval->rfqResponse->supplier;
        if ($winner && $winner->email_address) {

            $evaluationDetails = $winningEval->rfqResponse->quotes->map(function ($quote) {
                return [
                    'specifications' => $quote->specifications ?? 'N/A',
                    'unit_value' => $quote->unit_value ?? 0,
                    'total_value' => $quote->total_value ?? 0,
                    'remarks' => $quote->statement_of_compliance ? 'Compliant' : 'Non-Compliant',
                ];
            })->toArray();

            Mail::to($winner->email_address)
                ->send(new WinningSupplierMail(
                    $winner->business_name,
                    $procurement->title,
                    $evaluationDetails
                ));
        }
    }

    // Notify the losing suppliers
    $losingEvals = AoqEvaluation::where('procurement_id', $procurement->id)
        ->where('lowest_bid', false)
        ->with(['rfqResponse.supplier', 'rfqResponse.quotes'])
        ->get();

    foreach ($losingEvals as $eval) {
        $supplier = $eval->rfqResponse->supplier;
        if ($supplier && $supplier->email_address) {

            $evaluationDetails = $eval->rfqResponse->quotes->map(function ($quote) {
                return [
                    'specifications' => $quote->specifications ?? 'N/A',
                    'unit_value' => $quote->unit_value ?? 0,
                    'total_value' => $quote->total_value ?? 0,
                    'remarks' => $quote->statement_of_compliance ? 'Compliant' : 'Non-Compliant',
                ];
            })->toArray();

            Mail::to($supplier->email_address)
                ->send(new LosingSupplierMail(
                    $supplier->business_name,
                    $procurement->title,
                    $evaluationDetails
                ));
        }
    }

    Notification::make()
        ->title('All bidders have been notified successfully.')
        ->success()
        ->send();
}),




        ];
    }
}
 

          