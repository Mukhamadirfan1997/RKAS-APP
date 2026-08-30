<?php

namespace Database\Seeders;

use App\Models\KategoriJuknis;
use App\Models\MasterKodeRekening;
use Illuminate\Database\Seeder;

class JuknisSeeder extends Seeder
{
    public function run(): void
    {
        $kategoriList = [
            ['nama' => 'Honor Guru & Tenaga Kependidikan', 'arah' => 'maksimal', 'batas_persen' => 20, 'keterangan' => 'Batas honor sekolah negeri maks 20% dari pagu.'],
            ['nama' => 'Pengadaan Buku', 'arah' => 'minimal', 'batas_persen' => 10, 'keterangan' => 'Batas minimal anggaran buku 10% dari pagu (SD).'],
            ['nama' => 'Sarana & Prasarana', 'arah' => 'maksimal', 'batas_persen' => 20, 'keterangan' => 'Batas maksimal anggaran pemeliharaan sarpras 20%.'],
        ];

        $rekeningHonor = MasterKodeRekening::where('kategori_belanja', 'HONOR')->pluck('id');
        $rekeningBuku = MasterKodeRekening::where('kode', 'like', '5.2.05%')->pluck('id');
        $rekeningSarpras = MasterKodeRekening::where('kode', 'like', '%0405%')->pluck('id');

        foreach ($kategoriList as $k) {
            $kategori = KategoriJuknis::firstOrCreate(
                ['nama' => $k['nama']],
                $k
            );

            match ($k['nama']) {
                'Honor Guru & Tenaga Kependidikan' => $kategori->rekenings()->sync($rekeningHonor),
                'Pengadaan Buku' => $kategori->rekenings()->sync($rekeningBuku),
                'Sarana & Prasarana' => $kategori->rekenings()->sync($rekeningSarpras),
                default => null,
            };
        }
    }
}
