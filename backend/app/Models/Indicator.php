<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Indicator extends Model
{
    use HasFactory;
    //
    protected $fillable = ['name', 'description',  'unit', 'category_id', 'bps_var_id', 'bps_vervar_id', 'bps_turvar_id'];

    /**
     * Satu indikator hanya dimiliki oleh satu kategori
     * Many to One
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Satu indikator punya banyak nilai 
     * One To Many 
     */
    public function values()
    {
        return $this->hasMany(IndicatorValue::class);
    }


    /**
     * Satu indikator punya banyak publikasi dan sebaliknya 
     * Many to Many
     */
    public function publications()
    {
        return $this->belongsToMany(Publication::class, 'indicator_publication');
    }
}
