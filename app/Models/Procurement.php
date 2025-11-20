<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Procurement extends Model
{
    protected $fillable = [
        'module',
        'parent_id',
        'procurement_type',
        'procurement_id',
        'created_by',
        'basis',
        'requested_by',
        'fund_cluster_id',
        'office_section',
        'category_id',
        'status',
        'title',
        'grand_total',
        'delivery_mode',
        'delivery_value',
        'deadline_date',
        'prepared_by',
        'bid_opening_datetime',
        'place_of_delivery',
        'date_of_delivery',
        'payment_term',
        'ors_burs_no',
        'ors_burs_date',
    ];

    protected $casts = [
        'grand_total' => 'decimal:2',
        'deadline_date' => 'datetime',
        'bid_opening_datetime' => 'datetime',
    ];

    protected $guarded = [
        'rfqResponses',
        'procurementItems',
        'requester',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->procurement_id) && is_null($model->module)) {
                $year = date('Y');
                $month = date('m');
                $count = self::whereYear('created_at', $year)
                            ->whereMonth('created_at', $month)
                            ->whereNull('module')
                            ->count() + 1;
                $model->procurement_id = sprintf('PROC-%s-%s-%03d', $year, $month, $count);
            }
            if (empty($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        // Remove custom attributes before saving
        static::saving(function ($model) {
            // Remove custom collections that don't exist in database
            unset($model->attributes['rfqResponses']);
            unset($model->attributes['procurementItems']);
            unset($model->attributes['requester']);
        });

        static::deleting(function ($model) {
            // Delete all child procurements
            $model->children()->delete();
        });
    }

    public function updateGrandTotal()
    {
        $this->grand_total = $this->items()->sum('total_cost');
        $this->save();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Procurement::class, 'parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }

    public function fundCluster(): BelongsTo
    {
        return $this->belongsTo(FundCluster::class, 'fund_cluster_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementItem::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'procurement_employees');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProcurementDocument::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function rfqSuppliers(): HasMany
    {
        return $this->hasMany(RfqSupplier::class);
    }

    public function rfqResponses(): HasMany
    {
        return $this->hasMany(RfqResponse::class, 'procurement_id');
    }

    public function rfqDistributions(): HasMany
    {
        return $this->hasMany(RfqDistribution::class);
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'prepared_by');
    }

    public function aoqEvaluations(): HasMany
    {
        return $this->hasMany(AoqEvaluation::class);
    }

    public function procurementItems(): HasMany
    {
        return $this->hasMany(ProcurementItem::class, 'procurement_id');
    }

    public function getFullRequesterNameAttribute()
    {
        return $this->requester?->firstname . ' ' . $this->requester?->lastname ?? 'Not set';
    }

    /** 
     * Relationship to the RFQ (Request for Quotation) child procurement
     */
    public function rfq()
    {
        return $this->hasOne(Procurement::class, 'parent_id')
            ->where('module', 'request_for_quotation');
    }

    /** delivery_mode – fall back to RFQ if empty */
    public function getDeliveryModeAttribute($value)
    {
        return $value ?? $this->rfq?->delivery_mode;
    }

    /** delivery_value – fall back to RFQ if empty */
    public function getDeliveryValueAttribute($value)
    {
        return $value ?? $this->rfq?->delivery_value;
    }
}