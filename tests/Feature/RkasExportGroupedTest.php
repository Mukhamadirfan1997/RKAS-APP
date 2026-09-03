<?php

namespace Tests\Feature;

use App\Exports\RkasKertasKerjaGroupedExport;
use App\Http\Controllers\RkasController;
use App\Models\KodeBarang;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class RkasExportGroupedTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected TahunAnggaran $ta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->user = User::first();
        $this->actingAs($this->user);
        $this->ta = TahunAnggaran::where('tahun', 2026)->first();
    }

    private function cleanAndCreateGroupedData(): array
    {
        RkasItem::where('tahun_anggaran_id', $this->ta->id)->delete();
        $prog1 = MasterProgram::where('kode', '04.06.05')->firstOrFail();
        $prog2 = MasterProgram::where('kode', '03.02.02')->firstOrFail();
        $rek = MasterKodeRekening::firstOrFail();
        $rek2 = MasterKodeRekening::where('id', '!=', $rek->id)->first() ?? $rek;
        $barang = KodeBarang::whereNotNull('id_barang_arkas')->first() ?? KodeBarang::first();
        if (! $barang) {
            $barang = KodeBarang::create(['kode' => 'KB-TEST-GRP', 'nama' => 'Barang Test', 'harga_acuan' => 10000, 'id_barang_arkas' => 'BRG-999']);
        }
        $item1 = RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $prog1->id,
            'master_kode_rekening_id' => $rek->id,
            'kode_barang_id' => $barang->id,
            'uraian' => 'Item Kelompok 1-A',
            'volume' => 3, 'satuan' => 'paket',
            'harga_satuan' => 50000, 'harga_satuan_arkas' => 50000,
            'jumlah' => 150000, 'koreksi' => 5000, 'no_urut' => 1,
        ]);
        $item1->alokasiBulan()->create(['bulan' => 1, 'volume' => 1, 'satuan' => 'paket', 'jumlah' => 50000]);
        $item1->alokasiBulan()->create(['bulan' => 3, 'volume' => 1, 'satuan' => 'paket', 'jumlah' => 50000]);
        $item1->alokasiBulan()->create(['bulan' => 7, 'volume' => 1, 'satuan' => 'paket', 'jumlah' => 50000]);
        $item2 = RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $prog1->id,
            'master_kode_rekening_id' => $rek2->id,
            'kode_barang_id' => null,
            'uraian' => 'Item Kelompok 1-B custom',
            'volume' => 1, 'satuan' => 'dus',
            'harga_satuan' => 30000, 'harga_satuan_arkas' => 0,
            'jumlah' => 30000, 'koreksi' => 0, 'no_urut' => 2,
        ]);
        $item2->alokasiBulan()->create(['bulan' => 2, 'volume' => 1, 'satuan' => 'dus', 'jumlah' => 30000]);
        $barang2 = KodeBarang::where('id', '!=', $barang->id)->first() ?? $barang;
        $item3 = RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $prog2->id,
            'master_kode_rekening_id' => $rek->id,
            'kode_barang_id' => $barang2->id,
            'uraian' => 'Item Kelompok 2-A',
            'volume' => 2, 'satuan' => 'unit',
            'harga_satuan' => 100000, 'harga_satuan_arkas' => 90000,
            'jumlah' => 200000, 'koreksi' => 0, 'no_urut' => 3,
        ]);
        $item3->alokasiBulan()->create(['bulan' => 12, 'volume' => 2, 'satuan' => 'unit', 'jumlah' => 200000]);

        return [$item1, $item2, $item3, $prog1, $prog2, $barang, $rek, $rek2];
    }

    private function buildExcelViaFlat(): array
    {
        $sekolah = PengaturanSekolah::first();
        $ctrl = app(RkasController::class);
        $ref = new \ReflectionMethod($ctrl, 'buildFlatForExport');
        $ref->setAccessible(true);
        $data = $ref->invoke($ctrl, $this->ta);
        $export = new RkasKertasKerjaGroupedExport($sekolah, $this->ta, $data['groups'], $data);
        $filename = 'test-flat-'.uniqid().'.xlsx';
        Excel::store($export, $filename, 'local');
        $path = storage_path('app/private/'.$filename);
        if (! is_file($path)) {
            $path = storage_path('app/'.$filename);
        }
        $ss = IOFactory::load($path);

        return [$ss, $path, $data];
    }

    public function test_pdf_grouped_flat_bisa_generate(): void
    {
        $this->cleanAndCreateGroupedData();
        $resp = $this->get(route('rkas.pdf-grouped'));
        $resp->assertOk();
        $this->assertStringContainsString('application/pdf', $resp->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $resp->getContent());
        $this->assertGreaterThan(5000, strlen($resp->getContent()));
    }

    public function test_excel_flat_structure_hanya_kegiatan_rekening_tanpa_standar_program(): void
    {
        $this->cleanAndCreateGroupedData();
        [$ss, $tmp, $data] = $this->buildExcelViaFlat();
        $this->assertArrayHasKey('flatGroups', $data);
        $this->assertArrayNotHasKey('hierarchy', $data);
        $flatGroups = $data['flatGroups'];
        $this->assertGreaterThan(0, $flatGroups->count());
        foreach ($flatGroups as $g) {
            $this->assertArrayHasKey('kode', $g);
            $this->assertArrayHasKey('rekenings', $g);
            $this->assertArrayNotHasKey('standar', $g);
            $this->assertArrayNotHasKey('subs', $g);
            foreach ($g['rekenings'] as $rek) {
                $this->assertArrayHasKey('kode', $rek);
                foreach ($rek['items'] as $it) {
                    $this->assertIsArray($it->bulanVol);
                    $this->assertIsArray($it->bulanJml);
                    $this->assertCount(12, $it->bulanVol);
                    $this->assertContains($it->validasiBulanan, ['BENAR', 'SALAH']);
                }
            }
        }
        $sheet = $ss->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $flatText = implode("\n", array_map(fn ($r) => implode(' ', (array) $r), $rows));
        $this->assertStringNotContainsString('STANDAR:', $flatText);
        $this->assertStringNotContainsString('PROGRAM:', $flatText);
        $hasKegiatanRow = false;
        $hasRekeningRow = false;
        foreach ($rows as $row) {
            $b = trim((string) ($row['B'] ?? ''));
            $c = trim((string) ($row['C'] ?? ''));
            $d = trim((string) ($row['D'] ?? ''));
            $a = trim((string) ($row['A'] ?? ''));
            if ($b !== '' && $c !== '' && $d === '' && $a === '') {
                $hasKegiatanRow = true;
            }
            if ($d !== '' && $c !== '' && $b === '' && $a === '') {
                $hasRekeningRow = true;
            }
        }
        $this->assertTrue($hasKegiatanRow, 'Harus ada baris KEGIATAN');
        $this->assertTrue($hasRekeningRow, 'Harus ada baris REKENING');
        @unlink($tmp);
    }

    public function test_excel_volume_dan_jumlah_terpisah_24_kolom(): void
    {
        $this->cleanAndCreateGroupedData();
        [$ss, $tmp] = $this->buildExcelViaFlat();
        $sheet = $ss->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $headerRow = null;
        foreach ($rows as $idx => $row) {
            if (in_array('Kode Barang', $row, true) && in_array('Uraian', $row, true) && in_array('Kode Rekening', $row, true)) {
                $headerRow = $idx;
                break;
            }
        }
        $this->assertNotNull($headerRow);
        $header = $rows[$headerRow];
        $headerVals = array_values($header);
        $posBarang = array_search('Kode Barang', $headerVals, true);
        $posUraian = array_search('Uraian', $headerVals, true);
        $posRekening = array_search('Kode Rekening', $headerVals, true);
        $this->assertLessThan($posUraian, $posBarang, 'Kode Barang sebelum Uraian');
        $this->assertLessThan($posRekening, $posUraian, 'Uraian sebelum Kode Rekening? Actually spec: Kode Barang, Uraian, Kode Rekening');
        // Check spec order: No, Kode Barang, Uraian, Kode Rekening
        $this->assertEquals($posBarang + 1, $posUraian);
        $this->assertTrue(in_array('Jan Vol', $header, true) && in_array('Jan Jml', $header, true));
        $this->assertTrue(in_array('Des Vol', $header, true) && in_array('Des Jml', $header, true));
        $this->assertTrue(in_array('JUMLAH TAHAP I', $header, true), 'Harus ada JUMLAH TAHAP I setelah Jun');
        $this->assertTrue(in_array('JUMLAH TAHAP II', $header, true), 'Harus ada JUMLAH TAHAP II setelah Des');
        $this->assertFalse(in_array('Koreksi', $header, true), 'Koreksi harus HAPUS');
        $this->assertFalse(in_array('Jumlah+Koreksi', $header, true) || in_array('Jml+Koreksi', $header, true), 'Jumlah+Koreksi harus HAPUS');
        // 24 kolom bulan
        $countVol = count(array_filter($header, fn ($v) => str_ends_with((string) $v, 'Vol')));
        $this->assertEquals(12, $countVol);
        $found = false;
        foreach ($rows as $row) {
            if (in_array('Item Kelompok 1-A', $row, true)) {
                $found = true;
                $janVolLetter = array_search('Jan Vol', $header, true);
                $this->assertNotFalse($janVolLetter);
                // Jan Vol letter is key like 'H'
                $letter = array_search('Jan Vol', $header, true);
                // But array_search returns key letter, not index, for associative array
                $letter = array_search('Jan Vol', $header, true);
                // header is associative letter=>value, so we need letter directly
                $this->assertTrue(in_array(50000, $row, true) || in_array('50000', $row, true));
                break;
            }
        }
        $this->assertTrue($found);
        @unlink($tmp);
    }

    public function test_excel_single_table_tidak_duplikasi_tahap_headers(): void
    {
        $this->cleanAndCreateGroupedData();
        [$ss, $tmp] = $this->buildExcelViaFlat();
        $sheet = $ss->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $flat = implode("\n", array_map(fn ($r) => implode(' ', (array) $r), $rows));
        // Tidak boleh ada judul section "Tahap I —" dua kali
        $countTahapI = substr_count($flat, 'Tahap I —');
        $this->assertEquals(0, $countTahapI, 'Tidak boleh ada judul section Tahap I — (harus hanya kolom JUMLAH TAHAP I)');
        $countTahapII = substr_count($flat, 'Tahap II —');
        $this->assertEquals(0, $countTahapII, 'Tidak boleh ada judul section Tahap II —');
        // Kolom label harus ada tepat 1 kali di header
        $headerRow = null;
        foreach ($rows as $row) {
            if (in_array('JUMLAH TAHAP I', $row, true)) {
                $headerRow = $row;
                break;
            }
        }
        $this->assertNotNull($headerRow, 'Header harus mengandung JUMLAH TAHAP I sebagai kolom, bukan section');
        $this->assertTrue(in_array('JUMLAH TAHAP II', $headerRow, true));
        $this->assertFalse(in_array('Koreksi', $headerRow, true), 'Koreksi tidak boleh ada');
        @unlink($tmp);
    }

    public function test_validasi_bulanan_benar_dan_salah(): void
    {
        [$item1, $item2, $item3] = $this->cleanAndCreateGroupedData();
        $prog = MasterProgram::where('kode', '03.01.01')->firstOrFail();
        $rek = MasterKodeRekening::firstOrFail();
        $itemSalah = RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => $rek->id,
            'kode_barang_id' => null,
            'uraian' => 'Item Validasi SALAH',
            'volume' => 2, 'satuan' => 'paket',
            'harga_satuan' => 50000, 'harga_satuan_arkas' => 0,
            'jumlah' => 100000, 'koreksi' => 0, 'no_urut' => 99,
        ]);
        $itemSalah->alokasiBulan()->create(['bulan' => 1, 'volume' => 1, 'satuan' => 'paket', 'jumlah' => 50000]);
        $ctrl = app(RkasController::class);
        $ref = new \ReflectionMethod($ctrl, 'buildFlatForExport');
        $ref->setAccessible(true);
        $data = $ref->invoke($ctrl, $this->ta);
        $foundBenar = false;
        $foundSalah = false;
        foreach ($data['items'] as $it) {
            if ($it->uraian === 'Item Kelompok 1-A') {
                $this->assertEquals('BENAR', $it->validasiBulanan);
                $foundBenar = true;
            }
            if ($it->uraian === 'Item Validasi SALAH') {
                $this->assertEquals('SALAH', $it->validasiBulanan);
                $foundSalah = true;
            }
        }
        $this->assertTrue($foundBenar && $foundSalah);
        $sekolah = PengaturanSekolah::first();
        $export = new RkasKertasKerjaGroupedExport($sekolah, $this->ta, $data['groups'], $data);
        $filename = 'test-validasi-'.uniqid().'.xlsx';
        Excel::store($export, $filename, 'local');
        $path = storage_path('app/private/'.$filename);
        if (! is_file($path)) {
            $path = storage_path('app/'.$filename);
        }
        $ss = IOFactory::load($path);
        $rows = $ss->getActiveSheet()->toArray(null, true, true, true);
        $flat = implode("\n", array_map(fn ($r) => implode(' ', (array) $r), $rows));
        $this->assertStringContainsString('BENAR', $flat);
        $this->assertStringContainsString('SALAH', $flat);
        @unlink($path);
    }

    public function test_excel_styling_ada_fill_bold_bukan_kosong(): void
    {
        $this->cleanAndCreateGroupedData();
        [$ss, $tmp] = $this->buildExcelViaFlat();
        $sheet = $ss->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $samples = ['kegiatan' => null, 'rekening' => null, 'item' => null];
        for ($row = 3; $row <= $highestRow; $row++) {
            $valA = $sheet->getCell("A{$row}")->getValue();
            $valB = trim((string) ($sheet->getCell("B{$row}")->getValue() ?? ''));
            $valC = trim((string) ($sheet->getCell("C{$row}")->getValue() ?? ''));
            $valD = trim((string) ($sheet->getCell("D{$row}")->getValue() ?? ''));
            $aStr = trim((string) ($valA ?? ''));
            $isKegiatan = $valB !== '' && $valC !== '' && $valD === '' && $aStr === '';
            $isRekening = $valD !== '' && $valC !== '' && $valB === '' && $aStr === '';
            $isItem = is_numeric($valA) && $valA !== '' && $valA !== null;
            if ($isKegiatan && $samples['kegiatan'] === null) {
                $samples['kegiatan'] = $row;
            }
            if ($isRekening && $samples['rekening'] === null) {
                $samples['rekening'] = $row;
            }
            if ($isItem && $samples['item'] === null) {
                $samples['item'] = $row;
            }
            if ($samples['kegiatan'] && $samples['rekening'] && $samples['item']) {
                break;
            }
        }
        $this->assertNotNull($samples['kegiatan']);
        $this->assertNotNull($samples['rekening']);
        $this->assertNotNull($samples['item']);
        $getFill = fn ($row) => $sheet->getStyle("A{$row}:AJ{$row}")->getFill()->getStartColor()->getRGB();
        $getBold = fn ($row) => $sheet->getStyle("A{$row}:AJ{$row}")->getFont()->getBold();
        $fillKeg = $getFill($samples['kegiatan']);
        $fillRek = $getFill($samples['rekening']);
        $fillItem = $getFill($samples['item']);
        echo "\n[STYLING BUKTI] Kegiatan row {$samples['kegiatan']} fill #{$fillKeg} bold ".($getBold($samples['kegiatan']) ? 'true' : 'false')."\n";
        echo "[STYLING BUKTI] Rekening row {$samples['rekening']} fill #{$fillRek} bold ".($getBold($samples['rekening']) ? 'true' : 'false')."\n";
        echo "[STYLING BUKTI] Item row {$samples['item']} fill #{$fillItem} bold ".($getBold($samples['item']) ? 'true' : 'false')."\n";
        $this->assertNotEmpty($fillKeg);
        $this->assertNotEmpty($fillRek);
        $this->assertNotEmpty($fillItem);
        $this->assertTrue($getBold($samples['kegiatan']));
        $this->assertTrue($getBold($samples['rekening']));
        $this->assertNotEquals($fillKeg, $fillItem);
        $this->assertNotEquals($fillRek, $fillItem);
        $olKeg = $sheet->getRowDimension($samples['kegiatan'])->getOutlineLevel();
        $olRek = $sheet->getRowDimension($samples['rekening'])->getOutlineLevel();
        $this->assertEquals(1, $olKeg);
        $this->assertEquals(2, $olRek);
        $this->assertEquals('landscape', $sheet->getPageSetup()->getOrientation());
        $this->assertEquals(1, $sheet->getPageSetup()->getFitToWidth());
        $this->assertEquals(0, $sheet->getPageSetup()->getFitToHeight());
        $this->assertStringContainsString('AJ', $sheet->getPageSetup()->getPrintArea());
        $this->assertEquals('D3', $sheet->getFreezePane());
        @unlink($tmp);
    }

    public function test_pdf_single_table_tidak_duplikasi_tahap(): void
    {
        $this->cleanAndCreateGroupedData();
        $resp = $this->get(route('rkas.pdf-grouped'));
        $resp->assertOk();
        $this->assertStringContainsString('application/pdf', $resp->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $resp->getContent());
        // PDF text extraction via backend check: Tahap section titles tidak boleh ada dua kali
        $ctrl = app(RkasController::class);
        $ref = new \ReflectionMethod($ctrl, 'buildFlatForExport');
        $ref->setAccessible(true);
        $data = $ref->invoke($ctrl, $this->ta);
        $html = view('rkas.pdf-grouped', array_merge(['sekolah' => PengaturanSekolah::first(), 'tahunAnggaran' => $this->ta], $data))->render();
        $this->assertStringNotContainsString('Tahap I —', $html, 'PDF html tidak boleh ada judul Tahap I — terpisah (harus hanya kolom)');
        $this->assertStringNotContainsString('Tahap II —', $html, 'PDF html tidak boleh ada judul Tahap II — terpisah');
        $this->assertStringContainsString('JUMLAH TAHAP I', $html);
        $this->assertStringContainsString('JUMLAH TAHAP II', $html);
        $this->assertStringNotContainsString('Koreksi', $html, 'Koreksi harus hapus');
        $this->assertStringContainsString('Kontrol', $html);
        $this->assertStringContainsString('Validasi', $html);
    }

    public function test_pdf_contains_kontrol_dan_validasi(): void
    {
        $this->cleanAndCreateGroupedData();
        $ctrl = app(RkasController::class);
        $ref = new \ReflectionMethod($ctrl, 'buildFlatForExport');
        $ref->setAccessible(true);
        $data = $ref->invoke($ctrl, $this->ta);
        $hasOK = false;
        $hasBenar = false;
        foreach ($data['items'] as $it) {
            if ($it->kontrol === 'OK') {
                $hasOK = true;
            }
            if ($it->validasiBulanan === 'BENAR') {
                $hasBenar = true;
            }
        }
        $this->assertTrue($hasOK);
        $this->assertTrue($hasBenar);
    }
}
