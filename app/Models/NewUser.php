<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewUser extends Model implements Authenticatable
{
    use AuthenticatableTrait, HasFactory;

    protected $fillable = [
        'name', 'email', 'google_id',
    ];
}
