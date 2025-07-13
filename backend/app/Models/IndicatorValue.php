<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicatorValue extends Model
{
    //
    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
