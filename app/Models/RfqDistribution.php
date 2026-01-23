<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqDistribution extends Model
{
    protected $fillable = [
        'procurement_id',
        'supplier_id',
        'sent_method',
        'sent_to',
        'sent_at',
        'received_at',
        'sender_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'sender_id');
    }

    public function rfqResponses(): HasMany
    {
        return $this->hasMany(RfqResponse::class, 'supplier_id', 'supplier_id')
            ->where('procurement_id', $this->procurement_id);
    }
}
