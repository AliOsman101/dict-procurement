<?php
namespace App\Filament\Resources\ProcurementResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ApprovalsRelationManager extends RelationManager
{
    protected static string $relationship = 'approvals';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->relationship('employee', 'full_name')
                    ->required(),
                Forms\Components\TextInput::make('sequence')
                    ->numeric()
                    ->required()
                    ->minValue(1),
                Forms\Components\TextInput::make('designation')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Approved' => 'Approved',
                        'Rejected' => 'Rejected',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('remarks')
                    ->nullable(),
                Forms\Components\DateTimePicker::make('date_approved')
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name'),
                Tables\Columns\TextColumn::make('sequence'),
                Tables\Columns\TextColumn::make('designation'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Pending' => 'warning',
                        'Approved' => 'success',
                        'Rejected' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('remarks')
                    ->default('N/A'),
                Tables\Columns\TextColumn::make('date_approved')
                    ->dateTime('Y-m-d H:i:s')
                    ->default('N/A'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn ($record) => $record->status === 'Pending' && Auth::user()->employee?->id === $record->employee_id)
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'Approved',
                            'date_approved' => now(),
                        ]);
                        Notification::make()->title('Approval submitted')->success()->send();

                        // Check AOQ approval logic
                        if ($record->procurement->module === 'abstract_of_quotation') {
                            $approvals = $record->procurement->approvals()->where('status', 'Approved')->get();
                            $hasChairOrVice = $approvals->contains(fn ($approval) => 
                                in_array($approval->designation, ['BAC Chairperson', 'BAC Vice-Chairperson']));
                            if ($approvals->count() >= 3 && $hasChairOrVice) {
                                $year = now()->year;
                                $month = now()->format('m');
                                $count = $record->procurement->where('module', 'abstract_of_quotation')
                                    ->whereYear('created_at', $year)
                                    ->whereMonth('created_at', $month)
                                    ->count() + 1;
                                $record->procurement->update([
                                    'procurement_id' => sprintf('AOQ-%s-%s-%03d', $year, $month, $count),
                                    'status' => 'Approved',
                                ]);
                            }
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }
}