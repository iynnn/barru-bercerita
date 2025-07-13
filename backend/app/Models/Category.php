<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    //
    public function indicators()
    {
        return $this->hasMany(Indicator::class);
    }
}
