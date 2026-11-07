<?php

namespace Database\Seeders;

use App\Models\JenisBelanja;
use App\Models\KategoriJuknis;
use App\Models\MasterKodeRekening;
use Illuminate\Database\Eloquent\Collection;
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

        // Rekening tiap kategori disusun dengan aturan yang SAMA seperti
        // JuknisValidator: prefix kode rekening ATAU jenis belanja pada
        // config/juknis.php. Dengan begitu checklist Monitoring tidak pernah
        // berbeda dari angka yang dihitung validator.
        $mapping = [
            'Honor Guru & Tenaga Kependidikan' => $this->rekeningKomponen('honor'),
            'Pengadaan Buku' => $this->rekeningKomponen('buku'),
            'Pemeliharaan Sarana & Prasarana' => $this->rekeningKomponen('sarpras'),
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
     * Rekening yang memenuhi komponen JUKNIS menurut config/juknis.php.
     *
     * Prefix kode rekening adalah penanda utama (mis. buku = 5.2.05), jadi
     * perubahan label jenis belanja TIDAK mengurangi isi checklist.
     *
     * @return array<int, int>
     */
    protected function rekeningKomponen(string $komponen): array
    {
        $cfg = config("juknis.$komponen", []);
        $prefixes = array_filter($cfg['rekening'] ?? [], fn ($p) => $p !== '');
        $jenisIds = $this->jenisIds($cfg['jenis_belanja'] ?? []);

        if ($prefixes === [] && $jenisIds === []) {
            return [];
        }

        /** @var Collection<int, MasterKodeRekening> $rekenings */
        $rekenings = MasterKodeRekening::query()
            ->where(function ($q) use ($prefixes, $jenisIds) {
                foreach ($prefixes as $prefix) {
                    $q->orWhere('kode', 'like', $prefix.'%');
                }

                if ($jenisIds !== []) {
                    $q->orWhereIn('jenis_belanja_id', $jenisIds);
                }
            })
            ->pluck('id');

        return $rekenings->all();
    }

    /**
     * @param  array<int, string>  $nama
     * @return array<int, int>
     */
    protected function jenisIds(array $nama): array
    {
        if ($nama === []) {
            return [];
        }

        return JenisBelanja::whereIn('nama', $nama)->pluck('id')->all();
    }
}
