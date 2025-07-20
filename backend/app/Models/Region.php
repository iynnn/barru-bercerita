<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    //
    protected $fillable = ['name', 'code'];

    public function values()
    {
        return $this->hasMany(IndicatorValue::class);
    }
}
