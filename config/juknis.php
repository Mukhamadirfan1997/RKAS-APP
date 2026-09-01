<?php

return [
    /*
    | Pemetaan komponen kepatuhan JUKNIS (Permendikdasmen No. 8/2026).
    | Presisi KARSA: satu pos belanja masuk ke sebuah komponen HANYA bila
    | kombinasi kode program (kegiatan) DAN rekening belanja keduanya terpetakan
    | ke komponen tersebut. Kombinasi (AND), bukan salah-satu (OR), agar tidak
    | salah klasifikasi dan tidak dihitung ganda.
    |
    | kode_program : daftar kode kegiatan/sub program yang diakui komponen.
    | jenis_belanja: daftar jenis belanja resmi ARKAS yang diakui komponen.
    | rekening     : daftar prefix kode rekening belanja yang diakui komponen.
    | keywords     : kata kunci uraian tambahan (diproses AND dengan program).
    |
    | Prioritas klasifikasi per item: honor > buku > sarpras (eksklusif).
    |
    | Batas honor bervariasi menurut status sekolah: negeri 20% / swasta 40%.
    | Semua batas dihitung dari TOTAL pagu satu tahun anggaran.
    */
    'honor' => [
        'batas_persen' => 20,
        'batas_persen_swasta' => 40,
        'kode_program' => ['07.12.01', '07.12.02', '07.12.03', '07.12.04'],
        'jenis_belanja' => ['Belanja Jasa'],
        'rekening' => ['5.1.02.02.01'],
        'keywords' => ['honor', 'honorarium'],
    ],
    'buku' => [
        'batas_persen' => 10,
        'kode_program' => ['03.02.02', '05.02.02', '05.02.03', '05.02.04', '05.02.05'],
        'jenis_belanja' => ['Belanja Modal Buku'],
        'rekening' => ['5.2.05'],
        'keywords' => ['buku'],
    ],
    'sarpras' => [
        'batas_persen' => 20,
        'kode_program' => [
            '05.08.01', '05.08.03', '05.08.05', '05.08.06', '05.08.10', '05.08.12',
        ],
        'jenis_belanja' => ['Belanja Jasa Pemeliharaan'],
        'rekening' => ['5.1.02.03'],
        'keywords' => [],
    ],
    'tahap1' => [
        'batas_persen' => 50,
    ],
];
