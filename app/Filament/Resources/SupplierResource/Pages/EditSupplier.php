<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use App\Helpers\ActivityLogger; 
class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $documentFields = [
            'mayors_permit',
            'philgeps_certificate',
            'omnibus_sworn_statement',
            'pcab_license',
            'professional_license_cv',
            'terms_conditions_tech_specs',
            'tax_return',
        ];

        foreach ($documentFields as $field) {
            $oldValue = $this->record->{$field};
            $newValue = $data[$field] ?? null;
            
            // Delete old file if a new file is uploaded
            if ($newValue && $oldValue && $newValue !== $oldValue) {
                // Handle malformed JSON in old value
                $oldPath = $oldValue;
                if (is_string($oldValue) && str_starts_with($oldValue, '{')) {
                    $decoded = json_decode($oldValue, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $oldPath = reset($decoded);
                    }
                }
                
                if (is_string($oldPath) && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            
            // Set field to null and delete old file if user removes the file
            if (empty($newValue)) {
                $data[$field] = null;
                
                if ($oldValue) {
                    $oldPath = $oldValue;
                    if (is_string($oldValue) && str_starts_with($oldValue, '{')) {
                        $decoded = json_decode($oldValue, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $oldPath = reset($decoded);
                        }
                    }
                    
                    if (is_string($oldPath) && Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
            }
        }

        return $data;
    }
 
    protected function afterSave(): void
    {
        $supplierName = $this->record->fresh()->name 
            ?? $this->record->fresh()->business_name 
            ?? 'Unnamed Supplier';

        ActivityLogger::log(
            'Updated Supplier',
            "Supplier '{$supplierName}' was updated."
        );
    }
}