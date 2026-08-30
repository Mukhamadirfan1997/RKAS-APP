<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KodeRekeningKategoriJuknis extends Model
{
    protected $table = 'kode_rekening_kategori_juknis';

    protected $guarded = [];

    public function kategori()
    {
        return $this->belongsTo(KategoriJuknis::class);
    }

    public function rekening()
    {
        return $this->belongsTo(MasterKodeRekening::class, 'master_kode_rekening_id');
    }
}
