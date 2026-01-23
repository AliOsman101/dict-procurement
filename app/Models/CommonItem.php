<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommonItem extends Model
{
    protected $fillable = [
        'category_id',
        'unit',
        'item_description',
        'unit_cost',
    ];

    protected static function booted()
    {
        static::saving(function ($item) {

        
            if (strlen($item->unit) > 10 && strlen($item->item_description) < 10) {
    
                $temp = $item->unit;
                $item->unit = $item->item_description;
                $item->item_description = $temp;
            }

           
            if (empty($item->category_id)) {
    
                $item->category_id = 4;
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
