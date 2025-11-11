<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeCertificateResource\Pages;
use App\Models\EmployeeCertificate;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Redirect;
use Saade\FilamentAutograph\Forms\Components\SignaturePad;
use App\Models\Employee;

class EmployeeCertificateResource extends Resource
{
    protected static ?string $model = EmployeeCertificate::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Employee Profile';
    protected static ?int $navigationSort = 2;

    

    
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('employee_name')
                    ->label('Employee')
                    ->default(auth()->user()->employee->full_name)
                    ->disabled(),
    
                Forms\Components\Hidden::make('employee_id')
                    ->default(auth()->user()->employee->id),
    
                Forms\Components\FileUpload::make('p12_file')
                    ->label('.p12 File')
                    ->required()
                    ->directory('p12-files')
                    ->acceptedFileTypes(['application/x-pkcs12']),
    
                Forms\Components\TextInput::make('p12_password')
                    ->label('.p12 Password')
                    ->password()
                    ->required(),
    
                Forms\Components\Radio::make('signature_type')
                    ->label('Signature Type')
                    ->options([
                        'draw' => 'Draw Signature',
                        'upload' => 'Upload Image',
                    ])
                    ->default('draw')
                    ->reactive(),
    
                SignaturePad::make('signature_image')
                    ->label('Draw Signature')
                    ->backgroundColor('white')
                    ->penColor('black')
                    ->hidden(fn ($get) => $get('signature_type') !== 'draw')
                    ->required(fn ($get) => $get('signature_type') === 'draw'),
    
                Forms\Components\FileUpload::make('signature_file')
                    ->label('Upload Signature Image')
                    ->directory('signatures')
                    ->acceptedFileTypes(['image/png', 'image/jpeg'])
                    ->hidden(fn ($get) => $get('signature_type') !== 'upload')
                    ->required(fn ($get) => $get('signature_type') === 'upload'),
            ]);
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Tables\Columns\TextColumn::make('id')->label('ID'),
                Tables\Columns\TextColumn::make('employee_id')->label('Employee ID'),
                Tables\Columns\TextColumn::make('employee.full_name')->label('Employee'),
                // Tables\Columns\TextColumn::make('signature_image_path')->label('Signature Image'),
            ])
            ->filters([
                //
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\DeleteBulkAction::make(),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                // Restrict access to only the employee's own certificate
                return $query->where('employee_id', $user->employee->id);
            });
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
            'index' => Pages\ListEmployeeCertificates::route('/'),
            'create' => Pages\CreateEmployeeCertificate::route('/create'),
            // 'edit' => Pages\EditEmployeeCertificate::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        // Ensure the user has an employee relationship and a valid employee number
        return $user?->employee !== null && $user->employee->employee_no !== null;
    }
}