<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementItem extends Model
{
    protected $fillable = [
        'procurement_id',
        'unit',
        'item_description',
        'quantity',
        'unit_cost',
        'total_cost',
        'sort',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'quantity' => 'integer',
        'sort' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($item) {
            if (is_null($item->sort)) {
                // Find the highest sort value for the same procurement_id
                $maxSort = ProcurementItem::where('procurement_id', $item->procurement_id)
                    ->max('sort') ?? 0;
                $item->sort = $maxSort + 1;
            }
        });

        static::saving(function ($item) {
            $item->total_cost = $item->quantity * $item->unit_cost;
            
            // Validate grand total for SVP
            if ($item->procurement && $item->procurement->procurement_type === 'small_value_procurement') {
                $currentTotal = ProcurementItem::where('procurement_id', $item->procurement_id)
                    ->where('id', '!=', $item->id ?? 0)
                    ->sum('total_cost');
                
                $newGrandTotal = $currentTotal + $item->total_cost;
                
                if ($newGrandTotal >= 1000000) {
                    throw new \Exception('Grand total must be less than ₱1,000,000.00 for Small Value Procurement.');
                }
            }
        });

        static::saved(function ($item) {
            if ($item->procurement) {
                $item->procurement->updateGrandTotal();
            }
        });

        static::deleted(function ($item) {
            if ($item->procurement) {
                $item->procurement->updateGrandTotal();
            }
        });
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }
}