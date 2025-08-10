<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IndicatorValue extends Model
{
    //
    use HasFactory;
    protected $fillable = ['indicator_id', 'region_id', 'year', 'value', 'last_synced_at'];

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
}
