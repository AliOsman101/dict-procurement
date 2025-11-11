<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CompletedProcurementResource\Pages;
use App\Models\Procurement;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CompletedProcurementResource extends Resource
{
    protected static ?string $model = Procurement::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';

    protected static ?string $navigationLabel = 'Completed Procurements';

    protected static ?string $navigationGroup = 'Procurement Management';

    protected static ?int $navigationSort = 4;

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
                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('Created By')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date Created')
                    ->date('Y-m-d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed At')
                    ->date('Y-m-d H:i')
                    ->getStateUsing(function ($record) {
                        return $record->approvals()
                            ->where('status', 'Approved')
                            ->orderBy('date_approved', 'desc')
                            ->first()?->date_approved;
                    })
                    ->sortable(),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $employeeId = Auth::user()->employee->id ?? null;
                if ($employeeId) {
                    $query->whereHas('approvals', function ($q) use ($employeeId) {
                        $q->where('employee_id', $employeeId);
                    })->where('status', 'Approved');
                } else {
                    $query->whereRaw('1 = 0');
                }
                return $query;
            })
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Approved' => 'Approved',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('View')
                    ->url(fn ($record) => Pages\ViewCompletedProcurement::getUrl(['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCompletedProcurements::route('/'),
            'view' => Pages\ViewCompletedProcurement::route('/{record}'),
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
        return $employeeId ? (string) \App\Models\Procurement::whereHas('approvals', function ($q) use ($employeeId) {
            $q->where('employee_id', $employeeId);
        })->where('status', 'Approved')->count() : null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'success';
    }
}