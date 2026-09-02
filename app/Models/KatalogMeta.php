<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KatalogMeta extends Model
{
    protected $table = 'katalog_meta';

    protected $guarded = [];

    protected $casts = [
        'tanggal_update' => 'datetime',
        'jumlah_barang' => 'integer',
    ];
}
