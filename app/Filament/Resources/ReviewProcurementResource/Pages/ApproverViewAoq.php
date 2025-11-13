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
        $this->record->load([
            'rfqResponses.aoqEvaluations' => function ($query) {
                $query->where('procurement_id', $this->record->id);
            }
        ]);

        // Safely fetch Purchase Request
        $pr = $this->record->procurementItems->isNotEmpty()
            ? Procurement::where('parent_id', $this->record->parent_id)
                ->where('module', 'purchase_request')
                ->first()
            : null;

        // Determine labels based on basis
        $isLot = $pr && $pr->basis === 'lot';
        $numberLabel = $isLot ? 'Lot No.' : 'Item No.';
        $descriptionLabel = $isLot ? 'Lot Description' : 'Item Description';

        return $infolist
            ->schema([
                Section::make('Abstract of Quotation Details')
                    ->schema([
                        TextEntry::make('procurement_id')->label('AOQ No.'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'Pending'   => 'warning',
                                'Evaluated' => 'info',
                                'Approved'  => 'success',
                                'Locked'    => 'danger',
                                'Rejected'  => 'danger',
                                default     => 'gray',
                            })
                            ->getStateUsing(function ($record) {
                                $approvals = $record->approvals;
                                
                                if ($approvals->isEmpty()) {
                                    return 'Pending';
                                } elseif ($approvals->contains('status', 'Rejected')) {
                                    return 'Rejected';
                                } elseif ($approvals->every(fn ($approval) => $approval->status === 'Approved')) {
                                    return 'Approved';
                                }
                                return $record->status;
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
                        TextEntry::make('created_at')->label('Date Filed')->date('Y-m-d'),
                        TextEntry::make('title')->label('Title/Purpose'),
                        TextEntry::make('requested_by')
                            ->label('End User')
                            ->getStateUsing(fn ($record) => $record->requester?->full_name ?? 'Not set'),
                        TextEntry::make('procurement_type')
                            ->badge()
                            ->color(fn (string $state) => $state === 'small_value_procurement' ? 'info' : 'primary')
                            ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                            ->label('Mode of Procurement'),
                        TextEntry::make('fundCluster.name')->label('Fund Cluster'),
                        TextEntry::make('category.name')->label('Category'),
                        TextEntry::make('grand_total')
                            ->label('Approved Budget for Contract (ABC)')
                            ->money('PHP')
                            ->weight('bold')
                            ->getStateUsing(fn ($record) => $record->procurementItems->sum('total_cost')),
                        TextEntry::make('delivery_period')
                            ->label('Delivery Period')
                            ->state(function ($record) {
                                $rfq = Procurement::where('parent_id', $record->parent_id)
                                    ->where('module', 'request_for_quotation')
                                    ->first();
                                if ($rfq && $rfq->delivery_mode === 'days' && $rfq->delivery_value) {
                                    return "Within {$rfq->delivery_value} calendar days upon receipt of Purchase Order";
                                }
                                if ($rfq && $rfq->delivery_mode === 'date' && $rfq->delivery_value) {
                                    return Carbon::parse($rfq->delivery_value)->format('F j, Y');
                                }
                                return 'Not set';
                            }),
                        TextEntry::make('bid_opening_datetime')
                            ->label('Date and Time of Bid Opening')
                            ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d h:i A') : 'Not scheduled')
                            ->badge()
                            ->color(fn ($state) => $state ? (Carbon::now()->greaterThanOrEqualTo($state) ? 'success' : 'warning') : 'danger'),
                    ])
                    ->columns(4),

                Section::make('Procurement Items')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        RepeatableEntry::make('procurementItems')
                            ->label('')
                            ->schema([
                                TextEntry::make('sort')->label($numberLabel),
                                TextEntry::make('item_description')->label($descriptionLabel),
                                TextEntry::make('quantity')->label('Quantity'),
                                TextEntry::make('unit')->label('Unit'),
                                TextEntry::make('unit_cost')->label('Unit Cost (ABC)')->money('PHP'),
                                TextEntry::make('total_cost')->label('Total Cost (ABC)')->money('PHP'),
                            ])
                            ->columns(6),
                        TextEntry::make('no_items')
                            ->label('')
                            ->default('No items listed')
                            ->hidden(fn ($record) => $record->procurementItems->count() > 0),
                    ]),

                // Supplier Responses Section (same as user side, without actions)
                Section::make('Supplier Responses')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        \Filament\Infolists\Components\ViewEntry::make('rfq_responses_table')
                            ->label('')
                            ->view('filament.resources.procurement-resource.pages.rfq-responses-table-readonly')
                            ->state($this->record)
                    ])
                    ->columnSpanFull(),

                Section::make('Supplier Evaluations')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        TextEntry::make('evaluation_blocked')
                            ->label('')
                            ->getStateUsing(function ($record) {
                                if (is_null($record->bid_opening_datetime)) {
                                    return 'Bid opening date and time has not been set yet.';
                                }
                                if (Carbon::now()->lessThan($record->bid_opening_datetime)) {
                                    return 'Evaluations can start on ' . $record->bid_opening_datetime->format('Y-m-d h:i A') . '.';
                                }
                                return null;
                            })
                            ->hidden(function ($record) {
                                return !is_null($record->bid_opening_datetime) && 
                                    Carbon::now()->greaterThanOrEqualTo($record->bid_opening_datetime);
                            })
                            ->formatStateUsing(fn ($state) => $state)
                            ->extraAttributes(['class' => 'text-red-600 font-semibold']),
                        TextEntry::make('supplier_list')
                            ->label('')
                            ->html()
                            ->getStateUsing(function ($record) use ($isLot, $numberLabel, $descriptionLabel) {
                                $record->load([
                                    'rfqResponses.aoqEvaluations' => function ($query) use ($record) {
                                        $query->where('procurement_id', $record->id);
                                    }
                                ]);

                                if ($record->rfqResponses->isEmpty()) {
                                    return '<p class="text-gray-500">No RFQ responses received yet</p>';
                                }

                                $html = '';
                                $hasAnyEvaluations = AoqEvaluation::where('procurement_id', $record->id)->exists();

                                // Compute evaluation completeness
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
                                
                                $evaluationComplete = $totalDocs > 0 && $evaluatedDocs >= $totalDocs;

                                foreach ($record->rfqResponses as $rfqResponse) {
                                    $supplierName = $rfqResponse->supplier?->business_name ?? $rfqResponse->business_name ?? 'Unknown Supplier';

                                    $evaluations = AoqEvaluation::where('procurement_id', $record->id)
                                        ->where('rfq_response_id', $rfqResponse->id)
                                        ->where(function($query) {
                                            $query->where('requirement', 'not like', 'quote_%')
                                                ->orWhere('requirement', 'rfq_document');
                                        })
                                        ->get();

                                    $docEvals = $evaluations->keyBy('requirement');
                                    $hasFailedDocs = $evaluations->where('status', 'fail')->isNotEmpty();

                                    // Only show winning bids if evaluations are complete
                                    $hasWinningBids = $evaluationComplete && $hasAnyEvaluations && AoqEvaluation::where('procurement_id', $record->id)
                                        ->where('rfq_response_id', $rfqResponse->id)
                                        ->where('lowest_bid', true)
                                        ->exists();

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
                                    $html .= '<details class="border rounded-lg" open>';
                                    $html .= '<summary class="cursor-pointer p-4 font-semibold bg-gray-50 dark:bg-gray-700">Document Evaluation</summary>';
                                    $html .= '<div class="p-4">';
                                    
                                    $hasDocuments = !empty($rfqResponse->rfq_document) || (is_array($rfqResponse->documents) && !empty($rfqResponse->documents));

                                    if ($hasDocuments) {
                                        $html .= '<div class="overflow-x-auto"><table class="w-full divide-y divide-gray-200 dark:divide-gray-700" style="table-layout: fixed;">';
                                        $html .= '<thead class="bg-gray-50 dark:bg-gray-700">';
                                        $html .= '<tr>';
                                        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 20%;">Document Type</th>';
                                        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 30%;">Document</th>';
                                        $html .= '<th class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 12%;">Status</th>';
                                        $html .= '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 38%;">Remarks</th>';
                                        $html .= '</tr>';
                                        $html .= '</thead>';
                                        $html .= '<tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">';
                                        
                                        if (!empty($rfqResponse->rfq_document)) {
                                            $rfqEval = $docEvals->get('rfq_document');
                                            if (!$rfqEval) {
                                                $rfqEval = AoqEvaluation::where('procurement_id', $record->id)
                                                    ->where('rfq_response_id', $rfqResponse->id)
                                                    ->whereIn('requirement', ['rfq_document', 'original_rfq_document'])
                                                    ->first();
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
                                            $html .= '<td class="px-4 py-3 text-sm font-semibold align-top">Original RFQ Document</td>';
                                            $html .= '<td class="px-4 py-3 text-sm align-top" style="word-wrap: break-word; word-break: break-word;"><a href="' . $documentUrl . '" target="_blank" class="' . $linkClass . '">' . $linkText . '</a></td>';
                                            $html .= '<td class="px-4 py-3 text-sm text-center align-top">' . $statusBadge . '</td>';
                                            $html .= '<td class="px-4 py-3 text-sm align-top" style="word-wrap: break-word; word-break: break-word;">' . e($remarks) . '</td>';
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
                                                $html .= '<td class="px-4 py-3 text-sm align-top">' . ucwords(str_replace('_', ' ', $requirement)) . '</td>';
                                                $html .= '<td class="px-4 py-3 text-sm align-top" style="word-wrap: break-word; word-break: break-word;"><a href="' . $documentUrl . '" target="_blank" class="' . $linkClass . '">' . $linkText . '</a></td>';
                                                $html .= '<td class="px-4 py-3 text-sm text-center align-top">' . $statusBadge . '</td>';
                                                $html .= '<td class="px-4 py-3 text-sm align-top" style="word-wrap: break-word; word-break: break-word;">' . e($remarks) . '</td>';
                                                $html .= '</tr>';
                                            }
                                        }

                                        $html .= '</tbody></table></div>';
                                    } else {
                                        $html .= '<p class="text-gray-500">No documents submitted</p>';
                                    }

                                    $html .= '</div></details></div>';

                                    $html .= '<div class="mb-6">';
                                    $html .= '<details class="border rounded-lg" open>';
                                    $html .= '<summary class="cursor-pointer p-4 font-semibold bg-gray-50 dark:bg-gray-700">Quote Comparison</summary>';
                                    $html .= '<div class="p-4">';

                                    if ($rfqResponse->quotes->count() > 0) {
                                        $html .= '<div class="overflow-x-auto"><table class="w-full divide-y divide-gray-200 dark:divide-gray-700" style="table-layout: fixed;">';
                                        $html .= '<thead class="bg-gray-50 dark:bg-gray-700"><tr>';
                                        $html .= '<th class="px-2 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 6%;">' . $numberLabel . '</th>';
                                        $html .= '<th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 22%;">' . $descriptionLabel . '</th>';
                                        $html .= '<th class="px-2 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 6%;">Qty</th>';
                                        $html .= '<th class="px-2 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 8%;">Unit</th>';
                                        $html .= '<th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 13%;">ABC Unit</th>';
                                        $html .= '<th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 13%;">ABC Total</th>';
                                        $html .= '<th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 14%;">Unit Price</th>';
                                        $html .= '<th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase" style="width: 18%;">Total</th>';
                                        $html .= '</tr></thead>';
                                        $html .= '<tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">';

                                        foreach ($rfqResponse->quotes as $quote) {
                                            if (!$quote->procurementItem) continue;
                                            
                                            $item = $quote->procurementItem;
                                            $evaluation = AoqEvaluation::where('procurement_id', $record->id)
                                                ->where('rfq_response_id', $rfqResponse->id)
                                                ->where('requirement', 'quote_' . $item->id)
                                                ->first();
                                            $isLowest = $evaluation?->lowest_bid ?? false;

                                            $html .= '<tr class="' . ($isLowest ? 'bg-green-50 dark:bg-green-900/20' : '') . '">';
                                            $html .= '<td class="px-2 py-3 text-sm text-center align-top">' . e($item->sort) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm align-top" style="word-wrap: break-word; word-break: break-word;">' . e($item->item_description) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-center align-top">' . e($item->quantity) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-center align-top">' . e($item->unit) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-right align-top">₱' . number_format($item->unit_cost, 2) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-right align-top">₱' . number_format($item->total_cost, 2) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-right font-semibold align-top">₱' . number_format($quote->unit_value, 2) . '</td>';
                                            $html .= '<td class="px-2 py-3 text-sm text-right font-semibold align-top">₱' . number_format($quote->total_value, 2) . '</td>';
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
                            ->visible(function ($record) {
                                return !is_null($record->bid_opening_datetime) && 
                                    Carbon::now()->greaterThanOrEqualTo($record->bid_opening_datetime);
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Approval Stages')
                    ->collapsible()
                    ->collapsed(false)
                    ->schema([
                        Grid::make(5)
                            ->schema([
                                TextEntry::make('hdr_procurement_id')->label('')->state('Procurement ID')->weight('bold'),
                                TextEntry::make('hdr_approver')->label('')->state('Approver')->weight('bold'),
                                TextEntry::make('hdr_sequence')->label('')->state('Sequence')->weight('bold'),
                                TextEntry::make('hdr_status')->label('')->state('Status')->weight('bold'),
                                TextEntry::make('hdr_date_approved')->label('')->state('Date Approved')->weight('bold'),
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
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Approved' => 'success',
                                        'Pending'  => 'warning',
                                        'Rejected' => 'danger',
                                        default    => 'gray',
                                    }),
                                TextEntry::make('date_approved')
                                    ->label('')
                                    ->formatStateUsing(fn ($state) => $state ? $state->format('Y-m-d H:i') : 'Pending')
                                    ->color(fn ($state) => $state ? 'success' : 'warning'),
                            ])
                            ->columns(5),
                        TextEntry::make('no_approvers')
                            ->label('')
                            ->default('No approvers assigned.')
                            ->hidden(fn ($record) => $record->approvals->count() > 0),
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

        // Check if tie-breaking record exists
        $hasTieBreakingRecord = \DB::table('aoq_tie_breaking_records')
            ->where('procurement_id', $this->record->id)
            ->exists();

        // Base actions available to all
        $actions = [
            Action::make('viewPdf')
                ->label('View PDF')
                ->icon('heroicon-o-document-text')
                ->url(fn () => route('procurements.aoq.pdf', $this->record->parent_id), true)
                ->openUrlInNewTab()
                ->color('info'),
        ];

        // Add tie-breaking view action if record exists
        if ($hasTieBreakingRecord) {
            $actions[] = Action::make('viewTieBreaking')
                ->label('View Tie-Breaking Details')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->modalContent(function () {
                    $tieRecord = \DB::table('aoq_tie_breaking_records')
                        ->where('procurement_id', $this->record->id)
                        ->latest()
                        ->first();
                    
                    if (!$tieRecord) {
                        return view('filament.components.empty-state', [
                            'message' => 'No tie-breaking record found.'
                        ]);
                    }

                    $tiedSuppliers = json_decode($tieRecord->tied_suppliers_data, true);

                    return view('filament.components.tie-breaking-details', [
                        'record' => $tieRecord,
                        'suppliers' => $tiedSuppliers,
                    ]);
                })
                ->modalWidth('3xl')
                ->slideOver()
                ->modalSubmitAction(false)
                ->modalCancelAction(fn ($action) => $action
                    ->label('Close')
                    ->color('primary')
                    ->icon('heroicon-o-x-mark')
                    ->button()
                );
        }

        // If user can't act (approve/reject), return view-only actions
        if (!$canAct) {
            return $actions;
        }

        // Add approval actions for authorized users
        $actions[] = Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve AOQ')
            ->modalDescription('Are you sure you want to approve this Abstract of Quotation?')
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

                    $allApproved = $this->record->approvals()
                        ->where('module', 'abstract_of_quotation')
                        ->where('status', 'Pending')
                        ->doesntExist();

                    if ($allApproved) {
                        $this->record->update(['status' => 'Approved']);
                    }

                    ActivityLogger::log(
                        'Approved Abstract of Quotation',
                        'AOQ ' . $this->record->procurement_id . ' was approved by ' . Auth::user()->name
                    );

                    // Send email notifications
                    $procurement = $this->record;
                    if (method_exists($procurement, 'participants')) {
                        $participantIds = $procurement->participants()->pluck('employee_id');
                    } else {
                        $participantIds = collect();
                    }

                    $employees = \App\Models\Employee::whereIn('id', $participantIds)->get();

                    foreach ($employees as $employee) {
                        if (!empty($employee->email)) {
                            try {
                                \Mail::send('emails.aoq-status', [
                                    'procurement' => $procurement,
                                    'employee' => $employee,
                                    'status' => 'Approved',
                                    'link' => route('filament.admin.resources.procurements.view', $procurement->parent_id),
                                ], function ($message) use ($employee, $procurement) {
                                    $message->to($employee->email)
                                        ->subject('AOQ Approved: ' . $procurement->title);
                                });
                            } catch (\Exception $e) {
                                \Log::error("Failed to send AOQ approval email: {$e->getMessage()}");
                            }
                        }
                    }

                    Notification::make()
                        ->title('AOQ Approved')
                        ->body('The Abstract of Quotation has been approved successfully.')
                        ->success()
                        ->send();

                    $this->record->refresh();
                }
            });

        $actions[] = Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Reject AOQ')
            ->modalDescription('Please provide a reason for rejecting this Abstract of Quotation.')
            ->form([
                \Filament\Forms\Components\Textarea::make('remarks')
                    ->label('Remarks')
                    ->required()
                    ->maxLength(500)
                    ->rows(4)
                    ->placeholder('Enter the reason for rejection...'),
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

                    ActivityLogger::log(
                        'Rejected Abstract of Quotation',
                        'AOQ ' . $this->record->procurement_id . ' was rejected by ' . Auth::user()->name . 
                        ' with remarks: ' . $data['remarks']
                    );

                    // Send email notifications
                    $procurement = $this->record;
                    if (method_exists($procurement, 'participants')) {
                        $participantIds = $procurement->participants()->pluck('employee_id');
                    } else {
                        $participantIds = collect();
                    }

                    $employees = \App\Models\Employee::whereIn('id', $participantIds)->get();

                    foreach ($employees as $employee) {
                        if (!empty($employee->email)) {
                            try {
                                \Mail::send('emails.aoq-status', [
                                    'procurement' => $procurement,
                                    'employee' => $employee,
                                    'status' => 'Rejected',
                                    'remarks' => $data['remarks'],
                                    'link' => route('filament.admin.resources.procurements.view', $procurement->parent_id),
                                ], function ($message) use ($employee, $procurement) {
                                    $message->to($employee->email)
                                        ->subject('AOQ Rejected: ' . $procurement->title);
                                });
                            } catch (\Exception $e) {
                                \Log::error("Failed to send AOQ rejection email: {$e->getMessage()}");
                            }
                        }
                    }

                    Notification::make()
                        ->title('AOQ Rejected')
                        ->body('The Abstract of Quotation has been rejected.')
                        ->danger()
                        ->send();

                    $this->record->refresh();
                }
            });

        $actions[] = Action::make('notifyBidders')
            ->label('Notify Bidders')
            ->icon('heroicon-o-envelope')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Notify All Bidders')
            ->modalDescription('This will send email notifications to all suppliers (winners and losers) about the evaluation results. Continue?')
            ->action(function () {
                $procurement = $this->record;

                // Notify the winning supplier
                $winningEval = AoqEvaluation::where('procurement_id', $procurement->id)
                    ->where('lowest_bid', true)
                    ->with(['rfqResponse.supplier', 'rfqResponse.quotes'])
                    ->first();

                $notifiedCount = 0;

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

                        try {
                            Mail::to($winner->email_address)
                                ->send(new WinningSupplierMail(
                                    $winner->business_name,
                                    $procurement->title,
                                    $evaluationDetails
                                ));
                            $notifiedCount++;
                        } catch (\Exception $e) {
                            \Log::error("Failed to send winning email to {$winner->email_address}: {$e->getMessage()}");
                        }
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

                        try {
                            Mail::to($supplier->email_address)
                                ->send(new LosingSupplierMail(
                                    $supplier->business_name,
                                    $procurement->title,
                                    $evaluationDetails
                                ));
                            $notifiedCount++;
                        } catch (\Exception $e) {
                            \Log::error("Failed to send losing email to {$supplier->email_address}: {$e->getMessage()}");
                        }
                    }
                }

                ActivityLogger::log(
                    'Notified Bidders',
                    'All bidders were notified for AOQ ' . $this->record->procurement_id . ' by ' . Auth::user()->name
                );

                Notification::make()
                    ->title('Bidders Notified')
                    ->body("Successfully sent {$notifiedCount} email notification(s) to suppliers.")
                    ->success()
                    ->send();
            })
            ->visible(fn () => $this->record->status === 'Approved');

        return $actions;
    }
}