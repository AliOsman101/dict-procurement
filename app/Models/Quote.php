<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quote extends Model
{
    protected $fillable = [
        'rfq_response_id',
        'procurement_item_id',
        'statement_of_compliance',
        'specifications',
        'unit_value',
        'total_value',
    ];

    protected $casts = [
        'statement_of_compliance' => 'boolean',
        'unit_value' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    protected static function booted()
    {
        // Auto-calculate total_value before saving
        static::saving(function ($quote) {
            if ($quote->unit_value && $quote->procurementItem) {
                $quantity = $quote->procurementItem->quantity ?? 0;
                $quote->total_value = $quote->unit_value * $quantity;
            }
        });
    }

    public function rfqResponse(): BelongsTo
    {
        return $this->belongsTo(RfqResponse::class);
    }

    public function procurementItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementItem::class);
    }
}