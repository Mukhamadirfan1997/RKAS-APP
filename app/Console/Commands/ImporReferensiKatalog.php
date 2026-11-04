<?php

namespace App\Console\Commands;

use App\Services\ReferensiKatalogImporter;
use Illuminate\Console\Command;

class ImporReferensiKatalog extends Command
{
    protected $signature = 'katalog:impor-referensi
                            {--uji-coba : Hitung hasil pemetaan tanpa menulis ke database}
                            {--berkas= : Path fixture JSON lain (default: fixture Barang Jasa & Modal 2026-11)}';

    protected $description = 'Impor Barang Jasa & Barang Modal dari referensi Dinas Pendidikan (hanya menambah, tidak menghapus)';

    public function handle(ReferensiKatalogImporter $importer): int
    {
        $ujiCoba = (bool) $this->option('uji-coba');
        $berkas = $this->option('berkas') ?: null;

        $this->info('Berkas : '.$importer->pathBerkas($berkas));
        $this->info('Mode   : '.($ujiCoba ? 'uji coba (tidak menulis)' : 'impor sungguhan'));

        $hasil = $importer->jalankan($ujiCoba, $berkas);

        $this->newLine();
        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Baris dibaca (unik)', number_format($hasil['total_baris'], 0, ',', '.')],
                ['Baris baru ditambahkan', number_format($hasil['baru'], 0, ',', '.')],
                ['Harga acuan diperbarui', number_format($hasil['harga_diperbarui'], 0, ',', '.')],
                ['Rekening kosong dilengkapi', number_format($hasil['rekening_dilengkapi'], 0, ',', '.')],
                ['Duplikat dilewati', number_format($hasil['duplikat_dilewati'], 0, ',', '.')],
            ]
        );

        $this->newLine();
        $this->line('Dasar pemetaan kode rekening:');
        foreach ($hasil['per_dasar'] as $dasar => $jumlah) {
            $this->line(sprintf('  %-18s %s baris', $dasar, number_format($jumlah, 0, ',', '.')));
        }

        $this->newLine();

        if ($ujiCoba) {
            $this->warn('Uji coba selesai — tidak ada data yang ditulis.');

            return self::SUCCESS;
        }

        $this->info('Impor selesai. Tidak ada barang yang dihapus.');

        return self::SUCCESS;
    }
}
