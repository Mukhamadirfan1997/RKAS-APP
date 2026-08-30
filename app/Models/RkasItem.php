<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class RkasItem extends Model
{
    use LogsActivity;

    protected $table = 'rkas_item';

    protected $guarded = [];

    public function tahunAnggaran()
    {
        return $this->belongsTo(TahunAnggaran::class);
    }

    public function program()
    {
        return $this->belongsTo(MasterProgram::class, 'master_program_id');
    }

    public function kodeRekening()
    {
        return $this->belongsTo(MasterKodeRekening::class, 'master_kode_rekening_id');
    }

    public function barang()
    {
        return $this->belongsTo(KodeBarang::class, 'kode_barang_id');
    }

    public function alokasiBulan()
    {
        return $this->hasMany(RkasItemBulan::class, 'rkas_item_id')->orderBy('bulan');
    }

    public function getTahap1Attribute()
    {
        return $this->alokasiBulan->whereBetween('bulan', [1, 6])->sum('jumlah');
    }

    public function getTahap2Attribute()
    {
        return $this->alokasiBulan->whereBetween('bulan', [7, 12])->sum('jumlah');
    }
}
