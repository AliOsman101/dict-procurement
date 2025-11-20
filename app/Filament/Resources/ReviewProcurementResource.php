<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewProcurementResource\Pages;
use App\Models\Procurement;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ReviewProcurementResource extends Resource
{
    protected static ?string $model = Procurement::class;

    protected static ?string $navigationIcon = 'heroicon-o-eye';

    protected static ?string $navigationLabel = 'Review Procurement';

    protected static ?string $navigationGroup = 'Procurement Management';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = true;

    public static function form(Form $form): Form
    {
        return $form->schema([]); // No form, read-only
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('procurement_id')
                    ->label('Procurement ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('Title')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('requested_by')
                    ->label('Requested By')
                    ->getStateUsing(function ($record) {
                        $pr = $record->children()->where('module', 'purchase_request')->first();
                        return $pr && $pr->requester ? $pr->requester->full_name : 'Not set';
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date Created')
                    ->date('Y-m-d')
                    ->sortable(),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $employeeId = Auth::user()->employee->id ?? null;

                if (!$employeeId) {
                    return $query->whereRaw('1 = 0');
                }

                // Show procurements where:
                // 1. Employee is assigned as an approver
                // 2. Module status is "Locked" (ready for approval)
                // 3. Employee's approval status is "Pending"
                // 4. No previous approvers in sequence have "Pending" status
                // 5. No rejections exist in the module
                return $query->whereNull('module')
                    ->whereHas('children', function ($childQuery) use ($employeeId) {
                        $childQuery->whereIn('module', [
                            'purchase_request',
                            'request_for_quotation',
                            'abstract_of_quotation',
                            'bac_resolution_recommending_award',
                            'purchase_order'
                        ])
                        ->where('status', 'Locked') // Only show "Locked" modules (including revised ones)
                        ->whereHas('approvals', function ($approvalQuery) use ($employeeId) {
                            $approvalQuery->where('employee_id', $employeeId)
                                ->where('status', 'Pending'); // Employee has pending approval
                        })
                        ->whereDoesntHave('approvals', function ($rejectionQuery) {
                            $rejectionQuery->where('status', 'Rejected'); // No rejections in this module
                        })
                        ->whereHas('approvals', function ($sequenceQuery) use ($employeeId) {
                            // Get current employee's sequence
                            $sequenceQuery->where('employee_id', $employeeId)
                                ->where('status', 'Pending')
                                ->whereNotExists(function ($previousPendingQuery) use ($employeeId) {
                                    // No previous approvers have pending status
                                    $previousPendingQuery->selectRaw('1')
                                        ->from('approvals as prev_approvals')
                                        ->whereColumn('prev_approvals.procurement_id', 'approvals.procurement_id')
                                        ->where('prev_approvals.status', 'Pending')
                                        ->whereRaw('prev_approvals.sequence < (SELECT sequence FROM approvals WHERE employee_id = ? AND procurement_id = prev_approvals.procurement_id LIMIT 1)', [$employeeId]);
                                });
                        });
                    });
            })
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View')
                    ->url(fn ($record) => Pages\ReviewProcurementResourceView::getUrl(['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviewProcurements::route('/'),
            'view' => Pages\ReviewProcurementResourceView::route('/{record}'),
            'approver-view-ppmp' => Pages\ApproverViewPpmp::route('/{record}/ppmp'),
            'approver-view-pr' => Pages\ApproverViewPr::route('/{record}/pr'),
            'approver-view-rfq' => Pages\ApproverViewRfq::route('/{record}/rfq'),
            'rfq-distribution' => Pages\ApproverRfqDistribution::route('/{record}/rfq-distribution'),
            'approver-view-aoq' => Pages\ApproverViewAoq::route('/{record}/aoq'),
            'approver-view-bac' => Pages\ApproverViewBac::route('/{record}/bac'),
            'approver-view-po' => Pages\ApproverViewPo::route('/{record}/po'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        $employee = $user?->employee;
        return $employee && \App\Models\DefaultApprover::where('employee_id', $employee->id)->exists();
    }

    public static function getNavigationBadge(): ?string
    {
        $employeeId = Auth::user()->employee->id ?? null;
        if (!$employeeId) {
            return null;
        }

        $query = Procurement::whereNull('module')
            ->whereIn('status', ['Pending', 'Locked'])
            ->whereHas('children.approvals', function ($q) use ($employeeId) {
                $q->where('employee_id', $employeeId)
                  ->where('status', 'Pending');
            })
            ->whereHas('children', function ($q) {
                $q->where('module', 'ppmp')
                  ->whereHas('documents', function ($d) {
                      $d->where('module', 'ppmp');
                  });
            });

        return (string) $query->count();
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }
}