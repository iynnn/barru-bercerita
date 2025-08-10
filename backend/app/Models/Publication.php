<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Publication extends Model
{

    use HasFactory;

    protected $fillable = ['bps_related_id', 'title', 'link'];
    /**
     *  Satu publication bisa terkait dengan banyak indikator
     * Many to many 
     */

    public function indicators()
    {
        return $this->belongsToMany(Indicator::class, 'indicator_publication');
    }
}
