<?php

/**
 * Aturan pemetaan kode rekening untuk impor katalog "Referensi Barang Jasa &
 * Barang Modal" dari Dinas Pendidikan ke tabel `kode_barang`.
 *
 * Latar belakang (bukti 2026-11-04):
 * - File "Referensi Barang Jasa" memuat 11.720 barang jasa dengan kolom kode
 *   rekening (kolom B). 8.182 di antaranya memakai kode yang SUDAH ada di
 *   master_kode_rekening (dipakai apa adanya). 3.538 memakai 151 kode yang
 *   tidak ada di master (mis. 5.1.01.* gaji pegawai, 5.2.03.* konstruksi
 *   gedung, 5.2.02.07.* alat kesehatan) sehingga wajib dipetakan.
 * - File "Referensi Barang Modal" TIDAK punya kolom kode rekening. Kode bisa
 *   dibaca dari 12 digit terakhir ID barang untuk 1.719 baris, sedangkan
 *   2.467 baris (kode belanja RBM) tidak menyimpan kode rekening sama sekali
 *   sehingga harus dipetakan berdasarkan kategori/nama barang.
 *
 * Aturan disusun berurutan (aturan pertama yang cocok menang), memakai kode
 * rekening resmi yang benar-benar ada di master_kode_rekening. Tidak ada
 * penghapusan data katalog: impor hanya menambah baris baru atau
 * memperbarui harga acuan.
 */
return [

    'meta' => [
        'sumber' => 'Referensi Barang Jasa & Referensi Barang Modal (Manajemen Aplikasi RKAS 2026)',
        'sumber_berkas' => 'kode refrensi arkas/Referensi Barang Jasa ... (6).xlsx & Referensi Barang Modal ... (4).xlsx',
        'test_harga' => 'Harga 0 diisi dari Harga Maksimal agar Standar Satuan Harga (SSH) tetap terpakai',
    ],

    /**
     * Peta kategori (kolom Kategori file Modal) => kode rekening master.
     * Dipakai bila nama barang tidak cocok aturan kata kunci mana pun.
     */
    'kategori' => [
        'peraga edukasi anak' => '5.2.02.08.03.0015',
        'peraga kesenian' => '5.2.05.02.01.0003',
        'peraga olahraga' => '5.2.02.19.01.0002',
        'peraga biologi' => '5.2.02.08.03.0004',
        'peraga fisika' => '5.2.02.08.03.0004',
        'alat peraga sekolah lainnya' => '5.2.02.08.03.0016',
        'alat laboratorium' => '5.2.02.08.01.0011',
        'lemari pakaian' => '5.2.02.05.02.0001',
        'lemari file - filling cabinet' => '5.2.02.05.01.0004',
        'rak kertas' => '5.2.02.05.01.0004',
        'kursi kerja' => '5.2.02.05.02.0001',
        'meja kerja' => '5.2.02.05.02.0001',
        'meja laptop' => '5.2.02.05.02.0001',
        'kasur' => '5.2.02.05.02.0001',
        'rak dapur' => '5.2.02.05.02.0001',
        'papan pengumuman' => '5.2.02.05.01.0005',
        'papan jalan' => '5.2.02.05.01.0005',
        'speaker' => '5.2.02.06.01.0001',
        'amplifier' => '5.2.02.06.01.0001',
        'mixer' => '5.2.02.06.01.0001',
        'microphone wireless' => '5.2.02.06.01.0001',
        'aksesoris microphone' => '5.2.02.06.01.0001',
        'drum elektrik' => '5.2.05.02.01.0001',
        'gitar elektrik' => '5.2.05.02.01.0001',
        'efek gitar & bass' => '5.2.05.02.01.0001',
        'desktop all-in-one' => '5.2.02.10.01.0002',
        'pc & laptop gaming' => '5.2.02.10.01.0002',
        'printer ink jet' => '5.2.02.05.01.0003',
        'mesin jilid' => '5.2.02.05.01.0003',
        'mesin laminating' => '5.2.02.05.01.0003',
        'led proyektor' => '5.2.02.06.01.0002',
        'kamera mirrorless' => '5.2.02.06.01.0003',
        'kamera cctv' => '5.2.02.06.01.0002',
        'kipas angin meja' => '5.2.02.05.02.0004',
        'kipas angin dinding' => '5.2.02.05.02.0004',
        'kompor' => '5.2.02.05.02.0005',
        'tempat sampah' => '5.2.02.05.02.0003',
        'toren air' => '5.1.02.01.01.0001',
        'beton' => '5.1.02.01.01.0001',
        'besi' => '5.1.02.01.01.0001',
        'kusen pintu' => '5.1.02.01.01.0001',
        'ups' => '5.2.02.10.02.0005',
        'mesin bor' => '5.2.02.05.01.0005',
        'gergaji listrik' => '5.2.02.05.01.0005',
        'kompas' => '5.2.02.08.03.0007',
        'baju badminton' => '5.1.02.01.01.0076',
        'obat-obatan' => '5.1.02.01.01.0037',
        'aksesoris elektronik' => '5.2.02.05.01.0005',
        'pengetahuan, kepribadian, dan keterampilan' => '5.2.05.01.01.0007',
        'pengetahuan dan keterampilan' => '5.2.05.01.01.0007',
        'pengetahuan' => '5.2.05.01.01.0007',
    ],

    /**
     * Aturan kata kunci berurutan. Aturan berlaku bila salah satu kata pada
     * `wajib` ditemukan di nama barang. Kode dipilih dari `kunci`
     * (kata pertama yang cocok menang), atau `default` bila tidak ada.
     */
    'aturan' => [

        [
            'id' => 'buku-administrasi',
            'ket' => 'Buku administrasi (tulis/kas/induk) = alat tulis kantor',
            'wajib' => ['BUKU TULIS', 'BUKU KAS', 'BUKU AGENDA', 'BUKU BESAR', 'BUKU BANK', 'BUKU INDUK', 'BUKU TAMU', 'BUKU KUNJUNGAN', 'BUKU NOTA', 'BUKU CATATAN', 'BUKU STOK', 'BUKU INVENTARIS', 'BUKU REGISTER'],
            'default' => '5.1.02.01.01.0024',
        ],

        [
            'id' => 'honor',
            'ket' => 'Honorarium/upah/gaji tenaga',
            'wajib' => ['HONOR', 'HONORARIUM', 'GAJI', 'UPAH', 'INSENTIF', 'UANG SIDANG', 'UANG SAKU', 'TALI ASIH', 'LEMBUR', 'REMUNERASI'],
            'kunci' => [
                'GURU' => '5.1.02.02.01.0013',
                'PENDIDIK' => '5.1.02.02.01.0013',
                'LABORAN' => '5.1.02.02.01.0015',
                'LABORATORIUM' => '5.1.02.02.01.0015',
                'TUKANG' => '5.1.02.02.01.0016',
                'PRASARANA' => '5.1.02.02.01.0016',
                'KESENIAN' => '5.1.02.02.01.0025',
                'KARAWITAN' => '5.1.02.02.01.0025',
                'ADMINISTRASI' => '5.1.02.02.01.0026',
                'TATA USAHA' => '5.1.02.02.01.0026',
                'ARSIP' => '5.1.02.02.01.0026',
                'OPERATOR' => '5.1.02.02.01.0027',
                'KONSULTAN' => '5.1.02.02.01.0029',
                'AHLI' => '5.1.02.02.01.0029',
                'KEBERSIHAN' => '5.1.02.02.01.0030',
                'PESURUH' => '5.1.02.02.01.0030',
                'CLEANING' => '5.1.02.02.01.0030',
                'KEAMANAN' => '5.1.02.02.01.0031',
                'SATPAM' => '5.1.02.02.01.0031',
                'JAGA' => '5.1.02.02.01.0031',
                'TEKNISI' => '5.1.02.02.01.0035',
                'MEKANIK' => '5.1.02.02.01.0035',
                'LISTRIK' => '5.1.02.02.01.0035',
                'JURI' => '5.1.02.02.01.0037',
                'PENYULUH' => '5.1.02.02.01.0006',
                'PENDAMPING' => '5.1.02.02.01.0006',
                'FASILITATOR' => '5.1.02.02.01.0006',
                'ROHANI' => '5.1.02.02.01.0007',
                'USTADZ' => '5.1.02.02.01.0007',
                'PENDETA' => '5.1.02.02.01.0007',
                'UJIAN' => '5.1.02.02.01.0009',
                'ULANGAN' => '5.1.02.02.01.0009',
                'BUTIR SOAL' => '5.1.02.02.01.0009',
                'PENGAWAS' => '5.1.02.02.01.0009',
                'DIKLAT' => '5.1.02.02.01.0011',
                'PELATIHAN' => '5.1.02.02.01.0011',
                'BIMTEK' => '5.1.02.02.01.0011',
                'NARASUMBER' => '5.1.02.02.01.0003',
                'MODERATOR' => '5.1.02.02.01.0003',
                'PEMBAWA' => '5.1.02.02.01.0003',
                'PANITIA' => '5.1.02.02.01.0003',
                'PENGARAH' => '5.1.02.02.01.0003',
            ],
            'default' => '5.1.02.02.01.0004',
        ],

        [
            'id' => 'konsultan',
            'ket' => 'Jasa konsultansi/kajian/audit',
            'wajib' => ['KONSULTAN', 'KONSULTANSI', 'KAJIAN', 'STUDI KELAYAKAN', 'DED', 'PERENCANA', 'SUPERVISI', 'PENGAWASAN', 'AUDIT', 'NASKAH', 'RANPERDA', 'ROADMAP', 'MASTERPLAN', 'DESAIN'],
            'default' => '5.1.02.02.01.0029',
        ],

        [
            'id' => 'pemeliharaan',
            'ket' => 'Pemeliharaan/perbaikan/servis',
            'wajib' => ['PEMELIHARA', 'PERAWATAN', 'SERVIS', 'SERVICE', 'REPARASI', 'PERBAIKAN', 'KALIBRASI', 'PENGECATAN', 'RENOVASI', 'REHAB'],
            'kunci' => [
                'TAMAN' => '5.1.02.03.03.0036',
                'RTH' => '5.1.02.03.03.0036',
                'LANDSCAPE' => '5.1.02.03.03.0036',
                'PARKIR' => '5.1.02.03.03.0033',
                'JALAN' => '5.1.02.03.03.0030',
                'PAGAR' => '5.1.02.03.03.0030',
                'BANGUNAN' => '5.1.02.03.03.0010',
                'GEDUNG' => '5.1.02.03.03.0010',
                'RUANG' => '5.1.02.03.03.0010',
                'KELAS' => '5.1.02.03.03.0010',
                'ATAP' => '5.1.02.03.03.0010',
                'PLAFON' => '5.1.02.03.03.0010',
                'LANTAI' => '5.1.02.03.03.0010',
                'TOILET' => '5.1.02.03.03.0010',
                'KAMAR MANDI' => '5.1.02.03.03.0010',
                'KANOPI' => '5.1.02.03.03.0010',
                'KOMPUTER' => '5.1.02.03.02.0405',
                'LAPTOP' => '5.1.02.03.02.0405',
                'PRINTER' => '5.1.02.03.02.0115',
                'FOTOKOPI' => '5.1.02.03.02.0115',
                'MEBEL' => '5.1.02.03.02.0118',
                'KURSI' => '5.1.02.03.02.0118',
                'MEJA' => '5.1.02.03.02.0118',
                'LEMARI' => '5.1.02.03.02.0118',
                'PENDINGIN' => '5.1.02.03.02.0121',
                'KIPAS' => '5.1.02.03.02.0121',
                'ALAT PERAGA' => '5.1.02.03.02.0463',
                'OLAHRAGA' => '5.1.02.03.02.0505',
                'JARINGAN' => '5.1.02.03.02.0410',
                'INTERNET' => '5.1.02.03.02.0410',
                'PEMADAM' => '5.1.02.03.02.0124',
            ],
            'default' => '5.1.02.03.02.0123',
        ],

        [
            'id' => 'sewa',
            'ket' => 'Sewa/rental',
            'wajib' => ['SEWA', 'RENTAL', 'RENT'],
            'kunci' => [
                'GEDUNG' => '5.1.02.02.05.0030',
                'AULA' => '5.1.02.02.05.0030',
                'RUANG' => '5.1.02.02.05.0030',
                'BANGUNAN' => '5.1.02.02.05.0030',
                'KENDARAAN' => '5.1.02.02.04.0036',
                'MOBIL' => '5.1.02.02.04.0036',
                'BUS' => '5.1.02.02.04.0036',
                'ANGKUTAN' => '5.1.02.02.04.0036',
                'MOTOR' => '5.1.02.02.04.0038',
                'MEBEL' => '5.1.02.02.04.0118',
                'KURSI' => '5.1.02.02.04.0118',
                'MEJA' => '5.1.02.02.04.0118',
                'KOMPUTER' => '5.1.02.02.04.0406',
                'LAPTOP' => '5.1.02.02.04.0406',
                'PRINTER' => '5.1.02.02.04.0411',
                'SOUND' => '5.1.02.02.04.0132',
                'AUDIO' => '5.1.02.02.04.0132',
                'SPEAKER' => '5.1.02.02.04.0132',
                'VIDEOTRON' => '5.1.02.02.04.0133',
                'PROYEKTOR' => '5.1.02.02.04.0133',
                'LCD' => '5.1.02.02.04.0133',
                'TENDA' => '5.1.02.02.04.0355',
            ],
            'default' => '5.1.02.02.04.0117',
        ],

        [
            'id' => 'perjalanan',
            'ket' => 'Perjalanan dinas',
            'wajib' => ['PERJALANAN', 'PER DIEM', 'SPPD', 'HOTEL', 'PENGINAPAN', 'TIKET', 'AKOMODASI', 'PAKET MEETING', 'FULL BOARD', 'FULLBOARD'],
            'kunci' => [
                'LUAR DAERAH' => '5.1.02.04.01.0001',
                'LUAR KOTA' => '5.1.02.04.01.0001',
                'LUAR PROPINSI' => '5.1.02.04.01.0001',
                'LUAR PROVINSI' => '5.1.02.04.01.0001',
                'DALAM KOTA' => '5.1.02.04.01.0003',
                'DALAM DAERAH' => '5.1.02.04.01.0003',
            ],
            'default' => '5.1.02.04.01.0003',
        ],

        [
            'id' => 'pelatihan',
            'ket' => 'Kursus, pelatihan, bimbingan teknis, beasiswa',
            'wajib' => ['KURSUS', 'PELATIHAN', 'BIMTEK', 'DIKLAT', 'WORKSHOP', 'SEMINAR', 'SOSIALISASI', 'SERTIFIKASI', 'UJI KOMPETENSI', 'BEASISWA', 'BANTUAN TRANSPORT', 'UANG HARIAN PESERTA', 'BIMBINGAN TEKNIS'],
            'default' => '5.1.02.02.12.0001',
        ],

        [
            'id' => 'tagihan',
            'ket' => 'Tagihan/langganan/pengiriman',
            'wajib' => ['TAGIHAN', 'PDAM', 'LANGGANAN', 'SURAT KABAR', 'KORAN', 'MAJALAH', 'PENGIRIMAN', 'KURIR', 'EKSPEDISI', 'PULSA'],
            'kunci' => [
                'LISTRIK' => '5.1.02.02.01.0061',
                'AIR' => '5.1.02.02.01.0060',
                'TELEPON' => '5.1.02.02.01.0059',
                'INTERNET' => '5.1.02.02.01.0063',
                'TV' => '5.1.02.02.01.0063',
                'JURNAL' => '5.1.02.02.01.0062',
                'SURAT KABAR' => '5.1.02.02.01.0062',
                'MAJALAH' => '5.1.02.02.01.0062',
                'KORAN' => '5.1.02.02.01.0062',
                'PENGIRIMAN' => '5.1.02.02.01.0064',
                'KURIR' => '5.1.02.02.01.0064',
            ],
            'default' => '5.1.02.02.01.0063',
        ],

        [
            'id' => 'hadiah',
            'ket' => 'Hadiah/trofi/suvenir',
            'wajib' => ['HADIAH', 'TROFI', 'TROPHY', 'TROPHAY', 'MEDALI', 'PLAKAT', 'PIAGAM', 'SUVENIR', 'CENDERAMATA'],
            'default' => '5.1.02.01.01.0035',
        ],

        [
            'id' => 'makan-minum',
            'ket' => 'Makanan dan minuman',
            'wajib' => ['MAKAN', 'MINUM', 'KONSUMSI', 'NATURA', 'SNACK', 'KATERING', 'NASI', 'PRASMANAN', 'GALON', 'COFFEE BREAK', 'AIR MINERAL', 'MAKANAN'],
            'kunci' => [
                'RAPAT' => '5.1.02.01.01.0052',
                'MEETING' => '5.1.02.01.01.0052',
                'RAKOR' => '5.1.02.01.01.0052',
                'TAMU' => '5.1.02.01.01.0053',
                'JAMUAN' => '5.1.02.01.01.0053',
                'TERNAK' => '5.1.02.01.01.0043',
            ],
            'default' => '5.1.02.01.01.0055',
        ],

        [
            'id' => 'bahan-bakar',
            'ket' => 'Bahan bakar dan pelumas',
            'wajib' => ['PERTAMAX', 'PERTALITE', 'SOLAR', 'DEXLITE', 'BENSIN', 'BBM', 'ELPIJI', 'LPG', 'OLI', 'PELUMAS', 'ISU TABUNG'],
            'default' => '5.1.02.01.01.0004',
        ],

        [
            'id' => 'obat',
            'ket' => 'Obat dan peralatan P3K',
            'wajib' => ['OBAT', 'P3K', 'PERBAN', 'BETADINE', 'ALKOHOL', 'HANDSANITIZER', 'MASKER', 'SARUNG TANGAN', 'KAPAS', 'PLESTER', 'PEMBALUT'],
            'default' => '5.1.02.01.01.0037',
        ],

        [
            'id' => 'bahan-kimia',
            'ket' => 'Bahan kimia',
            'wajib' => ['BAHAN KIMIA', 'REAGENT', 'REAGENSIA', 'FORMALIN'],
            'default' => '5.1.02.01.01.0002',
        ],

        [
            'id' => 'tanaman-ternak',
            'ket' => 'Tanaman, bibit, pupuk, pakan',
            'wajib' => ['PUPUK', 'BIBIT', 'TANAMAN', 'BENIH', 'MEDIA TANAM', 'KOMPOS', 'PESTISIDA', 'INSEKTISIDA', 'PAKAN', 'TERNAK', 'IKAN'],
            'kunci' => [
                'TERNAK' => '5.1.02.01.01.0011',
                'IKAN' => '5.1.02.01.01.0011',
                'PAKAN' => '5.1.02.01.01.0045',
            ],
            'default' => '5.1.02.01.01.0008',
        ],

        [
            'id' => 'alkes',
            'ket' => 'Alat kesehatan (UKS)',
            'wajib' => ['STETOSKOP', 'INFUS', 'DENTAL', 'VENTILATOR', 'INKUBATOR', 'TENSIMETER', 'TENSI METER', 'ALKES', 'ALAT KESEHATAN', 'USG', 'EKG', 'OKSIGEN', 'BED PASIEN', 'RANJANG PASIEN', 'NERBED', 'KURSI RODA', 'KRUK', 'TERMOMETER', 'OKSIMETER', 'NEBULIZER', 'SUCTION'],
            'default' => '5.2.02.05.01.0005',
        ],

        [
            'id' => 'komputer',
            'ket' => 'Komputer dan perangkatnya',
            'wajib' => ['KOMPUTER', 'LAPTOP', 'NOTEBOOK', 'CHROMEBOOK', 'MONITOR', 'SERVER', 'SWITCH', 'ROUTER', 'ACCESS POINT', 'HARDISK', 'SSD', 'RAM', 'PROSESOR', 'MAINBOARD', 'UPS', 'STABILIZER', 'FLASHDISK', 'AIO', 'PC', 'WIFI'],
            'kunci' => [
                'JARINGAN' => '5.2.02.10.02.0004',
                'SWITCH' => '5.2.02.10.02.0004',
                'ROUTER' => '5.2.02.10.02.0004',
                'HUB' => '5.2.02.10.02.0004',
                'ACCESS POINT' => '5.2.02.10.02.0004',
                'WIFI' => '5.2.02.10.02.0004',
                'PERALATAN' => '5.2.02.10.02.0005',
                'AKSESORIS' => '5.2.02.10.02.0005',
                'KOMPONEN' => '5.2.02.10.02.0005',
                'UPS' => '5.2.02.10.02.0005',
                'HARDISK' => '5.2.02.10.02.0005',
                'SSD' => '5.2.02.10.02.0005',
                'RAM' => '5.2.02.10.02.0005',
                'FLASHDISK' => '5.2.02.10.02.0005',
            ],
            'default' => '5.2.02.10.01.0002',
        ],

        [
            'id' => 'reproduksi',
            'ket' => 'Alat reproduksi/penggandaan',
            'wajib' => ['PRINTER', 'SCANNER', 'MESIN FOTOKOPI', 'MESIN JILID', 'LAMINATING', 'MESIN LAMINATING', 'REPROGRAFI', 'MESIN CETAK'],
            'default' => '5.2.02.05.01.0003',
        ],

        [
            'id' => 'audio-video',
            'ket' => 'Peralatan audio, video, kamera',
            'wajib' => ['SPEAKER', 'TOA', 'AMPLIFIER', 'AMPLI', 'AUDIO MIXER', 'MIXER AUDIO', 'SOUND MIXER', 'MIXER SOUND', 'MIC', 'MIKROFON', 'MICROPHONE', 'AUDIO', 'SOUND', 'PROYEKTOR', 'LCD', 'LED', 'TELEVISI', 'TV', 'KAMERA', 'CCTV', 'DRONE'],
            'kunci' => [
                'CCTV' => '5.2.02.06.01.0002',
                'DRONE' => '5.2.02.06.01.0003',
                'KAMERA' => '5.2.02.06.01.0003',
                'PROYEKTOR' => '5.2.02.06.01.0002',
                'LCD' => '5.2.02.06.01.0002',
                'LED' => '5.2.02.06.01.0002',
                'TV' => '5.2.02.06.01.0002',
                'TELEVISI' => '5.2.02.06.01.0002',
            ],
            'default' => '5.2.02.06.01.0001',
        ],

        [
            'id' => 'komunikasi',
            'ket' => 'Alat komunikasi radio/HT',
            'wajib' => ['RADIO', 'HANDY TALKIE', 'HT', 'WALKIE TALKIE', 'MEGAPHONE', 'ANTENA', 'PEMANCAR'],
            'default' => '5.2.02.06.02.0003',
        ],

        [
            'id' => 'alat-musik',
            'ket' => 'Alat musik dan drumband',
            'wajib' => ['GITAR', 'DRUM', 'KEYBOARD', 'PIANO', 'PIANIKA', 'ANGKLUNG', 'KULINTANG', 'GAMELAN', 'KENDANG', 'SULING', 'REBANA', 'MARAWIS', 'BANJARI', 'HADROH', 'ORGAN', 'BIOLA', 'GONG', 'TAMBORIN', 'DRUMBAND', 'MARCHING BAND', 'SASANDO', 'ALAT MUSIK'],
            'default' => '5.2.05.02.01.0001',
        ],

        [
            'id' => 'atk',
            'ket' => 'Alat tulis kantor dan bahan komputer',
            'wajib' => ['KERTAS', 'HVS', 'AMPLOP', 'MAP', 'ORDNER', 'FOLDER', 'SPIDOL', 'PENA', 'PENSIL', 'PULPEN', 'PENGHAPUS', 'PENGGARIS', 'RAUTAN', 'LEM', 'LAKBAN', 'ISOLASI', 'STAPLER', 'KLIP', 'BINDER', 'GUNTING', 'CUTTER', 'TINTA', 'TONER', 'CATRIDGE', 'CARTRIDGE', 'REFILL', 'MATERAI', 'PERANGKO', 'STEMPEL', 'AGENDA', 'KALENDER', 'NOTEBOOK'],
            'kunci' => [
                'KERTAS' => '5.1.02.01.01.0025',
                'HVS' => '5.1.02.01.01.0025',
                'TINTA' => '5.1.02.01.01.0029',
                'TONER' => '5.1.02.01.01.0029',
                'CATRIDGE' => '5.1.02.01.01.0029',
                'CARTRIDGE' => '5.1.02.01.01.0029',
                'REFILL' => '5.1.02.01.01.0029',
                'MATERAI' => '5.1.02.01.01.0027',
                'PERANGKO' => '5.1.02.01.01.0027',
                'AMPLOP' => '5.1.02.01.01.0027',
            ],
            'default' => '5.1.02.01.01.0024',
        ],

        [
            'id' => 'mebel',
            'ket' => 'Mebel dan alat penyimpan',
            'wajib' => ['MEJA', 'KURSI', 'LEMARI', 'RAK', 'ALMARI', 'BUFET', 'SOFA', 'BANGKU', 'KASUR', 'TEMPAT TIDUR', 'SKAT', 'MEBEL', 'BRANGKAS', 'BRANKAS', 'ETALASE', 'LOKER', 'FILLING CABINET'],
            'kunci' => [
                'ARSIP' => '5.2.02.05.01.0004',
                'FILE' => '5.2.02.05.01.0004',
                'FILLING' => '5.2.02.05.01.0004',
                'LOKER' => '5.2.02.05.01.0004',
                'BRANGKAS' => '5.2.02.05.01.0004',
                'BRANKAS' => '5.2.02.05.01.0004',
                'RAK BESI' => '5.2.02.05.01.0004',
            ],
            'default' => '5.2.02.05.02.0001',
        ],

        [
            'id' => 'alat-rumah-tangga',
            'ket' => 'Alat rumah tangga, pendingin, pembersih, pemadam',
            'wajib' => ['KIPAS', 'PENDINGIN', 'AC', 'KULKAS', 'FREEZER', 'DISPENSER', 'KOMPOR', 'OVEN', 'RICE COOKER', 'MAGIC COM', 'MESIN CUCI', 'SETRIKA', 'SAPU', 'SEROKAN', 'VACUUM', 'TEMPAT SAMPAH', 'GENSET', 'GENERATOR', 'POMPA', 'KOMPRESOR', 'COMPRESSOR', 'APAR', 'PEMADAM', 'JAM DINDING', 'ALAT DAPUR'],
            'kunci' => [
                'KIPAS' => '5.2.02.05.02.0004',
                'PENDINGIN' => '5.2.02.05.02.0004',
                'AC' => '5.2.02.05.02.0004',
                'KULKAS' => '5.2.02.05.02.0004',
                'FREEZER' => '5.2.02.05.02.0004',
                'KOMPOR' => '5.2.02.05.02.0005',
                'OVEN' => '5.2.02.05.02.0005',
                'DISPENSER' => '5.2.02.05.02.0005',
                'RICE COOKER' => '5.2.02.05.02.0005',
                'MAGIC COM' => '5.2.02.05.02.0005',
                'ALAT DAPUR' => '5.2.02.05.02.0005',
                'SAPU' => '5.2.02.05.02.0003',
                'SEROKAN' => '5.2.02.05.02.0003',
                'VACUUM' => '5.2.02.05.02.0003',
                'TEMPAT SAMPAH' => '5.2.02.05.02.0003',
                'MESIN CUCI' => '5.2.02.05.02.0003',
                'SETRIKA' => '5.2.02.05.02.0003',
                'APAR' => '5.2.02.05.02.0007',
                'PEMADAM' => '5.2.02.05.02.0007',
                'GENSET' => '5.2.02.01.03.0004',
                'GENERATOR' => '5.2.02.01.03.0004',
                'POMPA' => '5.2.02.01.03.0005',
                'KOMPRESOR' => '5.2.02.01.03.0003',
                'COMPRESSOR' => '5.2.02.01.03.0003',
            ],
            'default' => '5.2.02.05.02.0006',
        ],

        [
            'id' => 'perkakas',
            'ket' => 'Perkakas/alat tukang',
            'wajib' => ['BOR', 'GERGAJI', 'TANG', 'OBENG', 'MESIN LAS', 'ALAT TUKANG', 'ALAT BENGKEL', 'KUNCI PAS', 'SEKOP', 'CANGKUL'],
            'default' => '5.2.02.05.01.0005',
        ],

        [
            'id' => 'alat-kantor',
            'ket' => 'Papan tulis/papan pengumuman',
            'wajib' => ['PAPAN TULIS', 'WHITEBOARD', 'BLACKBOARD', 'PAPAN PENGUMUMAN', 'PAPAN NAMA', 'PAPAN DATA', 'PAPAN ABSEN', 'MADING', 'PAPAN SKOR', 'PAPAN FLANEL'],
            'default' => '5.2.02.05.01.0005',
        ],

        [
            'id' => 'olahraga',
            'ket' => 'Peralatan olahraga',
            'wajib' => ['BOLA', 'RAKET', 'NET', 'GAWANG', 'MATRAS', 'PETI LOMPAT', 'SAMSAK', 'SHUTTLECOCK', 'KOK', 'PELUIT', 'BADMINTON', 'FUTSAL', 'VOLI', 'BASKET', 'TOLAK PELURU', 'LEMBING', 'PANAHAN', 'BUSUR', 'JERSEY', 'SEPATU OLAHRAGA'],
            'kunci' => [
                'ATLETIK' => '5.2.02.19.01.0001',
                'TOLAK PELURU' => '5.2.02.19.01.0001',
                'LEMBING' => '5.2.02.19.01.0001',
                'SENAM' => '5.2.02.19.01.0003',
                'MATRAS' => '5.2.02.19.01.0003',
                'RENANG' => '5.2.02.19.01.0004',
            ],
            'default' => '5.2.02.19.01.0002',
        ],

        [
            'id' => 'laboratorium',
            'ket' => 'Alat laboratorium',
            'wajib' => ['LABORATORIUM', 'MIKROSKOP', 'GELAS UKUR', 'BURET', 'PIPET', 'NERACA', 'TABUNG REAKSI', 'CENTRIFUGE', 'PH METER', 'TDS', 'TERMOMETER', 'AMPERE', 'VOLTMETER', 'MULTIMETER', 'TELESKOP', 'PERISKOP', 'PREPARAT', 'ALAT LAB'],
            'kunci' => [
                'KIMIA' => '5.2.02.08.01.0013',
                'FISIKA' => '5.2.02.08.01.0020',
                'MIKROBIOLOGI' => '5.2.02.08.01.0012',
                'BIOLOGI' => '5.2.02.08.01.0048',
            ],
            'default' => '5.2.02.08.01.0011',
        ],

        [
            'id' => 'pakaian',
            'ket' => 'Pakaian dan perlengkapannya',
            'wajib' => ['PAKAIAN', 'SERAGAM', 'BATIK', 'PDH', 'PDU', 'PDL', 'PSH', 'JAKET', 'KAOS', 'KEMEJA', 'CELANA', 'ROK', 'SEPATU', 'TOPI', 'DASI', 'JILBAB', 'MUKENA', 'SARUNG'],
            'kunci' => [
                'OLAHRAGA' => '5.1.02.01.01.0076',
                'BATIK' => '5.1.02.01.01.0075',
                'PDU' => '5.1.02.01.01.0066',
                'UPACARA' => '5.1.02.01.01.0066',
                'PDL' => '5.1.02.01.01.0064',
                'LABORATORIUM' => '5.1.02.01.01.0071',
                'BENGKEL' => '5.1.02.01.01.0072',
                'ADAT' => '5.1.02.01.01.0074',
            ],
            'default' => '5.1.02.01.01.0032',
        ],

        [
            'id' => 'bahan-bangunan',
            'ket' => 'Bahan bangunan dan instalasi',
            'wajib' => ['SEMEN', 'PASIR', 'BATA', 'BATAKO', 'BESI', 'KAYU', 'TRIPLEK', 'PLYWOOD', 'PAKU', 'CAT', 'KERAMIK', 'GRANIT', 'GENTENG', 'PIPA', 'PVC', 'KUAS', 'PLAMIR', 'HEBEL', 'KUSEN', 'PINTU', 'JENDELA', 'TERALIS', 'TOREN', 'TANDON', 'KERAN', 'CLOSET', 'BAK MANDI', 'MORTAR', 'GALVALUM', 'ALUMINIUM', 'ACP'],
            'default' => '5.1.02.01.01.0001',
        ],

        [
            'id' => 'cetak',
            'ket' => 'Cetak dan penggandaan',
            'wajib' => ['FOTOKOPI', 'FOTO COPY', 'PENGGANDAAN', 'CETAK', 'JILID', 'LAMINASI', 'BROSUR', 'BANNER', 'SPANDUK', 'BALIHO', 'UNDANGAN', 'LEAFLET', 'BOOKLET', 'KARTU NAMA', 'STIKER', 'SERTIFIKAT', 'BUKU SAKU'],
            'default' => '5.1.02.01.01.0026',
        ],

        [
            'id' => 'alat-peraga',
            'ket' => 'Alat peraga pendidikan (APE)',
            'wajib' => ['APE', 'ALAT PERAGA', 'PERAGA', 'PUZZLE', 'MERONCE', 'BALOK', 'PASAK', 'MAKET', 'MINIATUR', 'REPLIKA', 'TORSO', 'MAGNETIK', 'ABJAD', 'ALPHABET', 'KARTU HURUF', 'KARTU ANGKA', 'MENARA', 'PIRAMIDA', 'JUNGKITAN', 'PELUNCUR', 'DERMULEN', 'AYUNAN', 'TANGGA MAJEMUK', 'KIT', 'MODEL'],
            'kunci' => [
                'AGAMA' => '5.2.02.08.03.0008',
                'ISLAM' => '5.2.02.08.03.0008',
                'HIJAIYAH' => '5.2.02.08.03.0008',
                'QUR' => '5.2.02.08.03.0008',
                'MATEMATIKA' => '5.2.02.08.03.0002',
                'ANGKA' => '5.2.02.08.03.0002',
                'GEOMETRI' => '5.2.02.08.03.0002',
                'IPA' => '5.2.02.08.03.0004',
                'SAINS' => '5.2.02.08.03.0004',
                'FISIKA' => '5.2.02.08.03.0004',
                'BIOLOGI' => '5.2.02.08.03.0004',
                'MIKROSKOP' => '5.2.02.08.03.0004',
                'TORSO' => '5.2.02.08.03.0004',
                'IPS' => '5.2.02.08.03.0007',
                'PROFESI' => '5.2.02.08.03.0007',
                'RAMBU' => '5.2.02.08.03.0007',
                'KESENIAN' => '5.2.02.08.03.0010',
                'MUSIK' => '5.2.02.08.03.0010',
                'OLAHRAGA' => '5.2.02.08.03.0011',
                'PANCASILA' => '5.2.02.08.03.0012',
                'PKN' => '5.2.02.08.03.0012',
                'PAUD' => '5.2.02.08.03.0015',
                'TK' => '5.2.02.08.03.0015',
                'EDUKATIF' => '5.2.02.08.03.0015',
                'BAHASA' => '5.2.02.08.03.0001',
                'HURUF' => '5.2.02.08.03.0001',
            ],
            'default' => '5.2.02.08.03.0016',
        ],

        [
            'id' => 'buku',
            'ket' => 'Buku perpustakaan/buku pelajaran',
            'wajib' => ['BUKU', 'PANDUAN', 'MODUL AJAR', 'MODUL BELAJAR', 'MODUL PEMBELAJARAN', 'MODUL PROJEK', 'MODUL PROYEK', 'MODUL GURU', 'MODUL SISWA', 'LKS', 'ENSIKLOPEDIA', 'ENSIKLOPEDI', 'KAMUS', 'ATLAS', 'CERITA', 'DONGENG', 'FABEL', 'NOVEL', 'PUISI', 'ANTOLOGI', 'KOMIK', 'LITERASI', 'SIBI', 'PEMBACA', 'BACAAN', 'QUR', 'HADIS', 'HADITS', 'MAJALAH', 'JURNAL', 'BULETIN'],
            'kunci' => [
                'AGAMA' => '5.2.05.01.01.0003',
                'ISLAM' => '5.2.05.01.01.0003',
                'QUR' => '5.2.05.01.01.0003',
                'HADIS' => '5.2.05.01.01.0003',
                'HADITS' => '5.2.05.01.01.0003',
                'AKIDAH' => '5.2.05.01.01.0003',
                'AKHLAK' => '5.2.05.01.01.0003',
                'BUDI PEKERTI' => '5.2.05.01.01.0003',
                'HIJAIYAH' => '5.2.05.01.01.0003',
                'BTQ' => '5.2.05.01.01.0003',
                'TAUHID' => '5.2.05.01.01.0003',
                'FIKIH' => '5.2.05.01.01.0003',
                'KRISTEN' => '5.2.05.01.01.0003',
                'KATOLIK' => '5.2.05.01.01.0003',
                'HINDU' => '5.2.05.01.01.0003',
                'BUDDHA' => '5.2.05.01.01.0003',
                'MATEMATIKA' => '5.2.05.01.01.0006',
                'MATEMATIK' => '5.2.05.01.01.0006',
                'IPA' => '5.2.05.01.01.0006',
                'IPAS' => '5.2.05.01.01.0006',
                'SAINS' => '5.2.05.01.01.0006',
                'FISIKA' => '5.2.05.01.01.0006',
                'KIMIA' => '5.2.05.01.01.0006',
                'BIOLOGI' => '5.2.05.01.01.0006',
                'NUMERASI' => '5.2.05.01.01.0006',
                'BAHASA' => '5.2.05.01.01.0005',
                'INGGRIS' => '5.2.05.01.01.0005',
                'ENGLISH' => '5.2.05.01.01.0005',
                'INDONESIA' => '5.2.05.01.01.0005',
                'JAWA' => '5.2.05.01.01.0005',
                'SUNDA' => '5.2.05.01.01.0005',
                'KAMUS' => '5.2.05.01.01.0005',
                'MEMBACA' => '5.2.05.01.01.0005',
                'PEMBACA' => '5.2.05.01.01.0005',
                'BACAAN' => '5.2.05.01.01.0005',
                'LITERASI' => '5.2.05.01.01.0005',
                'SIBI' => '5.2.05.01.01.0005',
                'ESPS' => '5.2.05.01.01.0005',
                'CERITA' => '5.2.05.01.01.0005',
                'DONGENG' => '5.2.05.01.01.0005',
                'FABEL' => '5.2.05.01.01.0005',
                'NOVEL' => '5.2.05.01.01.0005',
                'PUISI' => '5.2.05.01.01.0005',
                'KOMIK' => '5.2.05.01.01.0005',
                'ANTOLOGI' => '5.2.05.01.01.0005',
                'SEJARAH' => '5.2.05.01.01.0009',
                'GEOGRAFI' => '5.2.05.01.01.0009',
                'BIOGRAFI' => '5.2.05.01.01.0009',
                'SOSIOLOGI' => '5.2.05.01.01.0004',
                'EKONOMI' => '5.2.05.01.01.0004',
                'PANCASILA' => '5.2.05.01.01.0004',
                'PPKN' => '5.2.05.01.01.0004',
                'PKN' => '5.2.05.01.01.0004',
                'IPS' => '5.2.05.01.01.0004',
                'SENI' => '5.2.05.01.01.0008',
                'BUDAYA' => '5.2.05.01.01.0008',
                'MUSIK' => '5.2.05.01.01.0008',
                'TARI' => '5.2.05.01.01.0008',
                'RUPA' => '5.2.05.01.01.0008',
                'OLAHRAGA' => '5.2.05.01.01.0008',
                'JASMANI' => '5.2.05.01.01.0008',
                'PJOK' => '5.2.05.01.01.0008',
                'PRAKARYA' => '5.2.05.01.01.0008',
                'KETERAMPILAN' => '5.2.05.01.01.0008',
                'KODING' => '5.2.05.01.01.0007',
                'INFORMATIKA' => '5.2.05.01.01.0007',
                'KOMPUTER' => '5.2.05.01.01.0007',
                'FILSAFAT' => '5.2.05.01.01.0002',
                'PSIKOLOGI' => '5.2.05.01.01.0002',
                'PENDIDIKAN' => '5.2.05.01.01.0007',
                'GURU' => '5.2.05.01.01.0007',
                'MAJALAH' => '5.2.05.01.01.0010',
                'JURNAL' => '5.2.05.01.01.0010',
                'BULETIN' => '5.2.05.01.01.0010',
            ],
            'default' => '5.2.05.01.01.0001',
        ],

        [
            'id' => 'kerajinan',
            'ket' => 'Barang kerajinan/hiasan',
            'wajib' => ['HIASAN', 'KERAJINAN', 'VAS', 'LUKISAN', 'KALIGRAFI', 'PATUNG', 'UKIRAN'],
            'kunci' => [
                'LUKISAN' => '5.2.05.02.01.0002',
                'KALIGRAFI' => '5.2.05.02.01.0002',
            ],
            'default' => '5.2.05.02.02.0003',
        ],

        [
            'id' => 'kebersihan-bahan',
            'ket' => 'Bahan kebersihan',
            'wajib' => ['SABUN', 'DETERJEN', 'KARBOL', 'PEMBERSIH', 'PEMUTIH', 'PEWANGI', 'PENGHARUM', 'SIKAT', 'KAIN LAP', 'PLASTIK SAMPAH', 'KANTONG SAMPAH'],
            'default' => '5.1.02.01.01.0036',
        ],

        [
            'id' => 'ban-kendaraan',
            'ket' => 'Ban dan kelengkapan kendaraan',
            'wajib' => ['BAN TRUCK', 'BAN TRUK', 'BAN LOADER', 'BAN KENDARAAN', 'BAN DALAM', 'BAN LUAR', 'BAN MOTOR', 'BAN MOBIL', 'BAN SEPEDA', 'TUBELESS', 'DOP RODA', 'VELG'],
            'default' => '5.1.02.01.01.0013',
        ],

        [
            'id' => 'komponen',
            'ket' => 'Komponen/suku cadang',
            'wajib' => ['KOMPONEN', 'SUKU CADANG', 'SPARE PART', 'SPAREPART'],
            'kunci' => [
                'ANGKUTAN' => '5.1.02.01.01.0013',
                'KENDARAAN' => '5.1.02.01.01.0013',
                'MOBIL' => '5.1.02.01.01.0013',
                'TRUK' => '5.1.02.01.01.0013',
                'LAB' => '5.1.02.01.01.0016',
                'BENGKEL' => '5.1.02.01.01.0020',
                'PERTANIAN' => '5.1.02.01.01.0019',
            ],
            'default' => '5.1.02.01.02.0003',
        ],
    ],

    /**
     * Aturan yang HANYA boleh dinilai dari nama pokok (sebelum tanda "-"),
     * tidak dari teks deskripsi. Tanpa ini, deskripsi panjang seperti
     * "...membantu pemeliharaan otot..." atau "...termasuk perbaikan..."
     * membuat barang salah masuk ke aturan pemeliharaan.
     *
     * @var array<int, string>
     */
    'aturan_kepala_saja' => [
        'buku-administrasi',
        'honor',
        'konsultan',
        'pemeliharaan',
        'sewa',
        'perjalanan',
        'pelatihan',
        'tagihan',
        'hadiah',
    ],

    /**
     * Cadangan berdasarkan kelompok kode rekening asal (prefix 4 segmen).
     * Dipakai bila tidak ada aturan kata kunci yang cocok, tetapi barang
     * memiliki kode rekening asal dari dinas yang tidak ada di master.
     */
    'keluarga' => [
        '5.1.01' => '5.1.02.02.01.0004',
        '5.1.02.01' => '5.1.02.01.01.0012',
        '5.1.02.02' => '5.1.02.02.01.0004',
        '5.1.02.03' => '5.1.02.03.02.0123',
        '5.1.02.04' => '5.1.02.04.01.0003',
        '5.1.02.05' => '5.1.02.01.01.0035',
        '5.1.02.90' => '5.1.02.01.01.0004',
        '5.2.02.01' => '5.2.02.01.03.0004',
        '5.2.02.02' => '5.2.02.05.01.0005',
        '5.2.02.03' => '5.2.02.08.01.0011',
        '5.2.02.04' => '5.2.02.08.01.0011',
        '5.2.02.05' => '5.2.02.05.01.0005',
        '5.2.02.06' => '5.2.02.06.01.0001',
        '5.2.02.07' => '5.2.02.05.01.0005',
        '5.2.02.08' => '5.2.02.08.01.0011',
        '5.2.02.09' => '5.2.02.19.01.0002',
        '5.2.02.10' => '5.2.02.10.01.0002',
        '5.2.02.14' => '5.2.02.01.03.0004',
        '5.2.02.15' => '5.2.02.05.01.0005',
        '5.2.02.16' => '5.2.02.16.01.0001',
        '5.2.02.18' => '5.2.02.18.01.0002',
        '5.2.02.19' => '5.2.02.19.01.0002',
        '5.2.03' => '5.1.02.03.03.0010',
        '5.2.04' => '5.1.02.03.03.0030',
        '5.2.05' => '5.2.05.01.01.0001',
    ],

    /** Satuan yang menandakan buku (dipakai bila aturan kata kunci tidak cocok). */
    'satuan_buku' => ['EKSEMPLAR', 'EKS', 'EXMPLR', 'EXEMPLAR', 'EXEMPLAR'],

    /** Aturan yang dipakai untuk barang bersatuan buku. */
    'aturan_satuan_buku' => 'buku',

    /** Cadangan terakhir per kode belanja. */
    'cadangan_belanja' => [
        'RBM' => '5.2.02.05.01.0005',
        'BMA' => '5.2.02.05.01.0005',
        'BHP' => '5.1.02.01.01.0036',
        'RBHP' => '5.1.02.01.01.0036',
        'BBJ' => '5.1.02.01.01.0012',
        'RBBJ' => '5.1.02.01.01.0012',
    ],

    /** Cadangan global bila semua langkah gagal. */
    'cadangan' => '5.1.02.01.01.0012',
];
