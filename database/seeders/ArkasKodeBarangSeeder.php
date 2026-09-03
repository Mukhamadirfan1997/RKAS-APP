<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArkasKodeBarangSeeder extends Seeder
{
    private string $file;

    private array $strings = [];

    private int $inserted = 0;

    private int $skipped = 0;

    public function __construct()
    {
        $this->file = base_path('RKAS - KERTAS KERJA 2026 master V.2.TOYANING revisi harga edit.xlsm');
    }

    public function run(): void
    {
        if (! file_exists($this->file)) {
            $this->command->error("File tidak ditemukan: {$this->file}");

            return;
        }

        // Guard: jangan truncate buta — jika sudah terisi 81k (first-run via copy pre-seeded),
        // skip agar tidak menghapus data dan tidak memperlambat startup.
        // Manual dev tetap bisa force dengan --fresh atau truncate manual dahulu.
        $existing = DB::table('kode_barang')->count();
        if ($existing >= 80000) {
            $this->command->warn("kode_barang sudah terisi {$existing} baris, skip truncate & impor (sudah pre-seeded).");
            $this->command->warn('Hapus manual (truncate) jika ingin re-import dari .xlsm.');

            return;
        }

        // Jika ada data parsial (mis. 9 sample), truncate dulu baru isi penuh
        if ($existing > 0) {
            DB::table('kode_barang')->truncate();
            $this->command->info("Tabel kode_barang dikosongkan ({$existing} baris sample dihapus).");
        } else {
            $this->command->info('Tabel kode_barang kosong, mulai impor.');
        }

        $this->loadSharedStrings();
        $this->streamSheet5();
        $this->command->info("\nImpor selesai: {$this->inserted} baris dimasukkan, {$this->skipped} dilewati.");
    }

    private function loadSharedStrings(): void
    {
        $this->command->info('Memuat shared strings...');
        $zip = new \ZipArchive;
        $zip->open($this->file);
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        preg_match_all('/<si>(.*?)<\/si>/s', $ss, $sis);
        foreach ($sis[1] as $raw) {
            $t = '';
            if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $raw, $ts)) {
                $t = implode('', $ts[1]);
            }
            $this->strings[] = html_entity_decode($t);
        }
    }

    private function streamSheet5(): void
    {
        $this->command->info('Membaca sheet KODE BARANG (baris 2 = header, baris 3+ = data)...');
        $zip = new \ZipArchive;
        $zip->open($this->file);
        $xml = $zip->getFromName('xl/worksheets/sheet5.xml');
        $zip->close();

        $reader = new \XMLReader;
        $reader->XML($xml);

        $rows = [];
        $rowNo = 0;
        $flushAt = 500;

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'row') {
                continue;
            }

            $rowNo++;
            if ($rowNo <= 2) {
                continue; // row 1 = judul, row 2 = header kolom
            }

            $rowXml = $reader->readOuterXML();
            if (strpos($rowXml, '<c') === false) {
                continue;
            }

            $cells = $this->parseRow($rowXml);
            if (! isset($cells['A']) || trim((string) $cells['A']) === '') {
                continue;
            }

            $kode = trim((string) $cells['A']);
            $nama = trim((string) ($cells['C'] ?? ''));
            if ($nama === '') {
                $this->skipped++;

                continue;
            }

            $rows[] = [
                'kode' => $kode,
                'id_barang_arkas' => $kode,
                'nama' => $nama,
                'kode_rekening' => trim((string) ($cells['B'] ?? '')),
                'satuan_default' => trim((string) ($cells['D'] ?? '')),
                'harga_acuan' => (float) ($cells['E'] ?? 0),
                'harga_min' => (float) ($cells['F'] ?? 0),
                'harga_max' => (float) ($cells['G'] ?? 0),
                'kode_belanja' => trim((string) ($cells['H'] ?? '')),
                'kategori' => trim((string) ($cells['I'] ?? '')),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($rows) >= $flushAt) {
                $this->insertBatch($rows);
                $rows = [];
            }
        }

        $reader->close();

        if (count($rows) > 0) {
            $this->insertBatch($rows);
        }
    }

    private function parseRow(string $rowXml): array
    {
        $cells = [];
        if (preg_match_all('/<c\s+[^>]*r="([A-Z]+\d+)"[^>]*>(.*?)<\/c>/s', $rowXml, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $cv) {
                $ref = $cv[1];
                $col = preg_replace('/\d+$/', '', $ref);
                $inner = $cv[2];

                $isStr = false;
                if (preg_match('/\bt="([^"]+)"/', $cv[0], $ta)) {
                    $isStr = ($ta[1] === 's' || $ta[1] === 'inlineStr');
                }

                $val = '';
                if ($isStr) {
                    if (preg_match('/<is>.*?<t[^>]*>(.*?)<\/t>.*?<\/is>/s', $inner, $iv)) {
                        $val = $iv[1];
                    } elseif (preg_match('/<v>(.*?)<\/v>/s', $inner, $vv)) {
                        $idx = (int) $vv[1];
                        $val = $this->strings[$idx] ?? '';
                    }
                } elseif (preg_match('/<v>(.*?)<\/v>/s', $inner, $vv)) {
                    $val = $vv[1];
                }

                $cells[$col] = trim(html_entity_decode((string) $val));
            }
        }

        return $cells;
    }

    private function insertBatch(array $rows): void
    {
        foreach ($rows as $row) {
            DB::table('kode_barang')->insert($row);
        }
        $this->inserted += count($rows);
        $this->command->getOutput()->write("\r  {$this->inserted} baris dimasukkan...");
    }
}
