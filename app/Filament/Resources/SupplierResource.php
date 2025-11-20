<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Models\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use App\Helpers\ActivityLogger; 
class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Suppliers';

    protected static ?string $navigationGroup = 'Procurement Management';

    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
{
    return $form
        ->schema([
            Forms\Components\Section::make('Supplier Information')
                ->schema([
                    
                    Forms\Components\Group::make([
                        Forms\Components\TextInput::make('business_name')
                            ->label('Business Name')
                            ->required()
                            ->maxLength(255),
                    ])->columnSpan(1),
                    Forms\Components\Group::make([
                        Forms\Components\TextInput::make('contact_no')
                            ->label('Contact Number')
                            ->tel()
                            ->required(),
                    ])->columnSpan(1),

                    Forms\Components\Group::make([
                        Forms\Components\Textarea::make('business_address')
                            ->label('Business Address')
                            ->required()
                            ->rows(4),
                    ])->columnSpan(1),
                    Forms\Components\Group::make([
                        Forms\Components\TextInput::make('tin')
                            ->label('TIN')
                            ->maxLength(255),
                        Forms\Components\Checkbox::make('vat')
                            ->label('VAT Registered')
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state) {
                                if ($state) {
                                    $set('nvat', false);
                                }
                            }),
                        Forms\Components\Checkbox::make('nvat')
                            ->label('NVAT Registered')
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state) {
                                if ($state) {
                                    $set('vat', false);
                                }
                            }),
                    ])->columnSpan(1),

                    Forms\Components\Group::make([
                        Forms\Components\TextInput::make('email_address')
                            ->label('Email Address')
                            ->email()
                            ->required()
                            ->maxLength(255),
                    ])->columnSpan(1),
                    Forms\Components\Group::make([
                        Forms\Components\TextInput::make('philgeps_reg_no')
                            ->label('PhilGEPS Registration No.')
                            ->maxLength(255),
                    ])->columnSpan(1),

                    Forms\Components\Group::make([
                        Forms\Components\DatePicker::make('philgeps_expiry_date')
                            ->label('PhilGEPS Expiry Date')
                            ->native(false)
                            ->displayFormat('M d, Y')
                            ->helperText('When does the PhilGEPS registration expire?'),
                    ])->columnSpan(1),
                    Forms\Components\Group::make([
                        Forms\Components\TextInput::make('lbp_account_name')
                            ->label('LBP Account Name')
                            ->maxLength(255),
                    ])->columnSpan(1),

                    Forms\Components\Group::make([
                        Forms\Components\TextInput::make('lbp_account_number')
                            ->label('LBP Account Number')
                            ->maxLength(255),
                    ])->columnSpan(1),
                    Forms\Components\Group::make([])->columnSpan(1), 
                ])
                ->columns(2),
                    
                Forms\Components\Section::make('Categories')
                    ->schema([
                        Forms\Components\Select::make('categories')
                            ->multiple()
                            ->relationship('categories', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),
                    
                Forms\Components\Section::make('Supplier Documents')
                    ->description('Upload optional documents. These will be pre-filled when creating RFQ responses.')
                    ->schema([
                        // Mayor's Permit upload
                        Forms\Components\FileUpload::make('mayors_permit')
                            ->label("Mayor's Permit")
                            ->disk('public')
                            ->directory('supplier-documents')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->helperText('Optional: Upload if available')
                            ->getUploadedFileNameForStorageUsing(fn ($file, $record) => 
                                "supplier_" . ($record?->id ?? 'new') . "_mayors_permit_" . time() . ".{$file->getClientOriginalExtension()}"
                            )
                            ->afterStateHydrated(function (Forms\Components\FileUpload $component, $state) {
                                if (filled($state)) {
                                    // Handle malformed JSON from database
                                    if (is_string($state) && str_starts_with($state, '{')) {
                                        $decoded = json_decode($state, true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            $path = reset($decoded);
                                            if ($path !== false && is_string($path)) {
                                                $component->state([$path]);
                                                return;
                                            }
                                        }
                                    }
                                    
                                    // Handle standard string path
                                    if (is_string($state)) {
                                        $component->state([$state]);
                                    }
                                }
                            })
                            ->dehydrateStateUsing(function ($state) {
                                if (is_array($state) && !empty($state)) {
                                    // Handle indexed array
                                    if (isset($state[0]) && is_string($state[0])) {
                                        return $state[0];
                                    }
                                    // Handle UUID-keyed array
                                    $firstValue = reset($state);
                                    if ($firstValue !== false && is_string($firstValue)) {
                                        return $firstValue;
                                    }
                                }
                                
                                return is_string($state) ? $state : null;
                            })
                            ->deleteUploadedFileUsing(function ($file) {
                                if (is_string($file)) {
                                    Storage::disk('public')->delete($file);
                                }
                            })
                            ->deletable(true)
                            ->downloadable(true),

                        // PhilGEPS Certificate upload
                        Forms\Components\FileUpload::make('philgeps_certificate')
                            ->label('PhilGEPS Certificate')
                            ->disk('public')
                            ->directory('supplier-documents')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->helperText('Optional: Upload if available')
                            ->getUploadedFileNameForStorageUsing(fn ($file, $record) => 
                                "supplier_" . ($record?->id ?? 'new') . "_philgeps_certificate_" . time() . ".{$file->getClientOriginalExtension()}"
                            )
                            ->afterStateHydrated(function (Forms\Components\FileUpload $component, $state) {
                                if (filled($state)) {
                                    // Handle malformed JSON from database
                                    if (is_string($state) && str_starts_with($state, '{')) {
                                        $decoded = json_decode($state, true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            $path = reset($decoded);
                                            if ($path !== false && is_string($path)) {
                                                $component->state([$path]);
                                                return;
                                            }
                                        }
                                    }
                                    
                                    // Handle standard string path
                                    if (is_string($state)) {
                                        $component->state([$state]);
                                    }
                                }
                            })
                            ->dehydrateStateUsing(function ($state) {
                                if (is_array($state) && !empty($state)) {
                                    // Handle indexed array
                                    if (isset($state[0]) && is_string($state[0])) {
                                        return $state[0];
                                    }
                                    // Handle UUID-keyed array
                                    $firstValue = reset($state);
                                    if ($firstValue !== false && is_string($firstValue)) {
                                        return $firstValue;
                                    }
                                }
                                
                                return is_string($state) ? $state : null;
                            })
                            ->deleteUploadedFileUsing(function ($file) {
                                if (is_string($file)) {
                                    Storage::disk('public')->delete($file);
                                }
                            })
                            ->deletable(true)
                            ->downloadable(true),

                        // Omnibus Sworn Statement upload
                        Forms\Components\FileUpload::make('omnibus_sworn_statement')
                            ->label('Omnibus Sworn Statement')
                            ->disk('public')
                            ->directory('supplier-documents')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->helperText('Optional: Upload if available')
                            ->getUploadedFileNameForStorageUsing(fn ($file, $record) => 
                                "supplier_" . ($record?->id ?? 'new') . "_omnibus_sworn_statement_" . time() . ".{$file->getClientOriginalExtension()}"
                            )
                            ->afterStateHydrated(function (Forms\Components\FileUpload $component, $state) {
                                if (filled($state)) {
                                    // Handle malformed JSON from database
                                    if (is_string($state) && str_starts_with($state, '{')) {
                                        $decoded = json_decode($state, true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            $path = reset($decoded);
                                            if ($path !== false && is_string($path)) {
                                                $component->state([$path]);
                                                return;
                                            }
                                        }
                                    }
                                    
                                    // Handle standard string path
                                    if (is_string($state)) {
                                        $component->state([$state]);
                                    }
                                }
                            })
                            ->dehydrateStateUsing(function ($state) {
                                if (is_array($state) && !empty($state)) {
                                    // Handle indexed array
                                    if (isset($state[0]) && is_string($state[0])) {
                                        return $state[0];
                                    }
                                    // Handle UUID-keyed array
                                    $firstValue = reset($state);
                                    if ($firstValue !== false && is_string($firstValue)) {
                                        return $firstValue;
                                    }
                                }
                                
                                return is_string($state) ? $state : null;
                            })
                            ->deleteUploadedFileUsing(function ($file) {
                                if (is_string($file)) {
                                    Storage::disk('public')->delete($file);
                                }
                            })
                            ->deletable(true)
                            ->downloadable(true),

                        // PCAB License upload
                        Forms\Components\FileUpload::make('pcab_license')
                            ->label('PCAB License')
                            ->disk('public')
                            ->directory('supplier-documents')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->helperText('Optional: For infrastructure categories')
                            ->getUploadedFileNameForStorageUsing(fn ($file, $record) => 
                                "supplier_" . ($record?->id ?? 'new') . "_pcab_license_" . time() . ".{$file->getClientOriginalExtension()}"
                            )
                            ->afterStateHydrated(function (Forms\Components\FileUpload $component, $state) {
                                if (filled($state)) {
                                    // Handle malformed JSON from database
                                    if (is_string($state) && str_starts_with($state, '{')) {
                                        $decoded = json_decode($state, true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            $path = reset($decoded);
                                            if ($path !== false && is_string($path)) {
                                                $component->state([$path]);
                                                return;
                                            }
                                        }
                                    }
                                    
                                    // Handle standard string path
                                    if (is_string($state)) {
                                        $component->state([$state]);
                                    }
                                }
                            })
                            ->dehydrateStateUsing(function ($state) {
                                if (is_array($state) && !empty($state)) {
                                    // Handle indexed array
                                    if (isset($state[0]) && is_string($state[0])) {
                                        return $state[0];
                                    }
                                    // Handle UUID-keyed array
                                    $firstValue = reset($state);
                                    if ($firstValue !== false && is_string($firstValue)) {
                                        return $firstValue;
                                    }
                                }
                                
                                return is_string($state) ? $state : null;
                            })
                            ->deleteUploadedFileUsing(function ($file) {
                                if (is_string($file)) {
                                    Storage::disk('public')->delete($file);
                                }
                            })
                            ->deletable(true)
                            ->downloadable(true),

                        // Professional License/CV upload
                        Forms\Components\FileUpload::make('professional_license_cv')
                            ->label('Professional License/CV')
                            ->disk('public')
                            ->directory('supplier-documents')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->helperText('Optional: For consulting categories')
                            ->getUploadedFileNameForStorageUsing(fn ($file, $record) => 
                                "supplier_" . ($record?->id ?? 'new') . "_professional_license_cv_" . time() . ".{$file->getClientOriginalExtension()}"
                            )
                            ->afterStateHydrated(function (Forms\Components\FileUpload $component, $state) {
                                if (filled($state)) {
                                    // Handle malformed JSON from database
                                    if (is_string($state) && str_starts_with($state, '{')) {
                                        $decoded = json_decode($state, true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            $path = reset($decoded);
                                            if ($path !== false && is_string($path)) {
                                                $component->state([$path]);
                                                return;
                                            }
                                        }
                                    }
                                    
                                    // Handle standard string path
                                    if (is_string($state)) {
                                        $component->state([$state]);
                                    }
                                }
                            })
                            ->dehydrateStateUsing(function ($state) {
                                if (is_array($state) && !empty($state)) {
                                    // Handle indexed array
                                    if (isset($state[0]) && is_string($state[0])) {
                                        return $state[0];
                                    }
                                    // Handle UUID-keyed array
                                    $firstValue = reset($state);
                                    if ($firstValue !== false && is_string($firstValue)) {
                                        return $firstValue;
                                    }
                                }
                                
                                return is_string($state) ? $state : null;
                            })
                            ->deleteUploadedFileUsing(function ($file) {
                                if (is_string($file)) {
                                    Storage::disk('public')->delete($file);
                                }
                            })
                            ->deletable(true)
                            ->downloadable(true),

                        // Terms & Conditions / Tech Specs upload
                        Forms\Components\FileUpload::make('terms_conditions_tech_specs')
                            ->label('Terms & Conditions / Tech Specs')
                            ->disk('public')
                            ->directory('supplier-documents')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->helperText('Optional: For catering/printing categories')
                            ->getUploadedFileNameForStorageUsing(fn ($file, $record) => 
                                "supplier_" . ($record?->id ?? 'new') . "_terms_conditions_tech_specs_" . time() . ".{$file->getClientOriginalExtension()}"
                            )
                            ->afterStateHydrated(function (Forms\Components\FileUpload $component, $state) {
                                if (filled($state)) {
                                    // Handle malformed JSON from database
                                    if (is_string($state) && str_starts_with($state, '{')) {
                                        $decoded = json_decode($state, true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            $path = reset($decoded);
                                            if ($path !== false && is_string($path)) {
                                                $component->state([$path]);
                                                return;
                                            }
                                        }
                                    }
                                    
                                    // Handle standard string path
                                    if (is_string($state)) {
                                        $component->state([$state]);
                                    }
                                }
                            })
                            ->dehydrateStateUsing(function ($state) {
                                if (is_array($state) && !empty($state)) {
                                    // Handle indexed array
                                    if (isset($state[0]) && is_string($state[0])) {
                                        return $state[0];
                                    }
                                    // Handle UUID-keyed array
                                    $firstValue = reset($state);
                                    if ($firstValue !== false && is_string($firstValue)) {
                                        return $firstValue;
                                    }
                                }
                                
                                return is_string($state) ? $state : null;
                            })
                            ->deleteUploadedFileUsing(function ($file) {
                                if (is_string($file)) {
                                    Storage::disk('public')->delete($file);
                                }
                            })
                            ->deletable(true)
                            ->downloadable(true),

                        // Tax Return upload
                        Forms\Components\FileUpload::make('tax_return')
                            ->label('Tax Return')
                            ->disk('public')
                            ->directory('supplier-documents')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240)
                            ->helperText('Optional: For ABC > 500,000')
                            ->getUploadedFileNameForStorageUsing(fn ($file, $record) => 
                                "supplier_" . ($record?->id ?? 'new') . "_tax_return_" . time() . ".{$file->getClientOriginalExtension()}"
                            )
                            ->afterStateHydrated(function (Forms\Components\FileUpload $component, $state) {
                                if (filled($state)) {
                                    // Handle malformed JSON from database
                                    if (is_string($state) && str_starts_with($state, '{')) {
                                        $decoded = json_decode($state, true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            $path = reset($decoded);
                                            if ($path !== false && is_string($path)) {
                                                $component->state([$path]);
                                                return;
                                            }
                                        }
                                    }
                                    
                                    // Handle standard string path
                                    if (is_string($state)) {
                                        $component->state([$state]);
                                    }
                                }
                            })
                            ->dehydrateStateUsing(function ($state) {
                                if (is_array($state) && !empty($state)) {
                                    // Handle indexed array
                                    if (isset($state[0]) && is_string($state[0])) {
                                        return $state[0];
                                    }
                                    // Handle UUID-keyed array
                                    $firstValue = reset($state);
                                    if ($firstValue !== false && is_string($firstValue)) {
                                        return $firstValue;
                                    }
                                }
                                
                                return is_string($state) ? $state : null;
                            })
                            ->deleteUploadedFileUsing(function ($file) {
                                if (is_string($file)) {
                                    Storage::disk('public')->delete($file);
                                }
                            })
                            ->deletable(true)
                            ->downloadable(true),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('business_name')
                    ->label('Business Name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('contact_no')
                    ->label('Contact Number')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('email_address')
                    ->label('Email Address')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('philgeps_reg_no')
                    ->label('PhilGEPS Reg. No.')
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('philgeps_expiry_date')
                    ->label('PhilGEPS Expiry')
                    ->formatStateUsing(function ($record) {
                        if (!$record->philgeps_expiry_date) {
                            return '<span class="text-gray-500 text-sm">No expiry date</span>';
                        }
                        
                        $status = $record->philgeps_status;
                        $badgeColors = [
                            'expired' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                            'expiring_soon' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100',
                            'valid' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                            'unknown' => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100',
                        ];
                        
                        $color = $badgeColors[$status['status']] ?? $badgeColors['unknown'];
                        
                        return '<div class="flex flex-col gap-1">' .
                            '<span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full ' . $color . '">' .
                            $status['label'] .
                            '</span>' .
                            '<span class="text-xs text-gray-600 dark:text-gray-400">' . ($status['date'] ?? '') . '</span>' .
                            '</div>';
                    })
                    ->html()
                    ->sortable(),
                    
                Tables\Columns\IconColumn::make('vat')
                    ->label('VAT')
                    ->boolean(),
                    
                Tables\Columns\IconColumn::make('nvat')
                    ->label('NVAT')
                    ->boolean(),
                    
                Tables\Columns\TextColumn::make('categories.name')
                    ->label('Categories')
                    ->listWithLineBreaks()
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload(),
                Tables\Filters\Filter::make('philgeps_expiry')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options([
                                'expired' => 'Expired',
                                'expiring_soon' => 'Expiring Soon (30 days)',
                                'valid' => 'Valid',
                            ])
                            ->placeholder('All statuses'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['status'] === 'expired',
                            fn (Builder $query) => $query->whereDate('philgeps_expiry_date', '<', now())
                        )->when(
                            $data['status'] === 'expiring_soon',
                            fn (Builder $query) => $query->whereDate('philgeps_expiry_date', '>=', now())
                                ->whereDate('philgeps_expiry_date', '<=', now()->addDays(30))
                        )->when(
                            $data['status'] === 'valid',
                            fn (Builder $query) => $query->whereDate('philgeps_expiry_date', '>', now()->addDays(30))
                        );
                    }),
            ])
            ->actions([
    Tables\Actions\EditAction::make()
        ->after(function ($record) {
            $supplierName = $record->name 
                ?? $record->business_name 
                ?? 'Unnamed Supplier';

            \App\Helpers\ActivityLogger::log(
                'Updated Supplier',
                "Supplier '{$supplierName}' was updated."
            );
        }),


                Tables\Actions\DeleteAction::make()
    ->after(function ($record) {
        $supplierName = $record->business_name ?? 'Unnamed Supplier';
        \App\Helpers\ActivityLogger::log(
            'Deleted Supplier',
            "Supplier '{$supplierName}' was deleted."
        );
    })
    ->successNotification(
        Notification::make()
            ->success()
            ->title('Supplier deleted')
            ->body('The supplier has been deleted successfully.')
    )
    ->failureNotification(
        Notification::make()
            ->danger()
            ->title('Delete failed')
            ->body('Failed to delete the supplier. Please try again.')
    ),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}