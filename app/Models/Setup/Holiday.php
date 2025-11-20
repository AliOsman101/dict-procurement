<?php

namespace App\Models\Setup;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'description',
        'holiday_date',

    ];

    //usage: Holiday::isHoliday()->get();
    //returns the row
    public function scopeIsHoliday($query, $date)
    {
        return $query->where('holiday_date', $date);
    }

    public function scopePaymonth($query, $from, $to)
    {
        return $query->where('holiday_date', '>=', $from)->where('holiday_date', '<=', $to);

    }
}
