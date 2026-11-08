<?php

return [
    /*
    | Pemetaan komponen kepatuhan JUKNIS (Permendikdasmen No. 8/2026).
    | Presisi KARSA: satu pos belanja masuk ke sebuah komponen HANYA bila
    | kombinasi kode program (kegiatan) DAN rekening belanja keduanya terpetakan
    | ke komponen tersebut. Kombinasi (AND), bukan salah-satu (OR), agar tidak
    | salah klasifikasi dan tidak dihitung ganda.
    |
    | HONOR: hanya dihitung bila kode kegiatannya 07.12.* DAN salah satu
    | penanda (jenis belanja / prefix rekening / keyword uraian) cocok.
    | Honor di kegiatan lain (mis. honor ekstrakurikuler 03.03.*) TIDAK
    | masuk persentase honor — sesuai aturan yang berlaku sebelum 30 Okt 2026.
    |
    | kode_program : daftar kode kegiatan/sub program yang diakui komponen.
    | jenis_belanja: daftar jenis belanja resmi ARKAS yang diakui komponen.
    | rekening     : daftar prefix kode rekening belanja yang diakui komponen.
    | keywords     : kata kunci uraian tambahan (diproses AND dengan program).
    |
    | Prioritas klasifikasi per item: honor > buku > sarpras (eksklusif).
    |
    | PENTING — komponen buku dikenali dari PREFIX KODE REKENING (5.2.05),
    | bukan dari nama jenis belanja. Sejak 7 Nov 2026 seluruh 5.2.05 berjenis
    | "Belanja Modal Aset Tetap Lainnya" (sesuai hierarki kode), dan kepatuhan
    | buku tetap terhitung karena penandanya prefix rekening.
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
        // Penanda utama komponen buku = prefix rekening 5.2.05 (lihat header).
        // 'Belanja Modal Buku' dipertahankan hanya untuk rekening lama/custom
        // yang jenisnya belum diseragamkan.
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
    /*
    | PENYALURAN DUA TAHAP — jatah tiap tahap 50% dari pagu 1 tahun.
    |
    | BOSP disalurkan dua tahap: Tahap I (Januari–Juni) dan Tahap II
    | (Juli–Desember). Masing-masing tahap mendapat jatah 50% dari pagu satu
    | tahun, sehingga BELANJA yang direncanakan pada bulan-bulan tahap tersebut
    | tidak boleh melebihi jatah kasnya:
    |   - Tahap I : minimal 50% (JUKNIS) DAN maksimal 50% -> praktis tepat 50%.
    |   - Tahap II: maksimal 50%.
    | Sebelum 08 Nov 2026 hanya batas minimal yang diperiksa, sehingga rencana
    | 51,21% di Tahap I tetap dianggap "SESUAI" padahal melebihi dana yang cair.
    */
    'tahap1' => [
        'batas_persen' => 50,       // batas minimal (JUKNIS BOSP)
        'batas_maks_persen' => 50,  // batas maksimal (jatah penyaluran Tahap I)
    ],
    'tahap2' => [
        'batas_maks_persen' => 50,  // jatah penyaluran Tahap II
    ],
];
