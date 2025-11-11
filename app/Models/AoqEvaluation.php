<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AoqEvaluation extends Model
{
    protected $fillable = [
        'procurement_id',
        'rfq_response_id',
        'requirement',
        'status',
        'remarks',
        'lowest_bid',
    ];

    protected $casts = [
        'lowest_bid' => 'boolean',
        'status' => 'string',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function rfqResponse(): BelongsTo
    {
        return $this->belongsTo(RfqResponse::class);
    }

    // Scope to get document evaluations only
    public function scopeDocuments($query)
    {
        return $query->whereNot('requirement', 'like', 'quote_%');
    }

    // Scope to get quote evaluations only
    public function scopeQuotes($query)
    {
        return $query->where('requirement', 'like', 'quote_%');
    }

    // Check if this evaluation is for a specific document
    public function isDocumentEvaluation(): bool
    {
        return !str_starts_with($this->requirement, 'quote_');
    }

    // Check if this evaluation is for a quote
    public function isQuoteEvaluation(): bool
    {
        return str_starts_with($this->requirement, 'quote_');
    }
}