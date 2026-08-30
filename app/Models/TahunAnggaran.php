<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TahunAnggaran extends Model
{
    protected $table = 'tahun_anggaran';

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(RkasItem::class);
    }
}
