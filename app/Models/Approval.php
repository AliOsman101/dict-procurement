<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    protected $fillable = [
        'procurement_id',
        'module', // Enum: same as procurement
        'employee_id',
        'sequence',
        'designation',
        'status',
        'remarks',
        'date_approved',
    ];

    protected $casts = [
        'date_approved' => 'datetime',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}