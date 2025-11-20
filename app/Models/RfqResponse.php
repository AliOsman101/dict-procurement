<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqResponse extends Model
{
    protected $fillable = [
        'procurement_id',
        'supplier_id',
        'business_name',
        'designation',
        'business_address',
        'contact_no',
        'email_address',
        'tin',
        'vat',
        'nvat',
        'philgeps_reg_no',
        'lbp_account_name',
        'lbp_account_number',
        'submitted_by',
        'submitted_date',
        'documents',
        'rfq_document',
    ];

    protected $casts = [
        'submitted_date' => 'date',
        'vat' => 'boolean',
        'nvat' => 'boolean',
        'documents' => 'array',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function aoqEvaluations(): HasMany
    {
        return $this->hasMany(AoqEvaluation::class, 'rfq_response_id');
    }

    // Helper method to get document evaluations
    public function documentEvaluations()
    {
        return $this->aoqEvaluations()->documents();
    }

    // Helper method to get quote evaluations
    public function quoteEvaluations()
    {
        return $this->aoqEvaluations()->quotes();
    }

    // Check if supplier passed document evaluation
    public function hasPassedDocuments(): bool
    {
        $docEvals = $this->documentEvaluations()->get();
        return $docEvals->every(fn($eval) => $eval->status === 'pass');
    }

    // Get winning quote items
    public function winningQuotes()
    {
        return $this->quoteEvaluations()->where('lowest_bid', true)->get();
    }
}