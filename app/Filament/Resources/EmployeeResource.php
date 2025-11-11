<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Employee;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers\UserRelationManager;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Employee Profile';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $user = auth()->user();

        return $form->schema([
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
                            'plantilla' => 'Plantilla',
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
                    Forms\Components\Select::make('office')
                        ->options([
                            'Region Office' => 'Region Office',
                            'Abra' => 'Abra',
                            'Apayao' => 'Apayao',
                            'Benguet' => 'Benguet',
                            'Ifugao' => 'Ifugao',
                            'Kalinga' => 'Kalinga',
                            'Mt. Province' => 'Mt. Province',
                        ])
                        ->required()
                        ->searchable(),
                    Forms\Components\TextInput::make('employee_no')
                        ->maxLength(20)
                        ->helperText('Make sure this is accurate and same Employee No used in biometric device.')
                        ->unique(ignoreRecord: true)
                        ->required(),
                    Forms\Components\TextInput::make('photo')
                        ->required()
                        ->hidden()
                        ->maxLength(55)
                        ->default('/uploads/profilepicture/profile.png'),
                ]),

            Forms\Components\Fieldset::make('Other Information')
                ->schema([

                    Forms\Components\Select::make('supervisor_id')
                    ->label('Supervisor')
                    ->options(Employee::all()->mapWithKeys(function ($employee) {
                        return [$employee->id => $employee->full_name];
                    }))
                    ->required()
                    ->reactive(),
                
                    
                    Forms\Components\TextInput::make('supervisor')
                    // ->required()
                    ->maxLength(255)
                    ->visible(fn () => auth()->id() === 2),
                
                    Forms\Components\TextInput::make('middlename')
                        ->maxLength(50),
                    Forms\Components\TextInput::make('designation')
                        ->maxLength(50),
                    Forms\Components\DatePicker::make('birthday'),
                    Forms\Components\TextInput::make('mobile')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('gsis_no')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('tin')
                        ->maxLength(255),
                    Forms\Components\Checkbox::make('is_active')
                        ->label('Active')
                        ->visible($user && $user->hasAnyRole(['admin', 'hr-admin'])),
                    Forms\Components\DatePicker::make('entrance_to_duty'),


                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable()
                    ->url(fn($record) => route('filament.admin.resources.users.view', $record->user_id)),
                Tables\Columns\TextColumn::make('firstname')->searchable(),
                Tables\Columns\TextColumn::make('middlename')->searchable(),
                Tables\Columns\TextColumn::make('lastname')->searchable(),
                Tables\Columns\TextColumn::make('employee_no')->searchable(),
                //            Tables\Columns\TextColumn::make('employee_dtr_no')->numeric()->sortable(),
                // Tables\Columns\TextColumn::make('civil_status'),
                Tables\Columns\TextColumn::make('employment_status'),
                Tables\Columns\TextColumn::make('gender'),
                // Tables\Columns\TextColumn::make('designation')->searchable(),
                // Tables\Columns\TextColumn::make('division.name')->numeric()->sortable(),
                // Tables\Columns\TextColumn::make('position.name')->numeric()->sortable(),
                // Tables\Columns\TextColumn::make('project.name')->numeric()->sortable(),
                // Tables\Columns\TextColumn::make('supervisor')->searchable(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->visible($user && $user->hasAnyRole(['admin', 'hr-admin']))
                    ->sortable(),
                // Tables\Columns\TextColumn::make('entrance_to_duty')->date()->sortable(),

                Tables\Columns\TextColumn::make('office'),
                // Tables\Columns\TextColumn::make('photo')->searchable(),
                // Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                // Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('civil_status')
                    ->options([
                        'single' => 'Single',
                        'married' => 'Married',
                        'widowed' => 'Widowed',
                        'divorced' => 'Divorced',
                    ])
                    ->label('Civil Status'),
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ])
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();

                // Ensure the user only sees their associated employee record
                if (!$user->hasAnyRole(['hr-admin'])) {
                    $query->where('user_id', $user->id);
                }

                return $query;
            });
    }

    public static function getRelations(): array
    {
        return [
            UserRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
            'view' => Pages\ViewEmployee::route('/{record}'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()?->employee !== null;
    }
}
