<?php

namespace Tests\Feature;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected TahunAnggaran $ta2026;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->user = User::first();
        $this->actingAs($this->user);
        $this->ta2026 = TahunAnggaran::where('tahun', 2026)->first();
    }

    public function test_kartu_pagu_sudah_sisa_menghitung_benar(): void
    {
        // Bersihkan item default agar hitungan deterministik
        RkasItem::where('tahun_anggaran_id', $this->ta2026->id)->delete();

        $prog = MasterProgram::firstOrFail();
        $rek = MasterKodeRekening::firstOrFail();

        // Buat 2 item: 100k + 50k = 150k
        foreach ([100000, 50000] as $idx => $jumlah) {
            RkasItem::create([
                'tahun_anggaran_id' => $this->ta2026->id,
                'master_program_id' => $prog->id,
                'master_kode_rekening_id' => $rek->id,
                'uraian' => 'Dashboard item '.$idx,
                'volume' => 1, 'satuan' => 'paket',
                'harga_satuan' => $jumlah, 'jumlah' => $jumlah,
            ]);
        }

        $resp = $this->get(route('dashboard.index'));
        $resp->assertOk();
        $resp->assertViewHas(['tahunAnggaran', 'summary', 'bulanData', 'proporsiJenis', 'totalItem']);

        $summary = $resp->viewData('summary');
        $this->assertEquals(180320000, (float) $summary['pagu_total']);
        $this->assertEquals(150000, (float) $summary['sudah_dianggarkan']);
        $this->assertEquals(180170000, (float) ($summary['pagu_total'] - $summary['sudah_dianggarkan']));

        $totalItem = $resp->viewData('totalItem');
        $this->assertEquals(2, $totalItem);

        // summary sisa via validator juga
        $this->assertEquals(150000, (float) $summary['sudah_dianggarkan']);
    }

    public function test_regresi_tanpa_klasifikasi_tidak_bocor_lintas_tahun(): void
    {
        // Bersihkan
        RkasItem::whereIn('tahun_anggaran_id', [$this->ta2026->id])->delete();

        // Buat tahun kedua 2025 (non-aktif) dengan item tanpa klasifikasi besar
        $ta2025 = TahunAnggaran::create([
            'tahun' => 2025,
            'sumber_dana' => 'BOSP REGULER',
            'pagu_total' => 100000000,
            'pagu_tahap1' => 50000000,
            'pagu_tahap2' => 50000000,
            'is_active' => false,
            'status_pengesahan' => 'Draft',
        ]);

        $prog = MasterProgram::firstOrFail();
        // Rekening tanpa jenis_belanja (Tanpa Klasifikasi)
        $rekTanpa = MasterKodeRekening::create(['kode' => '5.9.99.99.99.8888', 'nama' => 'Rek Tanpa Klasifikasi', 'jenis_belanja_id' => null]);

        // Item di 2025 tanpa klasifikasi 9 juta (harus TIDAK terhitung di dashboard 2026)
        RkasItem::create([
            'tahun_anggaran_id' => $ta2025->id,
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => $rekTanpa->id,
            'uraian' => 'Tanpa klasifikasi 2025',
            'volume' => 1, 'satuan' => 'paket',
            'harga_satuan' => 9000000, 'jumlah' => 9000000,
        ]);

        // Item di 2026 tanpa klasifikasi 1 juta
        $item2026 = RkasItem::create([
            'tahun_anggaran_id' => $this->ta2026->id,
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => $rekTanpa->id,
            'uraian' => 'Tanpa klasifikasi 2026',
            'volume' => 1, 'satuan' => 'paket',
            'harga_satuan' => 1000000, 'jumlah' => 1000000,
        ]);

        // Item di 2026 dengan rekening null (orWhereDoesntHave) 500k
        $itemNull = RkasItem::create([
            'tahun_anggaran_id' => $this->ta2026->id,
            'master_program_id' => $prog->id,
            'master_kode_rekening_id' => null,
            'uraian' => 'Tanpa rekening',
            'volume' => 1, 'satuan' => 'paket',
            'harga_satuan' => 500000, 'jumlah' => 500000,
        ]);

        $resp = $this->get(route('dashboard.index'));
        $resp->assertOk();
        $proporsi = $resp->viewData('proporsiJenis');

        // Tanpa Klasifikasi untuk 2026 harus 1.5 juta (1jt + 500k), BUKAN 10.5jt (termasuk 2025)
        $this->assertArrayHasKey('Tanpa Klasifikasi', $proporsi);
        $this->assertEquals(1500000, (float) $proporsi['Tanpa Klasifikasi'], 'Bug scoping: tanpa klasifikasi bocor lintas tahun jika where/orWhere tidak dibungkus');
        $this->assertNotEquals(10500000, (float) $proporsi['Tanpa Klasifikasi']);

        // totalItem hanya untuk 2026 (2 item)
        $this->assertEquals(2, $resp->viewData('totalItem'));
    }
}
