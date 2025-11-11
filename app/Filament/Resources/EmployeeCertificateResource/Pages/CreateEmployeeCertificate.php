<?php

namespace App\Filament\Resources\EmployeeCertificateResource\Pages;

use Exception;
use App\Models\EmployeeCertificate;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use App\Filament\Resources\EmployeeCertificateResource;
use Illuminate\Support\Facades\Crypt;
class CreateEmployeeCertificate extends CreateRecord
{
    protected static string $resource = EmployeeCertificateResource::class;
    

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            // Ensure the employee_id is set based on the authenticated user
            $data['employee_id'] = auth()->user()->employee->id;

            // Check if the employee already has a certificate
            $existingCertificate = EmployeeCertificate::where('employee_id', $data['employee_id'])->first();
            if ($existingCertificate) {
                Notification::make()
                    ->title('Error')
                    ->body('This employee already has a certificate.')
                    ->danger()
                    ->send();

                return [];
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

            // Encrypt sensitive data before storing
            $data['private_key'] = Crypt::encryptString($certs['pkey']);  // Encrypt private key
            $data['certificate'] = Crypt::encryptString($certs['cert']);  // Encrypt certificate
            $data['intermediate_certificates'] = isset($certs['extracerts'])
                ? Crypt::encryptString(implode("\n", $certs['extracerts']))
                : null;
             // Handle Signature Encryption
        if (!empty($data['signature_image']) && $data['signature_type'] === 'draw') {
            // Remove prefix and encrypt SignaturePad base64 image
            $base64Signature = preg_replace('/^data:image\/\w+;base64,/', '', $data['signature_image']);
            $data['signature_image_path'] = Crypt::encryptString($base64Signature);
        } elseif (!empty($data['signature_file']) && $data['signature_type'] === 'upload') {
            // Convert uploaded file to base64 and encrypt
            $filePath = Storage::path('public/' . $data['signature_file']);
            $fileContent = file_get_contents($filePath);
            $base64Signature = base64_encode($fileContent);
            $data['signature_image_path'] = Crypt::encryptString($base64Signature);

        
        }


            // Remove unnecessary fields before saving
            unset($data['p12_file'], $data['p12_password'], $data['signature_image']);

      
        } catch (Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'p12_password' => $e->getMessage(),
            ]);
        }
    // Delete the uploaded file after encryption
            \File::cleanDirectory(public_path('storage/signatures'));
            \File::cleanDirectory(public_path('storage/p12-files'));
            
        return $data;
    }
}