@extends('layouts.focus')

@section('content')
<div class="w-full max-w-none space-y-6">
    <a href="{{ route('dashboard.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Kembali ke Dashboard
    </a>
    <!-- Header -->
    <div class="card p-6 lg:p-8">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 p-1.5 shadow-md shrink-0 flex items-center justify-center">
                <img src="{{ asset('icons/logo.png') }}" alt="KARSA" class="w-full h-full object-contain">
            </div>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl lg:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight font-display">KARSA — Kertas Anggaran Sekolah</h1>
                <p class="mt-1 inline-flex items-center gap-2">
                    <span class="px-2.5 py-1 rounded-full bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 text-xs font-bold text-blue-700 dark:text-blue-300">Versi {{ $version }}</span>
                    <span class="text-xs text-slate-400 dark:text-slate-500">Desktop Offline • TA 2026</span>
                </p>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">
                    @php
                        $nama = trim((string) ($sekolah->nama_sekolah ?? ''));
                        $kec = trim((string) ($sekolah->kecamatan ?? ''));
                        $kab = trim((string) ($sekolah->kabupaten_kota ?? ''));
                        $hasSekolah = $nama !== '' && $nama !== 'SD NEGERI TOYANING 1' ? true : ($nama !== '' && ($kec !== '' || $kab !== ''));
                        // Fallback: if nama_sekolah is default seed but belum diisi alamat, anggap sudah ada
                        $displayNama = $nama !== '' ? $nama : 'Sekolah Anda';
                        $lokasi = '';
                        if ($kec !== '' && $kab !== '') $lokasi = $kec . ', ' . $kab;
                        elseif ($kec !== '') $lokasi = $kec;
                        elseif ($kab !== '') $lokasi = $kab;
                    @endphp
                    Untuk <span class="font-semibold text-slate-800 dark:text-white">{{ $displayNama }}</span>@if($lokasi !== '')<span class="text-slate-500 dark:text-slate-400">, {{ $lokasi }}</span>@endif
                </p>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">Nama dan lokasi diambil otomatis dari Pengaturan → Profil Sekolah.</p>
            </div>
        </div>
    </div>

    @php $kontak = config('karsa.kontak'); @endphp
    <!-- Kredit Pengembang -->
    <div class="card p-6">
        <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Pengembang
        </h2>
        <div class="mt-3 space-y-2 text-sm">
            <p class="font-semibold text-slate-800 dark:text-white">Dikembangkan oleh {{ $kontak['pengembang'] ?? 'IrfanDev97 — OPS Rejoso' }}</p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ $kontak['whatsapp_link'] ?? 'https://wa.me/6285156830304' }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs font-semibold hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.04 2C6.58 2 2.1 6.45 2.1 11.94c0 1.75.46 3.45 1.32 4.95L2.04 22l5.25-1.38c1.45.79 3.08 1.2 4.75 1.2 5.46 0 9.94-4.45 9.94-9.94 0-2.66-1.03-5.16-2.9-7.03A9.86 9.86 0 0012.04 2zm5.2 14.3c-.23.64-1.34 1.2-1.87 1.27-.5.07-1 .07-1.62-.07-.44-.1-1-.32-1.72-.64-1.5-.67-2.46-2.23-2.54-2.34-.07-.1-.6-.8-.6-1.52s.38-1.08.52-1.22c.13-.14.29-.18.39-.18h.28c.09 0 .21-.03.33.25.12.28.41.97.45 1.04.04.07.07.15.02.24-.05.09-.08.14-.15.22l-.23.27c-.08.09-.16.19-.07.37.09.18.4.66.86 1.07.59.53 1.09.7 1.25.78.15.08.24.07.33-.04.09-.1.38-.44.48-.59.1-.15.2-.13.33-.08.13.05.84.4.99.47.14.07.24.1.27.16.04.06.04.33-.19.97z"/></svg>
                    WhatsApp {{ $kontak['whatsapp_display'] ?? '085156830304' }}
                </a>
                <a href="{{ $kontak['instagram_link'] ?? 'https://instagram.com/mukhamadirfan22' }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-pink-50 dark:bg-pink-500/10 border border-pink-200 dark:border-pink-500/30 text-pink-700 dark:text-pink-300 text-xs font-semibold hover:bg-pink-100 dark:hover:bg-pink-500/20 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" stroke-width="2"/><circle cx="12" cy="12" r="5" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>
                    {{ $kontak['instagram_display'] ?? '@mukhamadirfan22' }}
                </a>
            </div>
        </div>
    </div>

    <!-- Cara Update -->
    <div class="card p-6" id="card-update">
        <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Cara Update Aplikasi</h2>
        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
            Untuk memperbarui KARSA ke versi terbaru, unduh installer baru dari pengembang, lalu jalankan seperti biasa.
            <span class="font-bold text-emerald-600 dark:text-emerald-400">Data RKAS Anda AMAN dan tidak akan hilang atau tertimpa</span> — aplikasi otomatis mendeteksi database yang sudah ada di komputer dan mempertahankannya.
        </p>
        <div class="mt-3 flex items-start gap-2 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2.5">
            <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Jika ragu, lakukan <a href="{{ route('backup.index') }}" class="text-blue-600 dark:text-blue-400 underline">Backup</a> dulu sebelum update — satu klik, file .zip tersimpan.</span>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 items-center">
            <button id="btn-cek-update" type="button" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold shadow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Cek Update Sekarang
            </button>
            <span id="txt-update-status" class="text-xs text-slate-500 dark:text-slate-400"></span>
        </div>
        <div id="area-update-hasil" class="hidden mt-4 rounded-xl border-2 border-amber-300 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-4">
            <div class="text-sm font-extrabold text-amber-800 dark:text-amber-300">Versi <span id="tentang-versi-baru">-</span> tersedia</div>
            <div class="text-xs text-amber-700 dark:text-amber-300/80">Anda pakai v<span id="tentang-versi-sekarang">-</span> — ukuran <span id="tentang-ukuran">-</span> MB</div>
            <div class="mt-3 flex gap-2">
                <button id="btn-tentang-unduh" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold">Unduh Sekarang (~<span id="tentang-ukuran2">-</span> MB)</button>
                <a id="btn-tentang-buka" href="#" class="hidden px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">Buka File Installer</a>
            </div>
            <div id="tentang-progress" class="hidden mt-3 text-sm text-blue-600 dark:text-blue-400 flex items-center gap-2"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg> Sedang mengunduh...</div>
            <div id="tentang-done" class="hidden mt-3 text-sm font-bold text-emerald-600 dark:text-emerald-400">✓ Unduhan selesai — file di <code class="px-1 py-0.5 rounded bg-white dark:bg-slate-800 font-mono text-xs">storage/app/updates/</code> — buka file installer di atas untuk instal.</div>
        </div>
        <p class="mt-3 text-[11px] text-slate-400 dark:text-slate-500">Jika tidak ada update, akan tampil “Sudah versi terbaru”.</p>
    </div>

    <!-- Tur Ulang -->
    <div class="card p-6 border-dashed">
        <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Tur Interaktif</h2>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Baru pertama pakai KARSA? Tur singkat akan memandu Anda mengenal Dashboard, Lembar Kerja, dan Monitoring.</p>
        <button type="button" id="btn-restart-tour" class="mt-3 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Mulai Tur Ulang
        </button>
        <p class="mt-2 text-[11px] text-slate-400">Akan membuka Dashboard dan memulai tur dari awal. Cocok untuk demo ke staf baru.</p>
    </div>

    <!-- Riwayat Versi -->
    <div class="card p-6">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200">Riwayat Versi</h2>
            <a href="{{ route('tentang.index') }}" class="text-[11px] text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">CHANGELOG.md</a>
        </div>
        <div class="mt-4 prose prose-sm dark:prose-invert max-w-none prose-p:my-2 prose-ul:my-2">
            {!! $changelogHtml !!}
        </div>
        <details class="mt-4">
            <summary class="text-xs font-semibold text-slate-500 dark:text-slate-400 cursor-pointer hover:text-slate-700 dark:hover:text-slate-300">Lihat file mentah CHANGELOG.md</summary>
            <pre class="mt-3 bg-slate-900 text-slate-100 rounded-lg p-4 text-xs overflow-auto whitespace-pre-wrap break-words">{{ $changelogRaw }}</pre>
        </details>
    </div>
</div>

<script>
document.getElementById('btn-restart-tour')?.addEventListener('click', function() {
    try {
        localStorage.removeItem('karsa_tour_dashboard_seen');
        localStorage.removeItem('karsa_tour_rkas_seen');
        localStorage.removeItem('karsa_tour_monitoring_seen');
    } catch {}
    window.location.href = "{{ route('dashboard.index') }}";
});
// Update checker untuk Tentang
(function(){
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  let info = null;
  const btnCek = document.getElementById('btn-cek-update');
  const txtStatus = document.getElementById('txt-update-status');
  const area = document.getElementById('area-update-hasil');
  const prog = document.getElementById('tentang-progress');
  const done = document.getElementById('tentang-done');
  const btnUnduh = document.getElementById('btn-tentang-unduh');
  const btnBuka = document.getElementById('btn-tentang-buka');
  async function cek(){
    btnCek.disabled = true; txtStatus.textContent = 'Mengecek...';
    try{
      const r = await fetch('/update/cek', {headers:{'Accept':'application/json'}});
      const j = await r.json();
      if(!j.ada_update){
        txtStatus.textContent = j.checked ? 'Sudah versi terbaru ✓' : 'Tidak ada update / offline';
        area.classList.add('hidden');
        return;
      }
      info = j;
      document.getElementById('tentang-versi-baru').textContent = j.versi_baru;
      document.getElementById('tentang-versi-sekarang').textContent = j.versi_sekarang;
      document.getElementById('tentang-ukuran').textContent = j.ukuran_mb;
      document.getElementById('tentang-ukuran2').textContent = j.ukuran_mb;
      area.classList.remove('hidden');
      txtStatus.textContent = 'Update tersedia';
      // cek apakah sudah terunduh
      try{
        const s = await fetch('/update/status-unduhan?file=' + encodeURIComponent(j.nama_file), {headers:{'Accept':'application/json'}});
        const sj = await s.json();
        if(sj.exists){
          btnBuka.href = '/update/download-file/' + encodeURIComponent(sj.file);
          btnBuka.classList.remove('hidden');
          done.classList.remove('hidden');
        }
      }catch(e){}
    }catch(e){ txtStatus.textContent = 'Gagal cek (offline?)'; }
    finally{ btnCek.disabled = false; }
  }
  async function unduh(){
    if(!info) return;
    btnUnduh.disabled = true; prog.classList.remove('hidden'); done.classList.add('hidden');
    try{
      const r = await fetch('/update/unduh', {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body: JSON.stringify({url_unduh: info.url_unduh})});
      const j = await r.json();
      prog.classList.add('hidden');
      if(j.success){
        btnBuka.href = '/update/download-file/' + encodeURIComponent(j.file);
        btnBuka.classList.remove('hidden');
        done.classList.remove('hidden');
        txtStatus.textContent = 'Unduhan selesai';
      } else {
        alert(j.message || 'Gagal');
        txtStatus.textContent = j.message || 'Gagal';
      }
    }catch(e){ prog.classList.add('hidden'); alert('Gagal: '+e.message); }
    finally{ btnUnduh.disabled = false; }
  }
  btnCek?.addEventListener('click', cek);
  btnUnduh?.addEventListener('click', unduh);
})();
</script>
@endsection
