<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;

class BuildKatalogUpdate extends Command
{
    protected $signature = 'katalog:build-update
                            {--output=katalog-update.zip : Nama file output zip (relatif ke base_path atau absolute)}
                            {--source= : Path ke file .xlsm sumber (default dari ArkasKodeBarangSeeder)}
                            {--katalog-version= : Versi katalog (mis. 2027.01), auto dari tanggal jika kosong}';

    protected $description = 'Build paket update katalog (.zip berisi CSV + manifest.json) dari sumber .xlsm ARKAS';

    private array $strings = [];

    public function handle(): int
    {
        $source = $this->option('source');
        if (! $source) {
            $source = base_path('RKAS - KERTAS KERJA 2026 master V.2.TOYANING revisi harga edit.xlsm');
        }

        if (! is_file($source)) {
            $this->error("File sumber tidak ditemukan: {$source}");
            $this->line('Gunakan --source=path/ke/file.xlsm yang valid.');
            return self::FAILURE;
        }

        if (! str_ends_with(strtolower($source), '.xlsm') && ! str_ends_with(strtolower($source), '.xlsx')) {
            $this->warn("File sumber bukan .xlsm/.xlsx: {$source} — tetap coba baca, tapi bisa gagal.");
        }

        $output = $this->option('output') ?? 'katalog-update.zip';
        if (! str_starts_with($output, '/') && ! preg_match('/^[A-Za-z]:\\\\/', $output)) {
            $output = base_path($output);
        }

        $version = $this->option('katalog-version');
        if (! $version) {
            $version = now()->format('Y.m');
            $this->info("Versi tidak diisi, auto: {$version}");
        }

        $this->info("Membaca sumber: {$source}");
        $this->info("Output: {$output}");
        $this->info("Versi: {$version}");

        // Validasi struktur xlsm
        $zip = new ZipArchive;
        if ($zip->open($source) !== true) {
            $this->error('Gagal membuka file sebagai zip (xlsm). File korup atau bukan xlsm valid.');
            return self::FAILURE;
        }
        $hasSheet = $zip->getFromName('xl/worksheets/sheet5.xml') !== false;
        $hasStrings = $zip->getFromName('xl/sharedStrings.xml') !== false;
        $zip->close();
        if (! $hasSheet) {
            $this->error('Struktur tidak valid: xl/worksheets/sheet5.xml tidak ditemukan. Pastikan file adalah RKAS master dengan sheet KODE BARANG di sheet5.');
            return self::FAILURE;
        }
        if (! $hasStrings) {
            $this->warn('xl/sharedStrings.xml tidak ditemukan — mungkin xlsx tanpa shared strings, tetap lanjut.');
        }

        // Proses jadi CSV di temp
        $tmpDir = storage_path('app/katalog-build-'.uniqid());
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }
        $csvPath = $tmpDir.'/katalog.csv';
        $this->buildCsv($source, $csvPath);

        $jumlah = $this->countCsvRows($csvPath);
        if ($jumlah === 0) {
            $this->error('CSV hasil kosong (0 baris). Cek struktur kolom sumber.');
            return self::FAILURE;
        }

        $checksum = hash_file('sha256', $csvPath);
        $manifest = [
            'versi' => $version,
            'tanggal_generate' => now()->toIso8601String(),
            'jumlah_baris' => $jumlah,
            'checksum' => $checksum,
            'checksum_algo' => 'sha256',
            'source_file' => basename($source),
            'format' => 'csv',
            'csv_header' => ['kode','id_barang_arkas','nama','kode_rekening','satuan_default','harga_acuan','harga_min','harga_max','kode_belanja','kategori'],
            'catatan' => 'Paket update katalog KARSA — hanya update/insert match kode/id_barang_arkas, tidak hapus custom sekolah. Proses via upsert chunk 1000.',
        ];

        $manifestPath = $tmpDir.'/manifest.json';
        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Zip
        $zipOut = new ZipArchive;
        if ($zipOut->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Gagal membuat zip output: {$output}");
            return self::FAILURE;
        }
        $zipOut->addFile($csvPath, 'katalog.csv');
        $zipOut->addFile($manifestPath, 'manifest.json');
        $zipOut->close();

        $size = filesize($output);
        $this->info("Selesai: {$jumlah} baris, checksum {$checksum}");
        $this->info("Paket: {$output} (".number_format($size).' bytes)');

        // Bersihkan tmp csv tapi simpan manifest untuk debug? hapus semua
        @unlink($csvPath);
        @unlink($manifestPath);
        @rmdir($tmpDir);

        return self::SUCCESS;
    }

    private function buildCsv(string $source, string $csvPath): void
    {
        $this->loadSharedStrings($source);

        $zip = new ZipArchive;
        $zip->open($source);
        $xml = $zip->getFromName('xl/worksheets/sheet5.xml');
        $zip->close();

        $out = fopen($csvPath, 'w');
        // Header
        fputcsv($out, ['kode','id_barang_arkas','nama','kode_rekening','satuan_default','harga_acuan','harga_min','harga_max','kode_belanja','kategori']);

        $reader = new \XMLReader;
        $reader->XML($xml);
        $rowNo = 0;
        $written = 0;
        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->name !== 'row') {
                continue;
            }
            $rowNo++;
            if ($rowNo <= 2) {
                continue;
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
                continue;
            }
            $row = [
                $kode, // kode
                $kode, // id_barang_arkas
                $nama,
                trim((string) ($cells['B'] ?? '')),
                trim((string) ($cells['D'] ?? '')),
                (string) ($cells['E'] ?? '0'),
                (string) ($cells['F'] ?? '0'),
                (string) ($cells['G'] ?? '0'),
                trim((string) ($cells['H'] ?? '')),
                trim((string) ($cells['I'] ?? '')),
            ];
            fputcsv($out, $row);
            $written++;
            if ($written % 10000 === 0) {
                $this->info("  {$written} baris ditulis...");
            }
        }
        $reader->close();
        fclose($out);
        $this->info("CSV selesai: {$written} baris -> {$csvPath}");
    }

    private function loadSharedStrings(string $source): void
    {
        $this->strings = [];
        $zip = new ZipArchive;
        $zip->open($source);
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        if ($ss === false) {
            return;
        }
        preg_match_all('/<si>(.*?)<\/si>/s', $ss, $sis);
        foreach ($sis[1] as $raw) {
            $t = '';
            if (preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $raw, $ts)) {
                $t = implode('', $ts[1]);
            }
            $this->strings[] = html_entity_decode($t);
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

    private function countCsvRows(string $csvPath): int
    {
        $count = 0;
        $fh = fopen($csvPath, 'r');
        fgetcsv($fh); // header
        while (fgetcsv($fh) !== false) {
            $count++;
        }
        fclose($fh);
        return $count;
    }
}
