<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompletedProcurementResource\Pages;
use App\Models\Procurement;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompletedProcurementResource extends Resource
{
    protected static ?string $model = Procurement::class;
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?string $navigationLabel = 'Completed Procurements';
    protected static ?string $navigationGroup = 'Procurement Management';
    protected static ?int $navigationSort = 4;

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
                    ->sortable(
                        query: fn (Builder $query) => $query
                            ->leftJoin('procurements as pr', 'procurements.id', '=', 'pr.parent_id')
                            ->where('pr.module', 'purchase_request')
                            ->orderBy('pr.requested_by')
                    )
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        // Find Purchase Request child
                        $pr = $record->children()->where('module', 'purchase_request')->first();

                        // Return Requester's name or "Not set"
                        return $pr && $pr->requester
                            ? $pr->requester->full_name
                            : 'Not set';
                    }),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed At')
                    ->dateTime('M d, Y - h:i A')
                    ->sortable(
                        query: function (Builder $query, string $direction) {
                            return $query->orderBy(
                                DB::raw('(
                                    SELECT MAX(action_at)
                                    FROM approvals
                                    INNER JOIN procurements AS child ON approvals.procurement_id = child.id
                                    WHERE child.parent_id = procurements.id
                                    AND child.module = "purchase_order"
                                    AND approvals.status = "Approved"
                                )'),
                                $direction
                            );
                        }
                    )
                    ->getStateUsing(fn ($record) => $record->children()
                        ->where('module', 'purchase_order')
                        ->first()?->approvals()
                        ->where('status', 'Approved')
                        ->orderByDesc('action_at')
                        ->first()?->action_at
                    ),
            ])
            ->modifyQueryUsing(fn (Builder $query) => self::applyCompletedFilter($query))
            ->filters([
                Tables\Filters\SelectFilter::make('fund_cluster_id')
                    ->relationship('fundCluster', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View')
                    ->url(fn ($record) => \App\Filament\Resources\ProcurementResource\Pages\ProcurementView::getUrl([
                            'record' => $record,
                            'view_mode' => 'completed'
                        ])),
            ])
            ->bulkActions([])
            ->defaultSort('completed_at', 'desc');
    }

    /**
     * Apply filter to show only completed procurements
     * Completed = PPMP uploaded AND all modules approved by all approvers
     */
    public static function applyCompletedFilter(Builder $query): Builder
    {
        return $query
            ->whereNull('module')
            // PPMP must be uploaded
            ->whereHas('children', fn ($q) => $q
                ->where('module', 'ppmp')
                ->whereHas('documents', fn ($d) => $d->where('module', 'ppmp'))
            )
            // All required modules must have approved approvals
            ->where(function ($q) {
                $requiredModules = [
                    'purchase_request',
                    'request_for_quotation',
                    'abstract_of_quotation',
                    'bac_resolution_recommending_award',
                    'purchase_order'
                ];

                foreach ($requiredModules as $module) {
                    $q->whereHas('children', fn ($sub) => $sub
                        ->where('module', $module)
                        ->whereHas('approvals', fn ($a) => $a->where('status', 'Approved'))
                    );
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompletedProcurements::route('/'),
            // Reuse ProcurementView
            'view' => \App\Filament\Resources\ProcurementResource\Pages\ProcurementView::route('/{record}?view_mode=completed'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check();
    }

    public static function getNavigationBadge(): ?string
    {
        if (!Auth::check()) return null;

        return (string) self::applyCompletedFilter(Procurement::query())->count();
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'success';
    }
}