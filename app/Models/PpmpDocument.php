<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpmpDocument extends Model
{
    protected $fillable = [
        'procurement_id',
        'file_path',
        'file_name',
        'status',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }
}