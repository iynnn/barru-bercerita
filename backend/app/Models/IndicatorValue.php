<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicatorValue extends Model
{
    //

    protected $fillable = ['indicator_id', 'region_id', 'year', 'value'];

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
