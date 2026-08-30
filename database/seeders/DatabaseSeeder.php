<?php

namespace Database\Seeders;

use App\Models\KodeBarang;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. User default
        User::firstOrCreate(
            ['email' => 'admin@sekolah.id'],
            [
                'name' => 'Operator RKAS',
                'password' => Hash::make('password'),
            ]
        );

        // 2. Pengaturan Sekolah
        PengaturanSekolah::firstOrCreate(
            ['id' => 1],
            [
                'npsn' => '20512345',
                'nama_sekolah' => 'SD NEGERI TOYANING 1',
                'nama_kepala_sekolah' => 'H. AHMAD FAUZI, S.Pd., M.M.',
                'nip_kepala_sekolah' => '19750815 200003 1 004',
                'nama_bendahara' => 'SITI NURHALIZA, S.Pd.',
                'nip_bendahara' => '19880412 201101 2 018',
                'alamat' => 'Jl. Pendidikan No. 12',
                'desa_kelurahan' => 'Toyaning',
                'kecamatan' => 'Rejoso',
                'kabupaten_kota' => 'Kabupaten Pasuruan',
                'provinsi' => 'Jawa Timur',
            ]
        );

        // 3. Tahun Anggaran 2026
        $ta = TahunAnggaran::firstOrCreate(
            ['tahun' => 2026],
            [
                'sumber_dana' => 'BOSP REGULER',
                'pagu_total' => 180320000,
                'pagu_tahap1' => 90160000,
                'pagu_tahap2' => 90160000,
                'is_active' => true,
                'status_pengesahan' => 'Disahkan',
            ]
        );

        // 4. Master Program (8 SNP)
        $programs = [
            ['kode' => '02.01.01', 'nama' => 'Pelaksanaan Pembelajaran dan Asesmen', 'standar_snp' => 'Standar Proses'],
            ['kode' => '03.02.01', 'nama' => 'Peningkatan Kompetensi Guru', 'standar_snp' => 'Standar Pendidik dan Tenaga Kependidikan'],
            ['kode' => '03.02.02', 'nama' => 'Peningkatan Kompetensi Kepala Sekolah', 'standar_snp' => 'Standar Pendidik dan Tenaga Kependidikan'],
            ['kode' => '04.01.01', 'nama' => 'Pembayaran Honor Guru Non-ASN', 'standar_snp' => 'Standar Pengelolaan'],
            ['kode' => '04.01.02', 'nama' => 'Pembayaran Honor Tenaga Kependidikan Non-ASN', 'standar_snp' => 'Standar Pengelolaan'],
            ['kode' => '05.08.01', 'nama' => 'Pemeliharaan Sarana dan Prasarana Sekolah', 'standar_snp' => 'Standar Sarana dan Prasarana'],
            ['kode' => '05.02.02', 'nama' => 'Penyediaan Buku Teks Utama Peserta Didik', 'standar_snp' => 'Standar Sarana dan Prasarana'],
            ['kode' => '06.01.01', 'nama' => 'Kegiatan Evaluasi dan Asesmen Nasional', 'standar_snp' => 'Standar Penilaian'],
            ['kode' => '01.01.01', 'nama' => 'Kegiatan diskusi kolaborasi guru dalam penyusunan kurikulum', 'standar_snp' => 'Pengembangan Standar Isi'],
            ['kode' => '03.03.01', 'nama' => 'Pembinaan Minat, Bakat dan Prestasi Siswa', 'standar_snp' => 'Standar Pendidik dan Tenaga Kependidikan'],
        ];

        foreach ($programs as $prog) {
            MasterProgram::firstOrCreate(['kode' => $prog['kode']], $prog);
        }

        // 5. Master Kode Rekening
        $rekenings = [
            ['kode' => '5.1.02.01.01.0052', 'nama' => 'Belanja Makanan dan Minuman Rapat', 'kategori_belanja' => 'BARJAS'],
            ['kode' => '5.1.02.01.01.0024', 'nama' => 'Belanja Alat Tulis Kantor (ATK)', 'kategori_belanja' => 'BARJAS'],
            ['kode' => '5.1.02.01.01.0026', 'nama' => 'Belanja Kertas dan Cover', 'kategori_belanja' => 'BARJAS'],
            ['kode' => '5.1.02.02.01.0003', 'nama' => 'Belanja Honorarium Guru Honorer', 'kategori_belanja' => 'HONOR'],
            ['kode' => '5.1.02.02.01.0014', 'nama' => 'Belanja Honorarium Tenaga Kependidikan', 'kategori_belanja' => 'HONOR'],
            ['kode' => '5.1.02.03.02.0405', 'nama' => 'Belanja Pemeliharaan Bangunan Gedung-Bangunan Tempat Kerja', 'kategori_belanja' => 'BARJAS'],
            ['kode' => '5.1.02.04.01.0001', 'nama' => 'Belanja Perjalanan Dinas Dalam Kota', 'kategori_belanja' => 'BARJAS'],
            ['kode' => '5.2.05.01.01.0001', 'nama' => 'Belanja Modal Buku Ilmu Pengetahuan Umum', 'kategori_belanja' => 'MODAL'],
            ['kode' => '5.2.02.08.01.0005', 'nama' => 'Belanja Modal Personal Computer / Laptop', 'kategori_belanja' => 'MODAL'],
        ];

        foreach ($rekenings as $rek) {
            MasterKodeRekening::firstOrCreate(['kode' => $rek['kode']], $rek);
        }

        // 6. Katalog Barang
        $barangs = [
            ['kode' => 'KB-001', 'nama' => 'Nasi Dus & Lauk Pauk (biasa)-Hidangan rapat/tamu', 'satuan_default' => 'dus', 'harga_acuan' => 30000],
            ['kode' => 'KB-002', 'nama' => 'Snack Kotak Rapat / Pertemuan Guru', 'satuan_default' => 'kotak', 'harga_acuan' => 15000],
            ['kode' => 'KB-003', 'nama' => 'Kertas HVS A4 75 gram (PaperOne/Sinar Dunia)', 'satuan_default' => 'rim', 'harga_acuan' => 55000],
            ['kode' => 'KB-004', 'nama' => 'Kertas HVS F4 75 gram (PaperOne/Sinar Dunia)', 'satuan_default' => 'rim', 'harga_acuan' => 60000],
            ['kode' => 'KB-005', 'nama' => 'Spidol Whiteboard Boardmarker Snowman Hitam', 'satuan_default' => 'lusin', 'harga_acuan' => 95000],
            ['kode' => 'KB-006', 'nama' => 'Tinta Spidol Whiteboard Isi Ulang Snowman', 'satuan_default' => 'botol', 'harga_acuan' => 22000],
            ['kode' => 'KB-007', 'nama' => 'Cat Tembok Interior 25 Kg Dulux / Nippon Paint', 'satuan_default' => 'pail', 'harga_acuan' => 650000],
            ['kode' => 'KB-008', 'nama' => 'Semen Gresik / Tiga Roda 40 Kg', 'satuan_default' => 'sak', 'harga_acuan' => 62000],
            ['kode' => 'KB-009', 'nama' => 'Perjalanan Dinas Luar Kecamatan', 'satuan_default' => 'Orang / Kali', 'harga_acuan' => 150000],
        ];

        foreach ($barangs as $brg) {
            KodeBarang::firstOrCreate(['kode' => $brg['kode']], $brg);
        }

        // 7. Seed Sample RKAS Item (Seperti pada gambar ARKAS)
        $progGuru = MasterProgram::where('kode', '03.02.01')->first();
        $rekMakan = MasterKodeRekening::where('kode', '5.1.02.01.01.0052')->first();
        $brgNasi = KodeBarang::where('kode', 'KB-001')->first();

        $item1 = RkasItem::firstOrCreate(
            ['tahun_anggaran_id' => $ta->id, 'no_urut' => 1],
            [
                'master_program_id' => $progGuru->id,
                'master_kode_rekening_id' => $rekMakan->id,
                'kode_barang_id' => $brgNasi->id,
                'uraian' => 'Nasi Dus & Lauk Pauk (biasa)-Hidangan rapat/tamu',
                'keterangan_kustom' => 'Konsumsi Rapat Peningkatan Kompetensi Guru',
                'volume' => 143, // 11 bulan x 13 dus
                'satuan' => 'dus',
                'harga_satuan' => 30000,
                'harga_satuan_arkas' => 30000,
                'jumlah' => 4290000,
            ]
        );

        // Alokasi 11 bulan @ 13 dus = Rp 390.000 / bulan (Januari s.d. November)
        for ($b = 1; $b <= 11; $b++) {
            RkasItemBulan::updateOrCreate(
                ['rkas_item_id' => $item1->id, 'bulan' => $b],
                [
                    'volume' => 13,
                    'satuan' => 'dus',
                    'jumlah' => 390000,
                ]
            );
        }

        // 8. Kategori JUKNIS & Pemetaan Rekening
        $this->call(JuknisSeeder::class);
    }
}
