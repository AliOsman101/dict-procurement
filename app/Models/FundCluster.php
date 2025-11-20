<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundCluster extends Model
{
    protected $fillable = ['name'];

    /**
     * Procurements using this fund cluster.
     */
    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class);
    }
}