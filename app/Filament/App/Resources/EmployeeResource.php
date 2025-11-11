<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // Customize the navigation URL

    public static function getNavigationUrl(): string
    {
        $user = Auth::user();
        $employee = $user->employee;

        if ($employee) {
            return route('filament.app.resources.employees.edit', $employee->id);
        }

        // If the user does not have an associated employee, fall back to the list page
        return route('filament.app.resources.employees.index');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Fieldset::make('Required Information')
                    ->schema([
                        Forms\Components\TextInput::make('firstname')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\TextInput::make('lastname')
                            ->required()
                            ->maxLength(50),
                        Forms\Components\Select::make('civil_status')
                            ->options([
                                'single' => 'Single',
                                'married' => 'Married',
                                'widowed' => 'Widowed',
                                'divorced' => 'Divorced',
                            ])
                            ->required(),
                        Forms\Components\Select::make('employment_status')
                            ->options([
                                'jo' => 'Job Order',
                                'regular' => 'Regular',
                                'probationary' => 'Probationary',
                            ])
                            ->required(),
                        Forms\Components\Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                            ])
                            ->required(),
                        Forms\Components\Select::make('division_id')
                            ->relationship('division', 'name')
                            ->required(),
                        Forms\Components\Select::make('position_id')
                            ->relationship('position', 'name')
                            ->required(),
                        Forms\Components\Select::make('project_id')
                            ->relationship('project', 'name')
                            ->required(),

                        Forms\Components\Select::make('region')
                            ->options([
                                'CAR' => 'CAR',
                                'Region 1' => 'Region 1',
                                'Region 2' => 'Region 2',
                            ])
                            ->required()
                            ->default('CAR'),
                        Forms\Components\TextInput::make('photo')
                            ->required()
                            ->maxLength(55)
                            ->default('/uploads/profilepicture/profile.png'),
                        //user id is modified in beforeasave
                    ]),
                // Other fields
                Forms\Components\Fieldset::make('Other Information')
                    ->schema([
                        Forms\Components\TextInput::make('middlename')
                            ->maxLength(50)
                            ->default(null),
                        Forms\Components\TextInput::make('employee_no')
                            ->maxLength(20)
                            ->default(null),

                        Forms\Components\TextInput::make('designation')
                            ->maxLength(50)
                            ->default(null),
                        Forms\Components\DatePicker::make('birthday'),
                        Forms\Components\TextInput::make('mobile')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('gsis_no')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('tin')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\TextInput::make('supervisor')
                            ->maxLength(255)
                            ->default(null),
                        Forms\Components\Checkbox::make('is_active'),
                        Forms\Components\DatePicker::make('entrance_to_duty'),
                        Forms\Components\TextInput::make('office'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('firstname')
                    ->searchable(),
                Tables\Columns\TextColumn::make('middlename')
                    ->searchable(),
                Tables\Columns\TextColumn::make('lastname')
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee_no')
                    ->searchable(),

                Tables\Columns\TextColumn::make('civil_status'),
                Tables\Columns\TextColumn::make('employment_status'),
                Tables\Columns\TextColumn::make('gender'),
                Tables\Columns\TextColumn::make('designation')
                    ->searchable(),
                Tables\Columns\TextColumn::make('division_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('position_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('project_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('birthday')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('mobile')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gsis_no')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tin')
                    ->searchable(),
                Tables\Columns\TextColumn::make('supervisor')
                    ->searchable(),
                Tables\Columns\CheckboxColumn::make('is_active')
                    ->sortable(),
                Tables\Columns\TextColumn::make('entrance_to_duty')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('region'),
                Tables\Columns\TextColumn::make('office'),
                Tables\Columns\TextColumn::make('photo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
