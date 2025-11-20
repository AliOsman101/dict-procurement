<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Approval extends Model
{
    protected $fillable = [
        'procurement_id',
        'module',
        'employee_id',
        'sequence',
        'designation',
        'status',
        'remarks',
        'action_at',
    ];

    protected $casts = [
        'action_at' => 'datetime',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the signature for this approval (if approved)
     * Returns base64 image string or null
     * THIS IS THE CRITICAL METHOD FOR DISPLAYING SIGNATURES!
     */
    public function getSignatureAttribute(): ?string
    {
        // Only return signature if approval is approved
        if ($this->status !== 'Approved') {
            return null;
        }

        // Get employee certificate
        $certificate = $this->employee?->certificate;
        
        if (!$certificate || !$certificate->signature_image_path) {
            return null;
        }

        try {
            // Decrypt and return base64 signature
            return Crypt::decryptString($certificate->signature_image_path);
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt signature', [
                'approval_id' => $this->id,
                'employee_id' => $this->employee_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}