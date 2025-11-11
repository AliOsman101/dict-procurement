<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'private_key',
        'certificate',
        'intermediate_certificates',
        'signature_image_path',
    ];

    // Optionally, you can define a relationship with the Employee model
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}