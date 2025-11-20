<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeCertificateResource\Pages;
use App\Models\EmployeeCertificate;
use App\Models\Employee;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Saade\FilamentAutograph\Forms\Components\SignaturePad;

class EmployeeCertificateResource extends Resource
{
    protected static ?string $model = EmployeeCertificate::class;
    protected static ?string $navigationIcon = 'heroicon-o-finger-print';
    protected static ?string $navigationGroup = 'Employee Profile';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Employee Signatures';

    public static function form(Form $form): Form
    {
        $isAdmin = Auth::check() && Auth::user()->hasRole('admin');
        $isCreating = $form->getOperation() === 'create';
        $isEditing = $form->getOperation() === 'edit';

        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->label('Employee')
                    ->options(function () use ($isAdmin) {
                        if (!$isAdmin) {
                            return [];
                        }
                        
                        return Employee::query()
                            ->whereNotNull('firstname')
                            ->whereNotNull('lastname')
                            ->whereHas('user', function ($query) {
                                $query->whereDoesntHave('roles', function ($q) {
                                    $q->where('name', 'admin');
                                });
                            })
                            ->orderBy('firstname')
                            ->orderBy('lastname')
                            ->get()
                            ->pluck('full_name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->required()
                    ->native(false)
                    ->visible($isAdmin && $isCreating)
                    ->dehydrated($isAdmin && $isCreating)
                    ->disabled($isEditing),

                Forms\Components\TextInput::make('employee_name')
                    ->label('Employee')
                    ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $record) {
                        if ($record && $record->employee) {
                            $name = trim($record->employee->firstname . ' ' . 
                                   ($record->employee->middlename ? $record->employee->middlename . ' ' : '') . 
                                   $record->employee->lastname);
                            $component->state($name);
                        } elseif (!$record) {
                            $employee = Auth::user()->employee;
                            if ($employee) {
                                $name = trim($employee->firstname . ' ' . 
                                       ($employee->middlename ? $employee->middlename . ' ' : '') . 
                                       $employee->lastname);
                                $component->state($name);
                            }
                        }
                    })
                    ->disabled()
                    ->visible(fn () => !$isAdmin || $isEditing)
                    ->dehydrated(false),

                Forms\Components\Hidden::make('employee_id')
                    ->default(function ($record) {
                        if ($record && $record->employee_id) {
                            return $record->employee_id;
                        }
                        return Auth::user()->employee?->id;
                    })
                    ->visible(fn () => !$isAdmin || $isEditing)
                    ->dehydrated(fn () => !$isAdmin || $isEditing),

                // Digital Certificate (Required)
                Forms\Components\Section::make('Digital Certificate (Required)')
                    ->description('Upload .p12 file and password for digital signing')
                    ->schema([
                        Forms\Components\FileUpload::make('p12_file_path')
                            ->label('.p12 File')
                            ->directory('p12-files')
                            ->acceptedFileTypes(['application/x-pkcs12', '.p12', '.pfx'])
                            ->maxSize(5120)
                            ->helperText('Upload your .p12 certificate file')
                            ->required()
                            ->reactive()
                            ->dehydrated(true),

                        Forms\Components\TextInput::make('p12_password')
                            ->label('.p12 Password (if required)')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state) => $state ? Crypt::encryptString($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->afterStateHydrated(function (Forms\Components\TextInput $component, $state) {
                                // Decrypt the password when loading for edit
                                if (filled($state)) {
                                    try {
                                        $decrypted = Crypt::decryptString($state);
                                        $component->state($decrypted);
                                    } catch (\Exception $e) {
                                        // If decryption fails, leave it as is (might be unencrypted old data)
                                        $component->state($state);
                                    }
                                }
                            })
                            ->autocomplete('new-password')
                            ->helperText('Enter password for your .p12 file if it has one, otherwise leave blank')
                            ->visible(fn ($get) => filled($get('p12_file_path'))),
                    ])
                    ->collapsible()
                    ->collapsed(),

                // Signature Section
                Forms\Components\Section::make('Signature (Required)')
                    ->description('Draw or upload your signature - THIS IS REQUIRED')
                    ->schema([
                        Forms\Components\Radio::make('signature_type')
                            ->label('How do you want to add your signature?')
                            ->options([
                                'draw' => 'Draw my signature',
                                'upload' => 'Upload an image',
                            ])
                            ->default('draw')
                            ->live()
                            ->required()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // Clear other field when switching
                                if ($state === 'draw') {
                                    $set('signature_file', null);
                                } else {
                                    $set('signature_image', null);
                                }
                            }),

                        // DRAW SIGNATURE
                        SignaturePad::make('signature_image')
                            ->label('Draw Your Signature Below')
                            ->backgroundColor('rgba(255,255,255,1)')
                            ->backgroundColorOnDark('rgba(255,255,255,1)')
                            ->exportBackgroundColor('rgba(255,255,255,1)')
                            ->penColor('#000000')
                            ->exportPenColor('#000000')
                            ->dotSize(2)
                            ->lineMinWidth(0.5)
                            ->lineMaxWidth(2.5)
                            ->clearable()
                            ->downloadable()
                            ->downloadActionDropdownPlacement('center-end')
                            ->confirmable(false)
                            ->visible(fn (Forms\Get $get) => $get('signature_type') === 'draw')
                            ->helperText('Draw your signature using your mouse or touchpad'),

                        // UPLOAD SIGNATURE
                        Forms\Components\FileUpload::make('signature_file')
                            ->label('Upload Signature Image')
                            ->directory('signatures')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/jpg'])
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->maxSize(2048)
                            ->imageResizeMode('contain')
                            ->imageCropAspectRatio('16:9')
                            ->imageResizeTargetWidth('800')
                            ->imageResizeTargetHeight('450')
                            ->rules([
                                'dimensions:min_width=200,min_height=50,max_width=2000,max_height=1000',
                            ])
                            ->validationMessages([
                                'dimensions' => 'The signature image must be between 200x50 and 2000x1000 pixels. Please upload a horizontal signature image with appropriate dimensions.',
                            ])
                            ->visible(fn (Forms\Get $get) => $get('signature_type') === 'upload')
                            ->helperText('Upload a PNG or JPEG image of your signature (max 2MB). Image should be horizontal/landscape orientation with width greater than height. Recommended: white/transparent background with black ink signature.'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(function ($record) {
                        $employee = $record->employee;
                        if (!$employee) return 'N/A';
                        
                        return trim($employee->firstname . ' ' . 
                               ($employee->middlename ? $employee->middlename . ' ' : '') . 
                               $employee->lastname);
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date Added')
                    ->dateTime('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(function ($record) {
                        $user = Auth::user();
                        // Only the employee who owns the signature can edit it
                        return $user->employee && $record->employee_id === $user->employee->id;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(function ($record) {
                        $user = Auth::user();
                        // Admin can delete any record
                        if ($user->hasRole('admin')) {
                            return true;
                        }
                        // Employee can only delete their own signature
                        return $user->employee && $record->employee_id === $user->employee->id;
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $user = Auth::user();
                if (!$user->hasRole('admin') && $user->employee) {
                    return $query->where('employee_id', $user->employee->id);
                }
                return $query;
            });
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEmployeeCertificates::route('/'),
            'create' => Pages\CreateEmployeeCertificate::route('/create'),
            'edit'   => Pages\EditEmployeeCertificate::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();
        
        // Admin can always create (for any employee)
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Employee can only create if they don't already have a signature
        if ($user->employee) {
            $existingSignature = EmployeeCertificate::where('employee_id', $user->employee->id)->first();
            return !$existingSignature;
        }
        
        return false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        return $user && ($user->hasRole('admin') || ($user->employee !== null));
    }
}