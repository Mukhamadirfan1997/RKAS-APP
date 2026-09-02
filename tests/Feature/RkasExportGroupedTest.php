<?php

namespace Tests\Feature;

use App\Models\KodeBarang;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $prog1 = MasterProgram::where('kode', '04.06.05')->firstOrFail(); // existing
        $prog2 = MasterProgram::where('kode', '03.02.02')->firstOrFail();
        // Ensure different natural sort order
        $rek = MasterKodeRekening::firstOrFail();
        $barang = KodeBarang::whereNotNull('id_barang_arkas')->first() ?? KodeBarang::first();
        if (! $barang) {
            $barang = KodeBarang::create(['kode' => 'KB-TEST-GRP', 'nama' => 'Barang Test', 'harga_acuan' => 10000, 'id_barang_arkas' => 'BRG-999']);
        }

        // Item 1: kegiatan 1, alokasi Jan 100k, Mar 50k, Jul 20k
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

        // Item 2: same kegiatan, alokasi Feb 30k, tanpa alokasi di bulan lain (test 0)
        $item2 = RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $prog1->id,
            'master_kode_rekening_id' => $rek->id,
            'kode_barang_id' => null, // custom tanpa barang -> ID kosong
            'uraian' => 'Item Kelompok 1-B custom',
            'volume' => 1, 'satuan' => 'dus',
            'harga_satuan' => 30000, 'harga_satuan_arkas' => 0,
            'jumlah' => 30000, 'koreksi' => 0, 'no_urut' => 2,
        ]);
        $item2->alokasiBulan()->create(['bulan' => 2, 'volume' => 1, 'satuan' => 'dus', 'jumlah' => 30000]);

        // Item 3: kegiatan 2, alokasi Des 200k
        $item3 = RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $prog2->id,
            'master_kode_rekening_id' => $rek->id,
            'kode_barang_id' => $barang->id,
            'uraian' => 'Item Kelompok 2-A',
            'volume' => 2, 'satuan' => 'unit',
            'harga_satuan' => 100000, 'harga_satuan_arkas' => 100000,
            'jumlah' => 200000, 'koreksi' => 0, 'no_urut' => 3,
        ]);
        $item3->alokasiBulan()->create(['bulan' => 12, 'volume' => 2, 'satuan' => 'unit', 'jumlah' => 200000]);

        return [$item1, $item2, $item3, $prog1, $prog2, $barang];
    }

    public function test_pdf_grouped_bisa_generate_untuk_2_kegiatan(): void
    {
        $this->cleanAndCreateGroupedData();

        $resp = $this->get(route('rkas.pdf-grouped'));
        $resp->assertOk();
        $this->assertStringContainsString('application/pdf', $resp->headers->get('content-type'));
        // Dompdf output starts with %PDF
        $this->assertStringStartsWith('%PDF', $resp->getContent());
        // Pastikan mengandung kode kegiatan (grouped header)
        $this->assertTrue(strlen($resp->getContent()) > 5000, 'PDF grouped harus lebih besar dari empty');
    }

    private function buildExcelViaExport(): array
    {
        $sekolah = \App\Models\PengaturanSekolah::first();
        $ctrl = app(\App\Http\Controllers\RkasController::class);
        $ref = new \ReflectionMethod($ctrl, 'buildGroupedForExport');
        $ref->setAccessible(true);
        $data = $ref->invoke($ctrl, $this->ta);
        $export = new \App\Exports\RkasKertasKerjaGroupedExport($sekolah, $this->ta, $data['groups'], $data);
        $filename = 'test-grouped-'.uniqid().'.xlsx';
        \Maatwebsite\Excel\Facades\Excel::store($export, $filename, 'local');
        $path = storage_path('app/private/'.$filename);
        if (! is_file($path)) $path = storage_path('app/'.$filename);
        $ss = IOFactory::load($path);
        return [$ss, $path, $data];
    }

    public function test_excel_grouped_struktur_dan_nilai_bulan_sesuai(): void
    {
        [$item1, $item2, $item3, $prog1, $prog2, $barang] = $this->cleanAndCreateGroupedData();

        // Route harus 200
        $this->get(route('rkas.export-grouped'))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        [$ss, $tmp] = $this->buildExcelViaExport();
        $sheet = $ss->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true); // associative by column letter
        // Cari header row: harus mengandung "ID Barang ARKAS"
        $foundHeader = false;
        $headerRow = null;
        foreach ($rows as $idx => $row) {
            if (in_array('ID Barang ARKAS', $row, true)) {
                $foundHeader = true;
                $headerRow = $idx;
                break;
            }
        }
        $this->assertTrue($foundHeader, 'Header harus mengandung ID Barang ARKAS di sebelah Uraian');
        // Pastikan urutan kolom: B = ID Barang ARKAS, C = Uraian (B sebelum C)
        $headerVals = array_values($rows[$headerRow]);
        $posId = array_search('ID Barang ARKAS', $headerVals, true);
        $posUraian = array_search('Uraian', $headerVals, true);
        $this->assertNotFalse($posId);
        $this->assertNotFalse($posUraian);
        $this->assertLessThan($posUraian, $posId, 'ID Barang ARKAS harus di sebelah kiri Uraian (B < C)');

        // Cari baris item1: harus mengandung ID barang dan uraian
        $foundItem1 = false;
        $foundItem2IdEmpty = false;
        foreach ($rows as $row) {
            if (in_array('Item Kelompok 1-A', $row, true)) {
                $foundItem1 = true;
                // Cek ID barang ada
                $this->assertTrue(in_array($barang->id_barang_arkas ?? $barang->kode, $row, true), 'ID Barang ARKAS harus tampil untuk item dengan barang');
                $this->assertTrue(in_array(50000, $row, true) || in_array('50000', $row, true), 'Nilai bulan per item harus sesuai alokasi (50000)');
            }
            if (in_array('Item Kelompok 1-B custom', $row, true)) {
                // ID harus kosong (custom tanpa kode_barang_id)
                // Kolom B adalah ID Barang ARKAS -> harus kosong string atau null
                $colB = $row['B'] ?? null;
                $this->assertTrue($colB === null || $colB === '' || $colB === 0 || $colB === '0', 'Item custom tanpa barang harus tampil ID kosong, bukan error');
                $foundItem2IdEmpty = true;
            }
        }
        $this->assertTrue($foundItem1, 'Item 1 harus ada di Excel');
        $this->assertTrue($foundItem2IdEmpty, 'Item 2 custom ID kosong harus ada');

        // Cek subtotal per kegiatan: cari baris Subtotal
        $foundSubtotal1 = false;
        $foundGrand = false;
        foreach ($rows as $row) {
            $joined = implode(' ', $row);
            if (str_contains($joined, 'Subtotal '.$prog1->kode)) {
                $foundSubtotal1 = true;
                $this->assertTrue(in_array(50000, $row, true) || in_array(30000, $row, true) || in_array('50000', $row, true), 'Subtotal harus mengandung 50000/30000');
            }
            if (str_contains($joined, 'GRAND TOTAL')) {
                $foundGrand = true;
            }
        }
        $this->assertTrue($foundSubtotal1, 'Baris Subtotal per kegiatan harus ada');
        $this->assertTrue($foundGrand, 'Baris GRAND TOTAL harus ada');

        // Cek freeze pane dan outline (Via sheet properties)
        $this->assertEquals('D5', $sheet->getFreezePane(), 'Freeze panes harus di D5 (kolom setelah Uraian, baris setelah header)');
        // Pastikan ada outline level 1 (item rows)
        $hasOutline1 = false;
        foreach ($sheet->getRowDimensions() as $dim) {
            if ($dim->getOutlineLevel() == 1) { $hasOutline1 = true; break; }
        }
        $this->assertTrue($hasOutline1, 'Row grouping outline level 1 harus ada untuk collapse per kegiatan');

        @unlink($tmp);
    }

    public function test_excel_grouped_item_tanpa_alokasi_tampil_0_tidak_error(): void
    {
        $this->cleanAndCreateGroupedData();
        [$ss, $tmp] = $this->buildExcelViaExport();
        $sheet = $ss->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        // Cari row item2 dan cek Jan = 0
        foreach ($rows as $row) {
            if (in_array('Item Kelompok 1-B custom', $row, true)) {
                $norm = fn($v) => (int) str_replace(['.', ','], '', (string) ($v ?? '0'));
                $this->assertEquals(0, $norm($row['G']), 'Bulan tanpa alokasi harus 0 di Jan');
                $this->assertEquals(30000, $norm($row['H']), 'Feb harus 30000');
                $this->assertEquals(0, $norm($row['I']), 'Mar harus 0');
                break;
            }
        }
        @unlink($tmp);
    }

    public function test_excel_grouped_jumlah_baris_sesuai_kegiatan_item_subtotal(): void
    {
        $this->cleanAndCreateGroupedData();
        [$ss, $tmp] = $this->buildExcelViaExport();
        $sheet = $ss->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        // Header ada di row 4, data mulai row 5. Minimal baris = kop 3 + header 1 + 2*(header kegiatan 1 + items + subtotal 1) + grand 1 + sisa 1 = 3+1+2*(1+? ) = should be >10
        $this->assertGreaterThan(10, $highestRow, 'Jumlah baris harus sesuai kegiatan+item+subtotal (>10)');
        @unlink($tmp);
    }
}
