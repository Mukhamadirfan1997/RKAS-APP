<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KategoriJuknis extends Model
{
    protected $table = 'kategori_juknis';

    protected $guarded = [];

    public function rekenings()
    {
        return $this->belongsToMany(MasterKodeRekening::class, 'kode_rekening_kategori_juknis', 'kategori_juknis_id', 'master_kode_rekening_id');
    }
}
