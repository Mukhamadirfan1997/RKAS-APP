; ============================================================================
; Hook installer KARSA (NSIS)
;
; Masalah nyata: saat memperbarui, installer gagal menimpa berkas dengan
; "Error opening file for writing" karena KARSA.exe dan/atau php.exe (server
; internal aplikasi) masih hidup — termasuk php.exe yatim dari sesi sebelumnya
; yang tidak sempat dimatikan ketika aplikasi crash / ditutup paksa.
;
; Solusi: SEBELUM berkas apa pun ditimpa, hentikan SEMUA proses yang executable
; -nya berada di dalam folder instalasi tujuan ($INSTDIR). Pendekatan berbasis
; path (bukan nama proses) dipakai agar tidak mematikan aplikasi lain di
; komputer sekolah yang kebetulan bernama php.exe (mis. XAMPP).
;
; Pendamping di sisi Rust: php.exe didaftarkan ke Windows Job Object dengan
; JOB_OBJECT_LIMIT_KILL_ON_JOB_CLOSE sehingga ke depan tidak ada lagi php.exe
; yatim walau KARSA berhenti mendadak.
; ============================================================================

!macro NSIS_HOOK_PREINSTALL
  DetailPrint "Menutup KARSA dan server internal yang masih berjalan..."
  nsExec::ExecToLog "powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -Command $\"Get-CimInstance Win32_Process | Where-Object { $$_.ExecutablePath -and $$_.ExecutablePath.StartsWith('$INSTDIR') } | ForEach-Object { Stop-Process -Id $$_.ProcessId -Force -ErrorAction SilentlyContinue }$\""
  Pop $0
  DetailPrint "Penutupan proses selesai (status: $0)"
  ; Beri waktu Windows melepas handle berkas (DLL/php.exe) sebelum ditimpa.
  Sleep 1500

  ; Bersihkan aset tampilan versi lama. Installer hanya MENIMPA berkas, sehingga
  ; aset ber-hash dari versi sebelumnya (app-XXXXXXXX.js / app-XXXXXXXX.css)
  ; terus menumpuk — terukur ±250 KB per pembaruan, dan di komputer sekolah
  ; sempat menyisakan 886 KB berkas yatim. Dihapus DI SINI (setelah proses
  ; dimatikan) karena Windows mengunci berkas yang sedang dipakai; folder ini
  ; akan langsung diisi ulang oleh berkas build versi baru.
  IfFileExists "$INSTDIR\public\build\*.*" karsa_ada_build_lama
  Goto karsa_tanpa_build_lama
  karsa_ada_build_lama:
    DetailPrint "Membersihkan aset tampilan versi lama..."
    RMDir /r "$INSTDIR\public\build"
  karsa_tanpa_build_lama:
!macroend

!macro NSIS_HOOK_POSTINSTALL
  ; Jaring pengaman: karena folder aset dibersihkan sebelum penyalinan, pastikan
  ; berkas build benar-benar terpasang. Tanpa ini, kegagalan salin akan berakhir
  ; sebagai layar kosong tanpa penjelasan.
  IfFileExists "$INSTDIR\public\build\manifest.json" karsa_aset_lengkap
    IfSilent karsa_aset_lengkap
    MessageBox MB_ICONEXCLAMATION|MB_OK "Aset tampilan KARSA tidak ditemukan setelah pemasangan.$\r$\nJalankan ulang installer KARSA sebelum membuka aplikasi."
  karsa_aset_lengkap:
!macroend

!macro NSIS_HOOK_PREUNINSTALL
  DetailPrint "Menutup KARSA sebelum penghapusan..."
  nsExec::ExecToLog "powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -Command $\"Get-CimInstance Win32_Process | Where-Object { $$_.ExecutablePath -and $$_.ExecutablePath.StartsWith('$INSTDIR') } | ForEach-Object { Stop-Process -Id $$_.ProcessId -Force -ErrorAction SilentlyContinue }$\""
  Pop $0
  ; Bersihkan aturan firewall akses LAN milik KARSA agar tidak tertinggal.
  ; Best-effort: uninstaller berjalan tanpa hak administrator, jadi bila gagal
  ; (butuh elevasi) aturan hanya menunjuk path php.exe yang sudah tidak ada —
  ; tidak berbahaya. Pengguna bisa menghapusnya manual dari Windows Firewall.
  DetailPrint "Menghapus aturan Windows Firewall KARSA (bila ada)..."
  nsExec::ExecToLog 'netsh advfirewall firewall delete rule name="KARSA LAN Server"'
  Pop $0
  Sleep 800
!macroend
