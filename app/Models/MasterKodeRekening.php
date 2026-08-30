<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class MasterKodeRekening extends Model
{
    use LogsActivity;

    protected $table = 'master_kode_rekening';

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(RkasItem::class);
    }

    public function jenisBelanja()
    {
        return $this->belongsTo(JenisBelanja::class, 'jenis_belanja_id');
    }

    public function kategoriJuknis()
    {
        return $this->belongsToMany(KategoriJuknis::class, 'kode_rekening_kategori_juknis', 'master_kode_rekening_id', 'kategori_juknis_id');
    }
}
