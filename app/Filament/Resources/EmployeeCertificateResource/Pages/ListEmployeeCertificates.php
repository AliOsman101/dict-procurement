<?php

namespace App\Filament\Resources\EmployeeCertificateResource\Pages;

use App\Filament\Resources\EmployeeCertificateResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;
use App\Models\EmployeeCertificate;
use Illuminate\Support\Facades\Auth;

class ListEmployeeCertificates extends ListRecords
{
    protected static string $resource = EmployeeCertificateResource::class;

    protected function getHeaderActions(): array
    {
        $employeeId = Auth::user()->employee->id ?? null;

        // Check if the user already has a certificate
        $hasCertificate = EmployeeCertificate::where('employee_id', $employeeId)->exists();

        // If the employee already has a certificate, return an empty array
        if ($hasCertificate) {
            return []; // This prevents any button from appearing
        }

        return [CreateAction::make()];
    }
}