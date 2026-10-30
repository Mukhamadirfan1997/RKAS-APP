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

    /**
     * Jumlah setelah koreksi manual = (Volume x Harga) + KOREKSI.
     */
    public function getJumlahKoreksiAttribute()
    {
        return round((float) $this->jumlah + (float) $this->koreksi, 2);
    }

    /**
     * Selisih harga yang dianggarkan terhadap harga acuan katalog.
     * Bersifat informasi saja — bukan dasar badge KONTROL (KONTROL berbasis
     * batas atas SSH, lihat getKontrolAttribute()).
     */
    public function getSelisihHargaAttribute()
    {
        return round((float) $this->harga_satuan - (float) $this->harga_satuan_arkas, 2);
    }

    /**
     * Batas atas SSH (harga_max) dari katalog barang. 0 bila item tanpa katalog/SSH.
     */
    public function getBatasSshAttribute(): float
    {
        return (float) ($this->barang->harga_max ?? 0);
    }

    /**
     * Status KONTROL per baris berbasis Standar Satuan Harga (SSH).
     *
     * Aturan JUKNIS: harga satuan yang dianggarkan TIDAK BOLEH MELEBIHI batas
     * atas SSH. Tidak harus sama persis dengan harga acuan katalog — sekolah
     * boleh menawar lebih murah. Jadi:
     * - 'OK'       : harga <= batas atas SSH, atau item tidak punya data SSH.
     * - 'MELEBIHI' : harga > batas atas SSH.
     */
    public function getKontrolAttribute(): string
    {
        $batas = $this->batas_ssh;

        if ($batas <= 0.0) {
            return 'OK';
        }

        return (float) $this->harga_satuan > ($batas + 0.009) ? 'MELEBIHI' : 'OK';
    }
}
