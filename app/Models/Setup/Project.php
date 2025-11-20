<?php

namespace App\Models\Setup;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function employees()
    {
        return $this->hasMany('App\Models\Employee');
    }

    public function travelOrder()
    {
        return $this->hasMany('App\Models\Travel\TravelOrder');
    }
}
