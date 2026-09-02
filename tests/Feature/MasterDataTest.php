<?php

namespace Tests\Feature;

use App\Models\JenisBelanja;
use App\Models\KodeBarang;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->user = User::first();
        $this->actingAs($this->user);
    }

    private function makeExcel(array $headers, array $rows): string
    {
        $ss = new Spreadsheet;
        $sheet = $ss->getActiveSheet();
        $colIndex = 1;
        foreach ($headers as $h) {
            $sheet->setCellValue([$colIndex, 1], $h);
            $colIndex++;
        }
        $rowNum = 2;
        foreach ($rows as $row) {
            $c = 1;
            foreach ($row as $val) {
                $sheet->setCellValue([$c, $rowNum], $val);
                $c++;
            }
            $rowNum++;
        }
        $path = sys_get_temp_dir().'/master-'.uniqid().'.xlsx';
        (new Xlsx($ss))->save($path);
        return $path;
    }

    private function excelFile(string $path): UploadedFile
    {
        return new UploadedFile($path, basename($path), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    // ===== PROGRAM =====
    public function test_store_program_valid(): void
    {
        $resp = $this->post(route('master.program.store'), [
            'kode' => '99.88.77',
            'nama' => 'Program Uji Baru',
            'program' => 'SNP Test',
            'sub_program' => 'Sub Test',
        ]);
        $resp->assertRedirect(route('master.program'));
        $resp->assertSessionHas('success');
        $this->assertDatabaseHas('master_program', ['kode' => '99.88.77', 'nama' => 'Program Uji Baru']);
    }

    public function test_store_program_duplicate_kode_rejected(): void
    {
        $existing = MasterProgram::first();
        $resp = $this->post(route('master.program.store'), [
            'kode' => $existing->kode,
            'nama' => 'Duplikat',
        ]);
        $resp->assertSessionHasErrors(['kode']);
        // pastikan tidak menambah duplikat
        $this->assertEquals(1, MasterProgram::where('kode', $existing->kode)->count());
    }

    public function test_update_program_duplicate_kode_rejected(): void
    {
        $a = MasterProgram::create(['kode' => '11.11.11', 'nama' => 'A']);
        $b = MasterProgram::create(['kode' => '22.22.22', 'nama' => 'B']);
        $resp = $this->post(route('master.program.update', $b->id), [
            'kode' => '11.11.11',
            'nama' => 'B Updated',
        ]);
        $resp->assertSessionHasErrors(['kode']);
        $this->assertDatabaseHas('master_program', ['id' => $b->id, 'kode' => '22.22.22']);
    }

    public function test_update_program_success(): void
    {
        $prog = MasterProgram::create(['kode' => '33.33.33', 'nama' => 'Old']);
        $resp = $this->post(route('master.program.update', $prog->id), [
            'kode' => '33.33.33',
            'nama' => 'New Name',
            'program' => 'SNP X',
        ]);
        $resp->assertRedirect(route('master.program'));
        $this->assertDatabaseHas('master_program', ['id' => $prog->id, 'nama' => 'New Name']);
    }

    public function test_destroy_program(): void
    {
        $prog = MasterProgram::create(['kode' => '44.44.44', 'nama' => 'To Delete']);
        $this->delete(route('master.program.destroy', $prog->id))
            ->assertRedirect(route('master.program'));
        $this->assertDatabaseMissing('master_program', ['id' => $prog->id]);
    }

    // ===== REKENING =====
    public function test_store_rekening_valid(): void
    {
        $jenis = JenisBelanja::first();
        $resp = $this->post(route('master.rekening.store'), [
            'kode' => '5.9.99.99.99.9999',
            'nama' => 'Rekening Uji Baru',
            'jenis_belanja_id' => $jenis->id,
        ]);
        $resp->assertRedirect(route('master.rekening'));
        $this->assertDatabaseHas('master_kode_rekening', ['kode' => '5.9.99.99.99.9999']);
    }

    public function test_store_rekening_duplicate_kode_rejected(): void
    {
        $existing = MasterKodeRekening::first();
        $resp = $this->post(route('master.rekening.store'), [
            'kode' => $existing->kode,
            'nama' => 'Duplikat Rek',
        ]);
        $resp->assertSessionHasErrors(['kode']);
    }

    public function test_update_rekening_duplicate_kode_rejected(): void
    {
        $a = MasterKodeRekening::create(['kode' => '5.9.11.11.11.1111', 'nama' => 'A']);
        $b = MasterKodeRekening::create(['kode' => '5.9.22.22.22.2222', 'nama' => 'B']);
        $resp = $this->post(route('master.rekening.update', $b->id), [
            'kode' => '5.9.11.11.11.1111',
            'nama' => 'B Updated',
        ]);
        $resp->assertSessionHasErrors(['kode']);
    }

    public function test_destroy_rekening(): void
    {
        $rek = MasterKodeRekening::create(['kode' => '5.9.33.33.33.3333', 'nama' => 'To Delete']);
        $this->delete(route('master.rekening.destroy', $rek->id))
            ->assertRedirect(route('master.rekening'));
        $this->assertDatabaseMissing('master_kode_rekening', ['id' => $rek->id]);
    }

    // ===== BARANG =====
    public function test_store_barang_valid(): void
    {
        $resp = $this->post(route('master.barang.store'), [
            'kode' => 'KB-TEST-001',
            'nama' => 'Barang Uji Baru Master',
            'satuan_default' => 'unit',
            'harga_acuan' => 12345,
        ]);
        $resp->assertRedirect(route('master.barang'));
        $this->assertDatabaseHas('kode_barang', ['nama' => 'Barang Uji Baru Master']);
    }

    public function test_update_barang_success(): void
    {
        $brg = KodeBarang::create(['kode' => 'KB-UPD', 'nama' => 'Old Barang', 'harga_acuan' => 1000]);
        $resp = $this->post(route('master.barang.update', $brg->id), [
            'kode' => 'KB-UPD',
            'nama' => 'New Barang Name',
            'satuan_default' => 'dus',
            'harga_acuan' => 9999,
        ]);
        $resp->assertRedirect(route('master.barang'));
        $this->assertDatabaseHas('kode_barang', ['id' => $brg->id, 'nama' => 'New Barang Name']);
    }

    public function test_destroy_barang(): void
    {
        $brg = KodeBarang::create(['kode' => 'KB-DEL', 'nama' => 'To Delete Barang', 'harga_acuan' => 500]);
        $this->delete(route('master.barang.destroy', $brg->id))
            ->assertRedirect(route('master.barang'));
        $this->assertDatabaseMissing('kode_barang', ['id' => $brg->id]);
    }

    // ===== IMPORT PROGRAM =====
    public function test_import_program_valid_and_skip_empty_rows(): void
    {
        $headers = ['kode', 'nama', 'program'];
        $rows = [
            ['99.99.01', 'Program Import Valid', 'SNP A'],
            ['', 'Tanpa Kode', 'SNP A'], // skip: kode kosong
            ['99.99.02', '', 'SNP B'], // skip: nama kosong
            ['99.99.03', 'Program Valid 2', 'SNP B'],
        ];
        $path = $this->makeExcel($headers, $rows);
        $file = $this->excelFile($path);

        $resp = $this->post(route('master.import'), ['file' => $file, 'target' => 'program']);
        $resp->assertRedirect(route('master.program'));
        $resp->assertSessionHas('success');
        $msg = session('success');
        // Pesan ringkasan harus menyebut jumlah dilewati
        $this->assertStringContainsString('dilewati', strtolower($msg));

        $this->assertDatabaseHas('master_program', ['kode' => '99.99.01']);
        $this->assertDatabaseHas('master_program', ['kode' => '99.99.03']);
        $this->assertDatabaseMissing('master_program', ['kode' => '99.99.02']); // karena nama kosong
        @unlink($path);
    }

    public function test_import_program_header_unknown_not_crash(): void
    {
        $headers = ['foo', 'bar', 'baz'];
        $rows = [
            ['x', 'y', 'z'],
        ];
        $path = $this->makeExcel($headers, $rows);
        $file = $this->excelFile($path);

        $resp = $this->post(route('master.import'), ['file' => $file, 'target' => 'program']);
        // Tidak boleh 500
        $this->assertNotEquals(500, $resp->getStatusCode());
        // Harus redirect dengan pesan sukses (0 imported) atau error yang jelas, bukan exception
        $this->assertTrue($resp->isRedirect() || $resp->isOk());
        if ($resp->isRedirect()) {
            // Jika success, pastikan tidak ada data dengan kode x yang masuk (karena skip)
            $this->assertDatabaseMissing('master_program', ['kode' => 'x']);
        }
        @unlink($path);
    }

    public function test_import_program_first_or_create_no_overwrite(): void
    {
        // Buat data awal
        MasterProgram::create(['kode' => '88.88.88', 'nama' => 'Original Name', 'program' => 'SNP Orig']);

        $headers = ['kode', 'nama', 'program'];
        $rows1 = [
            ['88.88.88', 'Original Name', 'SNP Orig'],
        ];
        $path1 = $this->makeExcel($headers, $rows1);
        $file1 = $this->excelFile($path1);
        $this->post(route('master.import'), ['file' => $file1, 'target' => 'program']);
        @unlink($path1);

        // Upload kedua dengan nama beda di kode sama — harus tidak menimpa
        $rows2 = [
            ['88.88.88', 'Hacked Name', 'SNP Hack'],
        ];
        $path2 = $this->makeExcel($headers, $rows2);
        $file2 = $this->excelFile($path2);
        $resp2 = $this->post(route('master.import'), ['file' => $file2, 'target' => 'program']);
        $resp2->assertRedirect(route('master.program'));
        @unlink($path2);

        // Assert tetap Original
        $prog = MasterProgram::where('kode', '88.88.88')->first();
        $this->assertEquals('Original Name', $prog->nama);
        $this->assertEquals('SNP Orig', $prog->program);
        // Pastikan tidak duplikat
        $this->assertEquals(1, MasterProgram::where('kode', '88.88.88')->count());
    }

    // ===== IMPORT REKENING & BARANG =====
    public function test_import_rekening_valid(): void
    {
        $headers = ['kode', 'nama'];
        $rows = [
            ['5.9.88.88.88.8888', 'Rekening Import Rek'],
        ];
        $path = $this->makeExcel($headers, $rows);
        $file = $this->excelFile($path);
        $resp = $this->post(route('master.import'), ['file' => $file, 'target' => 'rekening']);
        $resp->assertRedirect(route('master.rekening'));
        $resp->assertSessionHas('success');
        $this->assertDatabaseHas('master_kode_rekening', ['kode' => '5.9.88.88.88.8888']);
        @unlink($path);
    }

    public function test_import_barang_valid(): void
    {
        $headers = ['nama', 'kode', 'satuan', 'harga_acuan'];
        $rows = [
            ['Barang Import Test', 'KB-IMP-001', 'buah', 5000],
        ];
        $path = $this->makeExcel($headers, $rows);
        $file = $this->excelFile($path);
        $resp = $this->post(route('master.import'), ['file' => $file, 'target' => 'barang']);
        $resp->assertRedirect(route('master.barang'));
        $resp->assertSessionHas('success');
        $this->assertDatabaseHas('kode_barang', ['nama' => 'Barang Import Test']);
        @unlink($path);
    }

    public function test_import_barang_skip_empty_nama(): void
    {
        $headers = ['nama', 'kode'];
        $rows = [
            ['', 'KB-EMPTY'],
            ['Barang Valid After Skip', 'KB-VALID'],
        ];
        $path = $this->makeExcel($headers, $rows);
        $file = $this->excelFile($path);
        $resp = $this->post(route('master.import'), ['file' => $file, 'target' => 'barang']);
        $resp->assertRedirect(route('master.barang'));
        $msg = session('success');
        $this->assertStringContainsString('dilewati', strtolower($msg));
        $this->assertDatabaseHas('kode_barang', ['nama' => 'Barang Valid After Skip']);
        $this->assertDatabaseMissing('kode_barang', ['kode' => 'KB-EMPTY']);
        @unlink($path);
    }
}
