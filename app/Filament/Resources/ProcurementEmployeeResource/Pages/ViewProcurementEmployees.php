<?php

namespace App\Filament\Resources\ProcurementEmployeeResource\Pages;

use App\Filament\Resources\ProcurementEmployeeResource;
use App\Models\Procurement;
use App\Models\ProcurementEmployee;
use App\Models\Employee;
use Filament\Forms;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ViewProcurementEmployees extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ProcurementEmployeeResource::class;
    protected static string $view = 'filament.resources.procurement-employee-resource.pages.view-procurement-employees';

    public $record;

    public function mount($record): void
    {
        $this->record = Procurement::findOrFail($record);
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $isCreator = $user && $this->record->created_by == $user->id;

        return $table
            ->query(
                ProcurementEmployee::query()
                    ->where('procurement_id', $this->record->id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('employee.firstname')->label('First Name')->sortable(),
                Tables\Columns\TextColumn::make('employee.lastname')->label('Last Name')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $isCreator) // Only creator can delete
                    ->after(function ($record) {
                        \App\Helpers\ActivityLogger::log(
                            'Removed Procurement Employee',
                            "Employee '{$record->employee->full_name}' was removed from Procurement {$this->record->procurement_id} by " . Auth::user()->name
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn () => $isCreator)
                    ->after(function ($records) {
                        foreach ($records as $record) {
                            \App\Helpers\ActivityLogger::log(
                                'Bulk Removed Procurement Employees',
                                "Employee '{$record->employee->full_name}' was removed from Procurement {$this->record->procurement_id} by " . Auth::user()->name
                            );
                        }
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->model(ProcurementEmployee::class)
                    ->label('Add Employee')
                    ->visible(fn () => $isCreator) // Only creator can add
                    ->form(function () {
                        $assignedEmployeeIds = ProcurementEmployee::where('procurement_id', $this->record->id)
                            ->pluck('employee_id')
                            ->toArray();

                        return [
                            Forms\Components\Hidden::make('procurement_id')
                                ->default(fn () => $this->record->id),

                            Forms\Components\Select::make('employee_id')
                                ->label('Select Employee')
                                ->options(
                                    Employee::query()
                                        ->whereNotIn('id', $assignedEmployeeIds)
                                        ->selectRaw("id, CONCAT(firstname, ' ', COALESCE(middlename, ''), ' ', lastname) as name")
                                        ->pluck('name', 'id')
                                )
                                ->required()
                                ->searchable()
                                ->preload(),
                        ];
                    })
                    ->createAnother(false)
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_at'] = now();
                        return $data;
                    })
                    ->after(function ($record) {
                        \App\Helpers\ActivityLogger::log(
                            'Added Procurement Employee',
                            "Employee '{$record->employee->full_name}' was added to Procurement {$this->record->procurement_id} by " . Auth::user()->name
                        );
                    }),
            ]);
        }
}