<?php

namespace Database\Seeders;

use App\Models\JenisBelanja;
use App\Models\KategoriJuknis;
use App\Models\MasterKodeRekening;
use Illuminate\Database\Seeder;

class JuknisSeeder extends Seeder
{
    public function run(): void
    {
        $kategoriList = [
            ['nama' => 'Honor Guru & Tenaga Kependidikan', 'arah' => 'maksimal', 'batas_persen' => 20, 'keterangan' => 'Batas honor maks 20% dari pagu (Permendikdasmen 8/2026).'],
            ['nama' => 'Pengadaan Buku', 'arah' => 'minimal', 'batas_persen' => 10, 'keterangan' => 'Batas minimal anggaran buku 10% dari pagu (SD).'],
            ['nama' => 'Pemeliharaan Sarana & Prasarana', 'arah' => 'maksimal', 'batas_persen' => 20, 'keterangan' => 'Batas maksimal pemeliharaan sarpras 20%.'],
        ];

        // Rekening per kategori disusun via JENIS BELANJA (klasifikasi resmi ARKAS)
        $honorRek = MasterKodeRekening::whereIn('jenis_belanja_id', $this->jenisIds(['Belanja Jasa']))->pluck('id');
        $bukuRek = MasterKodeRekening::whereIn('jenis_belanja_id', $this->jenisIds(['Belanja Modal Buku']))->pluck('id');
        $sarprasRek = MasterKodeRekening::whereIn('jenis_belanja_id', $this->jenisIds(['Belanja Jasa Pemeliharaan']))->pluck('id');

        $mapping = [
            'Honor Guru & Tenaga Kependidikan' => $honorRek,
            'Pengadaan Buku' => $bukuRek,
            'Pemeliharaan Sarana & Prasarana' => $sarprasRek,
        ];

        foreach ($kategoriList as $k) {
            $kategori = KategoriJuknis::firstOrCreate(
                ['nama' => $k['nama']],
                $k
            );

            if (isset($mapping[$k['nama']])) {
                $kategori->rekenings()->sync($mapping[$k['nama']]);
            }
        }
    }

    /**
     * @param  array<int, string>  $nama
     * @return array<int, int>
     */
    protected function jenisIds(array $nama): array
    {
        return JenisBelanja::whereIn('nama', $nama)->pluck('id')->all();
    }
}
