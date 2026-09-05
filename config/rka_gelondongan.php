<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mapping 3 Kategori Dinas — Cek RKA Gelondongan
    |--------------------------------------------------------------------------
    | Final, dikonfirmasi user — JANGAN diubah tanpa persetujuan Dinas.
    | Kalau Dinas ubah aturan lagi, cukup edit file ini satu tempat.
    |
    | Tiap key adalah kategori Dinas, valuenya daftar verbatim
    | jenis_belanja.nama yang masuk kategori tersebut.
    |
    | - Belanja Barang dan Jasa        = 6 jenis (semua BARJAS non-modal)
    | - Modal Mesin                    = 1 jenis (Peralatan & Mesin)
    | - Modal Aset Tetap Lainnya       = 2 jenis (Aset Tetap Lainnya + Modal Buku)
    |
    | 6 + 1 + 2 = 9 jenis_belanja resmi ARKAS — tidak ada sisa.
    | Item tanpa klasifikasi (rekening/jenis NULL) TIDAK masuk hitungan
    | gelondongan; mereka akan tampak sebagai selisih vs Pagu Total.
    |
    */
    'mapping' => [
        'barang_jasa' => [
            'Belanja Barang',
            'Belanja Barang Persediaan',
            'Belanja Cetak',
            'Belanja Jasa',
            'Belanja Jasa Pemeliharaan',
            'Belanja Perjalanan Dinas',
        ],
        'modal_mesin' => [
            'Belanja Modal Peralatan & Mesin',
        ],
        'modal_aset_lainnya' => [
            'Belanja Modal Aset Tetap Lainnya',
            'Belanja Modal Buku',
        ],
    ],

    /*
    | Label kolom persis urutan & wording file Dinas (untuk header tabel).
    */
    'labels' => [
        'barang_jasa' => 'Belanja Barang dan Jasa',
        'modal_mesin' => 'Modal Mesin',
        'modal_aset_lainnya' => 'Modal Aset Tetap Lainnya (termasuk modal buku)',
        'jumlah' => 'Jumlah',
    ],
];
