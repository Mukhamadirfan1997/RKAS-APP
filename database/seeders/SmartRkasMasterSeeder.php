<?php

namespace Database\Seeders;

use App\Models\JenisBelanja;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Services\JenisBelanjaResolver;
use Illuminate\Database\Seeder;

/**
 * Seed master data dari sumber nyata SmartRKAS (database asli).
 *
 * Fixture: database/seeders/data/smartrkas-master.json
 * Berisi: jenis_belanja (9), program (139, level 4), rekening (276).
 *
 * Aturan klasifikasi rekening -> jenis_belanja mengikuti prefix kode
 * (lihat klasifikasi_rekening.php proyek SIRA/SmartRKAS).
 */
class SmartRkasMasterSeeder extends Seeder
{
    protected JenisBelanjaResolver $resolver;

    public function __construct()
    {
        $this->resolver = app(JenisBelanjaResolver::class);
    }

    public function run(): void
    {
        $data = json_decode(
            file_get_contents(__DIR__.'/data/smartrkas-master.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->seedJenisBelanja($data['jenis_belanja'] ?? []);
        $this->seedProgram($data['program'] ?? []);
        $this->seedRekening($data['rekening'] ?? []);
    }

    /**
     * @param  array<int, string>  $list
     */
    protected function seedJenisBelanja(array $list): void
    {
        foreach ($list as $nama) {
            JenisBelanja::firstOrCreate(['nama' => $nama]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $list
     */
    protected function seedProgram(array $list): void
    {
        // parent_id ditentukan oleh kode LEVEL terdekat yang merupakan awalan kode.
        $rows = [];
        foreach ($list as $p) {
            $rows[] = MasterProgram::firstOrCreate(
                ['kode' => $p['kode']],
                [
                    'nama' => $p['nama'],
                    'program' => $p['program'] ?? null,
                    'sub_program' => $p['sub_program'] ?? null,
                    'level' => $p['level'] ?? 1,
                ]
            );
        }

        // Isi parent_id untuk yang levelnya > 1 (cari kegiatan terdekat yang kodenya awalan)
        foreach ($rows as $row) {
            if ((int) $row->level <= 1) {
                continue;
            }

            $candidates = MasterProgram::where('id', '!=', $row->id)
                ->where('level', '<', $row->level)
                ->whereRaw('? LIKE kode || \'%\'', [$row->kode])
                ->orderByDesc('level')
                ->first();

            if ($candidates && $row->parent_id !== $candidates->id) {
                $row->parent_id = $candidates->id;
                $row->save();
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $list
     */
    protected function seedRekening(array $list): void
    {
        foreach ($list as $r) {
            $kode = $r['kode'];
            $namaJenis = $r['jenis_belanja'] ?? $this->resolver->namaRekening($kode);
            $jenisId = JenisBelanja::where('nama', $namaJenis)->value('id')
                ?? $this->resolver->jenisId($kode);

            MasterKodeRekening::firstOrCreate(
                ['kode' => $kode],
                [
                    'nama' => $r['nama'],
                    'jenis_belanja_id' => $jenisId,
                    'kategori_belanja' => $this->resolver->legacyKategori($namaJenis),
                ]
            );
        }
    }
}
