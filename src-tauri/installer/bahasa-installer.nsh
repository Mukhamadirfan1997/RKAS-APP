; Terjemahan Indonesia untuk installer KARSA.
;
; Dipakai melalui:
;   bundle.windows.nsis.languages            = ["Indonesian"]
;   bundle.windows.nsis.customLanguageFiles  = { "Indonesian": "installer/bahasa-installer.nsh" }
;
; Berkas ini MENGGANTIKAN terjemahan bawaan Tauri untuk pesan khusus installer,
; jadi seluruh LangString yang dirujuk template Tauri harus ada di sini. Bila
; Tauri menambah pesan baru pada versi berikutnya, tambahkan juga di sini agar
; teksnya tidak kosong di layar installer.

LangString addOrReinstall ${LANG_INDONESIAN} "Tambah / instal ulang komponen"
LangString alreadyInstalled ${LANG_INDONESIAN} "Sudah terpasang"
LangString alreadyInstalledLong ${LANG_INDONESIAN} "${PRODUCTNAME} ${VERSION} sudah terpasang di komputer ini. Pilih tindakan yang diinginkan lalu tekan Berikutnya."
LangString appRunning ${LANG_INDONESIAN} "KARSA masih berjalan. Tutup aplikasi terlebih dahulu, lalu coba lagi."
LangString appRunningOkKill ${LANG_INDONESIAN} "KARSA masih berjalan.$\r$\nTekan OK untuk menutup paksa aplikasi."
LangString chooseMaintenanceOption ${LANG_INDONESIAN} "Pilih tindakan perbaikan yang ingin dilakukan."
LangString choowHowToInstall ${LANG_INDONESIAN} "Pilih cara pemasangan ${PRODUCTNAME}."
LangString createDesktop ${LANG_INDONESIAN} "Buat pintasan di desktop"
LangString dontUninstall ${LANG_INDONESIAN} "Jangan hapus pemasangan"
LangString dontUninstallDowngrade ${LANG_INDONESIAN} "Jangan hapus pemasangan (memasang versi lebih lama tanpa menghapus versi sekarang tidak diizinkan)"
LangString failedToKillApp ${LANG_INDONESIAN} "Gagal menutup KARSA. Tutup aplikasi secara manual, lalu coba lagi."
LangString installingWebview2 ${LANG_INDONESIAN} "Memasang WebView2..."
LangString newerVersionInstalled ${LANG_INDONESIAN} "Versi ${PRODUCTNAME} yang lebih baru sudah terpasang. Memasang versi lama tidak disarankan. Bila tetap ingin melanjutkan, hapus pemasangan versi sekarang terlebih dahulu. Pilih tindakan yang diinginkan lalu tekan Berikutnya."
LangString older ${LANG_INDONESIAN} "lebih lama"
LangString olderOrUnknownVersionInstalled ${LANG_INDONESIAN} "Versi $R4 dari ${PRODUCTNAME} sudah terpasang. Sebaiknya hapus pemasangan versi sekarang sebelum memasang. Pilih tindakan yang diinginkan lalu tekan Berikutnya."
LangString silentDowngrades ${LANG_INDONESIAN} "Pemasangan versi lebih lama tidak diizinkan pada installer ini. Gunakan installer dengan tampilan biasa.$\r$\n"
LangString unableToUninstall ${LANG_INDONESIAN} "Gagal menghapus pemasangan."
LangString uninstallApp ${LANG_INDONESIAN} "Hapus pemasangan ${PRODUCTNAME}"
LangString uninstallBeforeInstalling ${LANG_INDONESIAN} "Hapus pemasangan sebelum memasang"
LangString unknown ${LANG_INDONESIAN} "tidak diketahui"
LangString webview2AbortError ${LANG_INDONESIAN} "Gagal memasang WebView2. Aplikasi tidak dapat berjalan tanpa komponen ini. Jalankan ulang installer."
LangString webview2DownloadError ${LANG_INDONESIAN} "Gagal mengunduh WebView2 - $0"
LangString webview2DownloadSuccess ${LANG_INDONESIAN} "Pengunduh WebView2 berhasil diunduh"
LangString webview2Downloading ${LANG_INDONESIAN} "Mengunduh pengunduh WebView2..."
LangString webview2InstallError ${LANG_INDONESIAN} "Gagal memasang WebView2 (kode keluar $1)"
LangString webview2InstallSuccess ${LANG_INDONESIAN} "WebView2 berhasil dipasang"
LangString deleteAppData ${LANG_INDONESIAN} "Hapus data aplikasi"

; --- Teks halaman Modern UI yang disesuaikan untuk KARSA ---

LangString MUI_TEXT_WELCOME_INFO_TITLE ${LANG_INDONESIAN} "Selamat datang di ${PRODUCTNAME} ${VERSION}"
LangString MUI_TEXT_WELCOME_INFO_TEXT ${LANG_INDONESIAN} "KARSA (Kertas Anggaran Sekolah) adalah aplikasi desktop untuk menyusun Kertas Kerja RKAS Tahun Anggaran 2026 bagi sekolah penerima dana BOSP Reguler.$\r$\n$\r$\nAplikasi bekerja sepenuhnya di komputer sekolah: data anggaran tidak dikirim ke internet. Setelah pemasangan, isi profil sekolah dan pagu anggaran pada menu Pengaturan.$\r$\n$\r$\nTekan Berikutnya untuk melanjutkan."

LangString MUI_TEXT_LICENSE_TITLE ${LANG_INDONESIAN} "Tentang KARSA"
LangString MUI_TEXT_LICENSE_SUBTITLE ${LANG_INDONESIAN} "Ringkasan aplikasi dan ketentuan penggunaan. Gulir ke bawah untuk membaca seluruhnya."
LangString MUI_INNERTEXT_LICENSE_TOP ${LANG_INDONESIAN} "Baca ringkasan berikut, lalu tekan tombol persetujuan untuk melanjutkan."

LangString MUI_TEXT_FINISH_TITLE ${LANG_INDONESIAN} "Pemasangan selesai"
LangString MUI_TEXT_FINISH_SUBTITLE ${LANG_INDONESIAN} "KARSA sudah terpasang dan siap digunakan."
LangString MUI_TEXT_FINISH_INFO_TITLE ${LANG_INDONESIAN} "KARSA berhasil dipasang"
LangString MUI_TEXT_FINISH_INFO_TEXT ${LANG_INDONESIAN} "Buka KARSA dari menu Start atau pintasan di desktop. Saat pertama dibuka, isi profil sekolah dan pagu anggaran pada menu Pengaturan, lalu mulai menyusun anggaran pada Lembar Kerja.$\r$\n$\r$\nTekan Selesai untuk menutup installer."
