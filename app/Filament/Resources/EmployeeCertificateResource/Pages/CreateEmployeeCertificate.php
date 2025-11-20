<?php

namespace App\Filament\Resources\EmployeeCertificateResource\Pages;

use Exception;
use App\Models\EmployeeCertificate;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use App\Filament\Resources\EmployeeCertificateResource;
use Illuminate\Support\Facades\Crypt;
use Intervention\Image\Facades\Image;

class CreateEmployeeCertificate extends CreateRecord
{
    protected static string $resource = EmployeeCertificateResource::class;

    /**
     * Process and crop signature to remove white space
     * Returns base64 encoded cropped image
     */
    protected function processSignature($imageData): string
    {
        try {
            // Decode base64 to image
            if (strpos($imageData, 'data:image') === 0) {
                // Remove data URI prefix if present
                $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
            }
            
            $imageData = base64_decode($imageData);
            
            // Create image from string
            $image = imagecreatefromstring($imageData);
            if (!$image) {
                throw new Exception('Failed to process signature image');
            }
            
            $width = imagesx($image);
            $height = imagesy($image);
            
            // Find bounds of non-white pixels
            $minX = $width;
            $maxX = 0;
            $minY = $height;
            $maxY = 0;
            
            // Scan all pixels
            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < $width; $x++) {
                    $rgb = imagecolorat($image, $x, $y);
                    $colors = imagecolorsforindex($image, $rgb);
                    
                    // Check if pixel is NOT white/transparent (threshold 240 to catch light grays)
                    if ($colors['red'] < 240 || $colors['green'] < 240 || $colors['blue'] < 240) {
                        if ($x < $minX) $minX = $x;
                        if ($x > $maxX) $maxX = $x;
                        if ($y < $minY) $minY = $y;
                        if ($y > $maxY) $maxY = $y;
                    }
                }
            }
            
            // If no non-white pixels found, return original
            if ($minX >= $width || $minY >= $height) {
                imagedestroy($image);
                return base64_encode(imagecreatefromstring(base64_decode($imageData)));
            }
            
            // Add small padding (5 pixels)
            $padding = 5;
            $minX = max(0, $minX - $padding);
            $minY = max(0, $minY - $padding);
            $maxX = min($width - 1, $maxX + $padding);
            $maxY = min($height - 1, $maxY + $padding);
            
            // Calculate cropped dimensions
            $cropWidth = $maxX - $minX + 1;
            $cropHeight = $maxY - $minY + 1;
            
            // Create new cropped image
            $croppedImage = imagecreatetruecolor($cropWidth, $cropHeight);
            
            // Preserve transparency
            imagealphablending($croppedImage, false);
            imagesavealpha($croppedImage, true);
            $transparent = imagecolorallocatealpha($croppedImage, 255, 255, 255, 127);
            imagefill($croppedImage, 0, 0, $transparent);
            imagealphablending($croppedImage, true);
            
            // Copy cropped portion
            imagecopy($croppedImage, $image, 0, 0, $minX, $minY, $cropWidth, $cropHeight);
            
            // Convert to PNG and get base64
            ob_start();
            imagepng($croppedImage, null, 9);
            $pngData = ob_get_clean();
            
            // Clean up
            imagedestroy($image);
            imagedestroy($croppedImage);
            
            return base64_encode($pngData);
            
        } catch (\Exception $e) {
            \Log::error('Signature processing error: ' . $e->getMessage());
            // Return original if processing fails
            return base64_encode(base64_decode($imageData));
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            // Ensure the employee_id is set
            if (empty($data['employee_id'])) {
                $data['employee_id'] = auth()->user()->employee->id;
            }

            // Check if employee already has a certificate
            $existingCertificate = EmployeeCertificate::where('employee_id', $data['employee_id'])->first();
            if ($existingCertificate) {
                Notification::make()
                    ->title('Error')
                    ->body('This employee already has a certificate.')
                    ->danger()
                    ->send();

                $this->halt();
            }

            // Handle optional .p12 file
            if (!empty($data['p12_file']) && !empty($data['p12_password'])) {
                $p12FilePath = Storage::path('public/' . $data['p12_file']);
                $p12Password = $data['p12_password'];

                $p12Content = file_get_contents($p12FilePath);
                $certs = [];
                if (!openssl_pkcs12_read($p12Content, $certs, $p12Password)) {
                    throw new Exception('Unable to read the .p12 file. The password might be incorrect.');
                }

                $data['private_key'] = Crypt::encryptString($certs['pkey']);
                $data['certificate'] = Crypt::encryptString($certs['cert']);
                $data['intermediate_certificates'] = isset($certs['extracerts'])
                    ? Crypt::encryptString(implode("\n", $certs['extracerts']))
                    : null;
            }

            // Handle Signature - REQUIRED with auto-cropping
            if (!empty($data['signature_image']) && $data['signature_type'] === 'draw') {
                // Remove data URI prefix
                $base64Signature = preg_replace('/^data:image\/\w+;base64,/', '', $data['signature_image']);
                
                // Process and crop signature
                $croppedSignature = $this->processSignature($base64Signature);
                
                // Encrypt and store
                $data['signature_image_path'] = Crypt::encryptString($croppedSignature);
                
            } elseif (!empty($data['signature_file']) && $data['signature_type'] === 'upload') {
                $filePath = Storage::path('public/' . $data['signature_file']);
                
                if (!file_exists($filePath)) {
                    throw new Exception('Signature file not found. Please try uploading again.');
                }
                
                // Read file and convert to base64
                $fileContent = file_get_contents($filePath);
                $base64Image = base64_encode($fileContent);
                
                // Process and crop signature
                $croppedSignature = $this->processSignature($base64Image);
                
                // Encrypt and store
                $data['signature_image_path'] = Crypt::encryptString($croppedSignature);
                
            } else {
                throw new Exception('Signature is required. Please draw or upload a signature.');
            }

            // Remove temporary fields
            unset($data['p12_file'], $data['p12_password'], $data['signature_image'], $data['signature_file'], $data['signature_type'], $data['employee_name']);

        } catch (Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'signature_image' => $e->getMessage(),
            ]);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            // Clean up uploaded files
            $signatureFiles = Storage::files('public/signatures');
            foreach ($signatureFiles as $file) {
                Storage::delete($file);
            }

            $p12Files = Storage::files('public/p12-files');
            foreach ($p12Files as $file) {
                Storage::delete($file);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to clean up temporary files: ' . $e->getMessage());
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}