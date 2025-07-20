<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Indicator extends Model
{
    //
    protected $fillable = ['name', 'description',  'unit', 'category_id'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function values()
    {
        return $this->hasMany(IndicatorValue::class);
    }
}
