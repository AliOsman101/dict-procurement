<?php

namespace App\Filament\Resources\EmployeeCertificateResource\Pages;

use App\Filament\Resources\EmployeeCertificateResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use App\Models\Employee;
use App\Models\EmployeeCertificate;
use Filament\Forms;

class EditEmployeeCertificate extends EditRecord
{
    protected static string $resource = EmployeeCertificateResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        try {
            // Ensure the employee_id is set based on the authenticated user if the user is not an admin
            if (!Auth::user()->hasRole('admin')) {
                $data['employee_id'] = Auth::user()->employee->id;
            }

            // Check if the employee already has a certificate, excluding the current record
            $existingCertificate = EmployeeCertificate::where('employee_id', $data['employee_id'])
                ->where('id', '!=', $this->record->id)
                ->first();
            if ($existingCertificate) {
                throw new Exception('This employee already has a certificate.');
            }

            // Handle the uploaded .p12 file and extract data
            $p12FilePath = Storage::path('public/' . $data['p12_file']);
            $p12Password = $data['p12_password'];

            // Extract values from the .p12 file
            $p12Content = file_get_contents($p12FilePath);
            $certs = [];
            if (!openssl_pkcs12_read($p12Content, $certs, $p12Password)) {
                throw new Exception('Unable to read the .p12 file. The password might be incorrect.');
            }

            $data['private_key'] = $certs['pkey'];
            $data['certificate'] = $certs['cert'];
            $data['intermediate_certificates'] = isset($certs['extracerts']) ? implode("\n", $certs['extracerts']) : null;

            // Store the signature image path
            $data['signature_image_path'] = $data['signature_image'];

            // Remove the fields that are not part of the database schema
            unset($data['p12_file'], $data['p12_password'], $data['signature_image']);
        } catch (Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();

            // Stop the save process by throwing a ValidationException
            throw ValidationException::withMessages([
                'employee_id' => [$e->getMessage()],
            ]);
        }

        return $data;
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('employee_id')
                ->label('Employee')
                ->options(Employee::all()->pluck('full_name', 'id'))
                ->default(Auth::user()->employee->id)
                ->required()
                ->hidden(fn () => !Auth::user()->hasRole('admin'))
                ->disabled(fn () => !Auth::user()->hasRole('admin')),
            Forms\Components\TextInput::make('employee_name')
                ->label('Employee')
                ->default(Auth::user()->employee->full_name)
                ->disabled()
                ->hidden(fn () => Auth::user()->hasRole('admin')),
            Forms\Components\FileUpload::make('p12_file')
                ->label('.p12 File')
                ->directory('p12-files')
                ->acceptedFileTypes(['application/x-pkcs12']),
            Forms\Components\TextInput::make('p12_password')
                ->label('.p12 Password')
                ->password(),
            Forms\Components\FileUpload::make('signature_image')
                ->label('Signature Image')
                ->directory('signatures')
                ->image(),
        ];
    }
}