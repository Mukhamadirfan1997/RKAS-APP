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
!macroend

!macro NSIS_HOOK_PREUNINSTALL
  DetailPrint "Menutup KARSA sebelum penghapusan..."
  nsExec::ExecToLog "powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -WindowStyle Hidden -Command $\"Get-CimInstance Win32_Process | Where-Object { $$_.ExecutablePath -and $$_.ExecutablePath.StartsWith('$INSTDIR') } | ForEach-Object { Stop-Process -Id $$_.ProcessId -Force -ErrorAction SilentlyContinue }$\""
  Pop $0
  Sleep 800
!macroend
