<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_name',
        'business_address',
        'contact_no',
        'email_address',
        'tin',
        'vat',
        'nvat',
        'philgeps_reg_no',
        'philgeps_expiry_date',
        'lbp_account_name',
        'lbp_account_number',
        'mayors_permit',
        'philgeps_certificate',
        'omnibus_sworn_statement',
        'pcab_license',
        'professional_license_cv',
        'terms_conditions_tech_specs',
        'tax_return',
    ];

    protected $casts = [
        'vat' => 'boolean',
        'nvat' => 'boolean',
        'philgeps_expiry_date' => 'date',
    ];

    /**
     * Categories this supplier provides.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'supplier_categories');
    }

    public function rfqResponses(): HasMany
    {
        return $this->hasMany(RfqResponse::class);
    }

    // Helper to get PhilGEPS expiry status
    public function getPhilgepsStatusAttribute(): array
    {
        if (!$this->philgeps_expiry_date) {
            return [
                'status' => 'unknown',
                'label' => 'No expiry date',
                'color' => 'gray'
            ];
        }

        $now = Carbon::now();
        $expiryDate = Carbon::parse($this->philgeps_expiry_date);
        $daysUntilExpiry = $now->diffInDays($expiryDate, false);

        if ($daysUntilExpiry < 0) {
            return [
                'status' => 'expired',
                'label' => 'Expired',
                'color' => 'danger',
                'date' => $expiryDate->format('M d, Y')
            ];
        } elseif ($daysUntilExpiry <= 30) {
            return [
                'status' => 'expiring_soon',
                'label' => "Expiring Soon ({$daysUntilExpiry} days)",
                'color' => 'warning',
                'date' => $expiryDate->format('M d, Y')
            ];
        } else {
            return [
                'status' => 'valid',
                'label' => 'Valid',
                'color' => 'success',
                'date' => $expiryDate->format('M d, Y')
            ];
        }
    }

    // Get all available supplier documents
    public function getAvailableDocuments(): array
    {
        $documents = [];
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
            $value = $this->{$field};
            
            if (!empty($value)) {
                // Handle malformed JSON format from database
                if (is_string($value) && str_starts_with($value, '{')) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        // Extract actual path from UUID-keyed array
                        $path = reset($decoded);
                        if ($path !== false && is_string($path)) {
                            \Log::info("Cleaned malformed JSON for {$field}:", ['original' => $value, 'cleaned' => $path]);
                            $documents[$field] = $path;
                            continue;
                        }
                    }
                }
                
                // Normal string path
                if (is_string($value)) {
                    $documents[$field] = $value;
                }
            }
        }

        return $documents;
    }
}