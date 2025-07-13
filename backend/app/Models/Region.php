<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    //
    public function values()
    {
        return $this->hasMany(IndicatorValue::class);
    }
}
