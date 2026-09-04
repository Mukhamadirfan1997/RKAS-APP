<?php

namespace Tests\Feature;

use App\Http\Controllers\RkasController;
use App\Models\KodeBarang;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RkasPdfPerBulanTest extends TestCase
{
    use RefreshDatabase;

    protected TahunAnggaran $ta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::first());
        $this->ta = TahunAnggaran::where('tahun', 2026)->first();
    }

    private function seedBulanItems(): array
    {
        RkasItem::where('tahun_anggaran_id', $this->ta->id)->delete();
        $prog = MasterProgram::firstOrFail();
        $rek = MasterKodeRekening::firstOrFail();
        $barang = KodeBarang::first() ?? KodeBarang::create(['kode'=>'KB-T','nama'=>'T','harga_acuan'=>10000,'id_barang_arkas'=>'BRG-1']);

        // Item A: hanya Januari (bulan 1) 50000
        $a = RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => $rek->id,
            'kode_barang_id' => $barang->id,
            'uraian' => 'Item Januari Saja',
            'volume' => 1, 'satuan' => 'paket',
            'harga_satuan' => 50000, 'harga_satuan_arkas' => 50000,
            'jumlah' => 50000, 'koreksi' => 0, 'no_urut' => 1,
        ]);
        $a->alokasiBulan()->create(['bulan'=>1,'volume'=>1,'satuan'=>'paket','jumlah'=>50000]);

        // Item B: hanya Februari (bulan 2) 80000
        $b = RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => $rek->id,
            'kode_barang_id' => null,
            'uraian' => 'Item Februari Saja',
            'volume' => 2, 'satuan' => 'dus',
            'harga_satuan' => 40000, 'harga_satuan_arkas' => 0,
            'jumlah' => 80000, 'koreksi' => 0, 'no_urut' => 2,
        ]);
        $b->alokasiBulan()->create(['bulan'=>2,'volume'=>2,'satuan'=>'dus','jumlah'=>80000]);

        // Item C: Januari + Februari (dua alokasi) 30000+30000
        $c = RkasItem::create([
            'tahun_anggaran_id' => $this->ta->id,
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => $rek->id,
            'kode_barang_id' => $barang->id,
            'uraian' => 'Item Jan-Feb Combo',
            'volume' => 2, 'satuan' => 'unit',
            'harga_satuan' => 30000, 'harga_satuan_arkas' => 30000,
            'jumlah' => 60000, 'koreksi' => 0, 'no_urut' => 3,
        ]);
        $c->alokasiBulan()->create(['bulan'=>1,'volume'=>1,'satuan'=>'unit','jumlah'=>30000]);
        $c->alokasiBulan()->create(['bulan'=>2,'volume'=>1,'satuan'=>'unit','jumlah'=>30000]);

        return [$a,$b,$c];
    }

    public function test_pdf_per_bulan_hanya_item_bulan_tersebut(): void
    {
        $this->seedBulanItems();
        $ctrl = app(RkasController::class);
        $ref = new \ReflectionMethod($ctrl, 'buildFlatForExport');
        $ref->setAccessible(true);

        $dataJan = $ref->invoke($ctrl, $this->ta, 'per_bulan', 1);
        $this->assertCount(2, $dataJan['items'], 'Januari harus 2 item (A + C)');
        $uraianJan = $dataJan['items']->pluck('uraian')->toArray();
        $this->assertContains('Item Januari Saja', $uraianJan);
        $this->assertContains('Item Jan-Feb Combo', $uraianJan);
        $this->assertNotContains('Item Februari Saja', $uraianJan);
        $this->assertEquals(80000, $dataJan['grandBulanTotal']);
        $this->assertEquals(80000, $dataJan['grandBulanJml']);
        // Volume per bulan
        foreach ($dataJan['items'] as $it) {
            $this->assertEquals($it->bulanJml[1], $it->jumlahBulan);
        }
        // flatGroups subtotal juga hanya bulan itu
        $this->assertEquals(80000, $dataJan['flatGroups']->first()['subBulanJml']);

        $dataFeb = $ref->invoke($ctrl, $this->ta, 'per_bulan', 2);
        $this->assertCount(2, $dataFeb['items'], 'Februari harus 2 item (B + C)');
        $uraianFeb = $dataFeb['items']->pluck('uraian')->toArray();
        $this->assertContains('Item Februari Saja', $uraianFeb);
        $this->assertContains('Item Jan-Feb Combo', $uraianFeb);
        $this->assertNotContains('Item Januari Saja', $uraianFeb);
        $this->assertEquals(110000, $dataFeb['grandBulanTotal']); // 80000+30000

        // Ganti bulan menghasilkan subset berbeda
        $this->assertNotEquals(
            $dataJan['items']->pluck('id')->sort()->values()->toArray(),
            $dataFeb['items']->pluck('id')->sort()->values()->toArray()
        );
    }

    public function test_pdf_per_bulan_route_filter_dan_kolom(): void
    {
        $this->seedBulanItems();
        $resp = $this->get(route('rkas.pdf-per-bulan', ['bulan'=>2]));
        $resp->assertOk();
        $this->assertStringContainsString('application/pdf', $resp->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $resp->getContent());

        $ctrl = app(RkasController::class);
        $ref = new \ReflectionMethod($ctrl, 'buildFlatForExport');
        $ref->setAccessible(true);
        $data = $ref->invoke($ctrl, $this->ta, 'per_bulan', 2);
        $sekolah = $this->ta->pengaturan ?? \App\Models\PengaturanSekolah::first();
        // Render html per-bulan untuk cek kolom
        $html = view('rkas.pdf-per-bulan', array_merge(['sekolah'=>$sekolah ?? \App\Models\PengaturanSekolah::first(),'tahunAnggaran'=>$this->ta], $data))->render();
        $this->assertStringContainsString('RINCIAN KERTAS KERJA PER BULAN', $html);
        $this->assertStringContainsString('Bulan: Februari', $html);
        $this->assertStringContainsString('Item Februari Saja', $html);
        $this->assertStringNotContainsString('Item Januari Saja', $html);
        // Kolom Jumlah ada, Kontrol ada, Validasi HAPUS
        $this->assertStringContainsString('Kontrol', $html);
        $this->assertStringNotContainsString('Validasi', $html);
        $this->assertStringContainsString('Volume', $html);
        // Subtotal per kegiatan hanya bulan itu
        $this->assertStringContainsString('Subtotal', $html);
        // Ringkasan Total Bulan Ini & persen
        $this->assertStringContainsString('Total Bulan Ini', $html);
        $this->assertStringContainsString('Rp '.number_format(110000,0,',','.'), $html);
    }

    public function test_pdf_per_bulan_nomor_urut_berurutan(): void
    {
        $this->seedBulanItems();
        $ctrl = app(RkasController::class);
        $ref = new \ReflectionMethod($ctrl, 'buildFlatForExport');
        $ref->setAccessible(true);
        $data = $ref->invoke($ctrl, $this->ta, 'per_bulan', 1);
        $sekolah = \App\Models\PengaturanSekolah::first();
        $html = view('rkas.pdf-per-bulan', array_merge(['sekolah'=>$sekolah,'tahunAnggaran'=>$this->ta], $data))->render();
        // No harus 1,2 berurutan (strip html untuk cari >1< dan >2<)
        $this->assertStringContainsString('>1<', $html);
        $this->assertStringContainsString('>2<', $html);
        // Pastikan kolom Validasi tidak ada (header Validasi dihapus untuk per-bulan) — cek th spesifik, bukan css
        $this->assertStringNotContainsString('Validasi<small>', $html);
        $this->assertStringNotContainsString('>Validasi<', $html);
        $this->assertStringContainsString('badge-kontrol', $html);
    }

    public function test_builder_mode_per_bulan_reuse_whereHas(): void
    {
        // Pastikan mode per_bulan memanggil whereHas (filter) — item Maret kosong harus 0
        $this->seedBulanItems(); // hanya Jan & Feb
        $ctrl = app(RkasController::class);
        $ref = new \ReflectionMethod($ctrl, 'buildFlatForExport');
        $ref->setAccessible(true);
        $dataMar = $ref->invoke($ctrl, $this->ta, 'per_bulan', 3);
        $this->assertCount(0, $dataMar['items']);
        $this->assertEquals(0, $dataMar['grandBulanTotal']);
        $html = view('rkas.pdf-per-bulan', array_merge(['sekolah'=>\App\Models\PengaturanSekolah::first(),'tahunAnggaran'=>$this->ta], $dataMar))->render();
        $this->assertStringContainsString('Tidak ada rincian belanja untuk bulan Maret', $html);
    }
}
