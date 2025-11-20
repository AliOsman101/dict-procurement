<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RejectedProcurementResource\Pages;
use App\Models\Procurement;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class RejectedProcurementResource extends Resource
{
    protected static ?string $model = Procurement::class;

    protected static ?string $navigationIcon = 'heroicon-o-x-circle';

    protected static ?string $navigationLabel = 'Rejected Procurements';

    protected static ?string $navigationGroup = 'Procurement Management';

    protected static ?int $navigationSort = 3;
    
    protected static bool $shouldRegisterNavigation = true;

    public static function form(Form $form): Form
    {
        return $form->schema([]); // Read-only
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
                    ->searchable()
                    ->default('N/A'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date Created')
                    ->date('Y-m-d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('rejected_at')
                    ->label('Rejected At')
                    ->date('Y-m-d H:i')
                    ->getStateUsing(function ($record) {
                        // Get rejection from child modules
                        $rejectedApproval = \App\Models\Approval::whereIn('procurement_id', 
                            $record->children()->pluck('id')
                        )
                        ->where('status', 'Rejected')
                        ->orderBy('action_at', 'desc')
                        ->first();
                        
                        return $rejectedApproval?->action_at;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('rejected_by')
                    ->label('Rejected By')
                    ->getStateUsing(function ($record) {
                        // Get rejection from child modules
                        $rejectedApproval = \App\Models\Approval::whereIn('procurement_id', 
                            $record->children()->pluck('id')
                        )
                        ->where('status', 'Rejected')
                        ->with('employee')
                        ->orderBy('action_at', 'desc')
                        ->first();
                        
                        return $rejectedApproval?->employee->full_name ?? 'N/A';
                    })
                    ->sortable(),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $user = Auth::user();
                
                // Check if user is admin
                $isAdmin = $user->hasRole('admin');
                
                if ($isAdmin) {
                    // Admin sees ALL rejected procurements (excluding revised ones that are now Pending)
                    $query->whereNull('module')
                        ->where('status', 'Rejected')
                        ->whereDoesntHave('children', function ($childQuery) {
                            // Exclude procurements where any child was revised back to Pending
                            $childQuery->whereIn('module', [
                                'purchase_request',
                                'request_for_quotation',
                                'abstract_of_quotation',
                                'bac_resolution_recommending_award',
                                'purchase_order'
                            ])->where('status', 'Pending')
                            ->whereExists(function ($approvalQuery) {
                                $approvalQuery->selectRaw('1')
                                    ->from('approvals')
                                    ->whereColumn('approvals.procurement_id', 'procurements.id')
                                    ->where('approvals.status', 'Pending');
                            });
                        });
                } else {
                    // Regular employees see only rejected procurements they're involved in (excluding revised)
                    $employeeId = $user->employee->id ?? null;
                    if ($employeeId) {
                        $query->whereNull('module')
                            ->where('status', 'Rejected')
                            ->whereHas('children.approvals', function ($q) use ($employeeId) {
                                $q->where('employee_id', $employeeId);
                            })
                            ->whereDoesntHave('children', function ($childQuery) {
                                // Exclude procurements where any child was revised back to Pending
                                $childQuery->whereIn('module', [
                                    'purchase_request',
                                    'request_for_quotation',
                                    'abstract_of_quotation',
                                    'bac_resolution_recommending_award',
                                    'purchase_order'
                                ])->where('status', 'Pending')
                                ->whereExists(function ($approvalQuery) {
                                    $approvalQuery->selectRaw('1')
                                        ->from('approvals')
                                        ->whereColumn('approvals.procurement_id', 'procurements.id')
                                        ->where('approvals.status', 'Pending');
                                });
                            });
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                }
                
                return $query;
            })
            ->filters([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View')
                    ->url(fn ($record) => Pages\RejectedProcurementResourceView::getUrl(['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRejectedProcurements::route('/'),
            'view' => Pages\RejectedProcurementResourceView::route('/{record}'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        $employee = $user?->employee;
        // Show to all employees who have an employee record
        return $employee !== null;
    }

    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');
        
        if ($isAdmin) {
            // Admin sees count of ALL rejected procurements
            $count = Procurement::whereNull('module')
                ->where('status', 'Rejected')
                ->count();
        } else {
            // Regular employees see count of rejected procurements they're involved in
            $employeeId = $user->employee->id ?? null;
            if (!$employeeId) {
                return null;
            }
            
            $count = Procurement::whereNull('module')
                ->where('status', 'Rejected')
                ->whereHas('children.approvals', function ($q) use ($employeeId) {
                    $q->where('employee_id', $employeeId);
                })
                ->count();
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }
}