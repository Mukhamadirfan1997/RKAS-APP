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
                    <span class="px-2.5 py-1 rounded-full bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 text-xs font-bold text-blue-700 dark:text-blue-300">Versi {{ $version ?? '—' }}</span>
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

    <!-- Server Internal (Port Dinamis) -->
    <div class="card p-6" id="card-server">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/></svg>
                    Server Internal
                </h2>
                <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Alamat internal aplikasi di komputer ini. Akses dari komputer lain diatur pada kartu <span class="font-semibold">Akses Jaringan (LAN)</span> di bawah.</p>
            </div>
            @if($modeDesktop)
                <span class="px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-[11px] font-bold text-emerald-700 dark:text-emerald-300 shrink-0">Mode Desktop</span>
            @else
                <span class="px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-700/60 border border-slate-200 dark:border-slate-600 text-[11px] font-bold text-slate-600 dark:text-slate-300 shrink-0">Mode Browser (dev)</span>
            @endif
        </div>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="rounded-xl border border-blue-200 dark:border-blue-500/30 bg-blue-50/60 dark:bg-blue-500/10 px-4 py-3">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Port Aktif</div>
                <div class="font-mono text-2xl font-extrabold text-slate-800 dark:text-white leading-tight" id="port-server">{{ $portServer }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/50 px-4 py-3 sm:col-span-2">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Alamat Lokal</div>
                <div class="mt-1 flex items-center gap-2 flex-wrap">
                    <code id="alamat-server" class="font-mono text-sm font-bold text-slate-800 dark:text-slate-100 break-all">{{ $alamatServer }}</code>
                    <button type="button" id="btn-salin-alamat" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-[11px] font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        Salin
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-500 dark:text-slate-400">
            <span>Host: <span class="font-mono font-semibold text-slate-700 dark:text-slate-200">{{ $hostServer }}</span></span>
            <span>PHP: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $versiPhp }}</span></span>
            <span>Laravel: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $versiLaravel }}</span></span>
        </div>

        <div class="mt-3 flex items-start gap-2 text-[11px] text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2.5">
            <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Port dipilih otomatis setiap aplikasi dibuka, jadi bisa berbeda dari sebelumnya. Angka di atas selalu menunjukkan port yang sedang aktif saat ini — tidak perlu diisi manual di mana pun.</span>
        </div>
    </div>

    @php
        $statusLan = $lan['status'] ?? 'nonaktif';
        $badgeLan = [
            'aktif' => [
                'teks' => 'LAN Aktif',
                'kelas' => 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300',
                'titik' => 'bg-emerald-500',
            ],
            'terputus' => [
                'teks' => 'Jaringan Terputus',
                'kelas' => 'bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/30 text-amber-700 dark:text-amber-300',
                'titik' => 'bg-amber-500',
            ],
            'nonaktif' => [
                'teks' => 'LAN Tidak Aktif',
                'kelas' => 'bg-slate-100 dark:bg-slate-700/60 border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300',
                'titik' => 'bg-slate-400',
            ],
        ][$statusLan] ?? [
            'teks' => 'LAN Tidak Aktif',
            'kelas' => 'bg-slate-100 dark:bg-slate-700/60 border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300',
            'titik' => 'bg-slate-400',
        ];
        $subnetTeks = empty($lan['subnet']) ? '—' : implode(', ', $lan['subnet']);
    @endphp

    <!-- Akses Jaringan (LAN) -->
    <div class="card p-6" id="card-lan">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="min-w-0">
                <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                    Akses Jaringan (LAN)
                </h2>
                <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Buka KARSA dari komputer lain di jaringan sekolah yang sama lewat browser. Data tetap tersimpan di komputer ini.</p>
            </div>
            <span id="lan-badge" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[11px] font-bold shrink-0 {{ $badgeLan['kelas'] }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $badgeLan['titik'] }}"></span>
                <span id="lan-badge-teks">{{ $badgeLan['teks'] }}</span>
            </span>
        </div>

        <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-3">
            <div class="rounded-xl border border-blue-200 dark:border-blue-500/30 bg-blue-50/60 dark:bg-blue-500/10 px-4 py-3 lg:col-span-2">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Alamat untuk Komputer Lain</div>
                <div class="mt-1 flex items-center gap-2 flex-wrap">
                    <code id="lan-url" class="font-mono text-sm font-bold text-slate-800 dark:text-slate-100 break-all">{{ $lan['alamat_lan'] ?? 'Belum tersedia — tidak ada jaringan aktif' }}</code>
                    <button type="button" id="btn-salin-lan" @disabled(empty($lan['alamat_lan'])) class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-[11px] font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-600 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        Salin
                    </button>
                </div>
                <div class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">Ketik alamat ini di browser komputer lain (tanpa memasang aplikasi). Alamat ikut berubah otomatis bila IP komputer ini berpindah.</div>
            </div>

            <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-800/50 px-4 py-3">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Komputer Terhubung</div>
                <div class="mt-1 flex items-end gap-1.5">
                    <span id="lan-klien" class="font-mono text-2xl font-extrabold text-slate-800 dark:text-white leading-none">{{ $lan['klien_aktif'] }}</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 mb-0.5">komputer</span>
                </div>
                <div class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">sesi aktif 5 menit terakhir</div>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-500 dark:text-slate-400">
            <span>Binding server: <span class="font-mono font-semibold text-slate-700 dark:text-slate-200" id="lan-binding">{{ $hostBinding }}</span></span>
            <span>Jaringan diizinkan: <span class="font-mono font-semibold text-slate-700 dark:text-slate-200" id="lan-subnet">{{ $subnetTeks }}</span></span>
            <span>IP komputer ini: <span class="font-mono font-semibold text-slate-700 dark:text-slate-200" id="lan-ip">{{ $lan['ip_utama'] ?? '—' }}</span></span>
        </div>

        <div class="mt-2 text-[11px] text-slate-500 dark:text-slate-400" id="lan-adapter">
            @forelse ($lan['antarmuka'] as $a)
                <span class="inline-flex items-center gap-1 mr-3">{{ $a['nama'] }} <span class="font-mono">({{ $a['ip'] }})</span></span>
            @empty
                <span>Tidak ada adapter jaringan fisik yang aktif.</span>
            @endforelse
        </div>

        @if ($dariKomputerUtama)
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="button" id="btn-lan-toggle" data-aktif="{{ $lan['aktif'] ? '1' : '0' }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold border transition-colors {{ $lan['aktif'] ? 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 hover:bg-red-100' : 'bg-blue-600 border-blue-600 text-white hover:bg-blue-700' }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span id="btn-lan-toggle-teks">{{ $lan['aktif'] ? 'Matikan Akses LAN' : 'Aktifkan Akses LAN' }}</span>
                </button>

                <button type="button" id="btn-firewall-buat" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold border transition-colors bg-white dark:bg-slate-700 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    Buat Aturan Firewall
                </button>
                <button type="button" id="btn-firewall-hapus" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold border transition-colors bg-white dark:bg-slate-700 border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600">
                    Hapus Aturan Firewall
                </button>
                <span id="lan-proses" class="text-[11px] text-slate-500 dark:text-slate-400"></span>
            </div>
        @else
            <div class="mt-4 text-[11px] text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-lg px-3 py-2.5">
                Pengaturan akses LAN hanya bisa diubah dari komputer utama KARSA. Anda sedang membuka dari komputer lain.
            </div>
        @endif

        <details class="mt-3 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-3 py-2.5">
            <summary class="text-[11px] font-bold text-slate-700 dark:text-slate-200 cursor-pointer select-none">Keamanan &amp; batasan akses bersamaan</summary>
            <div class="mt-2 space-y-1.5 text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                <p><span class="font-semibold text-slate-700 dark:text-slate-200">🔒 Tetap wajib login.</span> Setiap komputer memakai halaman login yang sama; tidak ada akses tanpa nama pengguna dan sandi. Percobaan login dibatasi 5 kali per menit per komputer.</p>
                <p><span class="font-semibold text-slate-700 dark:text-slate-200">🛡️ Hanya jaringan sekolah.</span> Hanya alamat di subnet <span class="font-mono">{{ $subnetTeks }}</span> yang diterima. Jaringan VPN, tethering, atau adapter virtual ditolak walaupun alamatnya privat.</p>
                <p><span class="font-semibold text-slate-700 dark:text-slate-200">🧱 Firewall sempit.</span> Tombol firewall hanya membuka <span class="font-mono">php.exe</span> milik KARSA pada profil Private/Domain — bukan seluruh program, dan tidak membuka jaringan Public maupun internet.</p>
                <p><span class="font-semibold text-slate-700 dark:text-slate-200">⚠️ Akses bersamaan terbatas.</span> Database SQLite menulis satu per satu dan server PHP bawaan melayani satu permintaan pada satu waktu. Beberapa komputer boleh membuka halaman, tetapi hindari menyimpan data pada saat yang sama; bila terasa lambat, ajak bergantian. Semua data &amp; berkas tetap berada di komputer ini (tidak ada salinan di komputer lain).</p>
                <p><span class="font-semibold text-slate-700 dark:text-slate-200">📴 Desktop Offline tetap normal.</span> Menyalakan atau mematikan LAN tidak me-restart server dan tidak memengaruhi pemakaian di komputer utama.</p>
            </div>
        </details>
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
            <div class="mt-3 flex flex-wrap gap-2 items-center">
                <button id="btn-tentang-unduh" class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold"><span id="tentang-unduh-label">Unduh Sekarang</span> (~<span id="tentang-ukuran2">-</span> MB)</button>
                <a id="btn-tentang-manual" href="#" class="hidden text-xs font-bold text-amber-800 dark:text-amber-300 hover:underline">Unduh manual</a>
                <a id="btn-tentang-buka" href="#" class="hidden px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold">Simpan File Installer</a>
            </div>
            <div id="tentang-progress" class="hidden mt-3 text-sm text-blue-600 dark:text-blue-400">
                <div class="flex items-center gap-2"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg> <span id="tentang-progress-text">Sedang mengunduh...</span></div>
                <div class="mt-2 h-2 rounded-full bg-blue-200/70 dark:bg-blue-500/20 overflow-hidden">
                    <div id="tentang-progress-bar" class="h-full bg-blue-600 dark:bg-blue-400 transition-[width] duration-300" style="width:0%"></div>
                </div>
            </div>
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
// Salin alamat lokal (port dinamis) — pakai Clipboard API, fallback textarea
// agar tetap bekerja di WebView2 lama yang belum mengizinkan navigator.clipboard.
document.getElementById('btn-salin-alamat')?.addEventListener('click', async function () {
    const teks = (document.getElementById('alamat-server')?.textContent || '').trim();
    if (teks === '') return;
    const berhasil = () => { if (window.karsaToast) window.karsaToast('Alamat disalin: ' + teks, 'success', 6000); };
    const gagal = () => { if (window.karsaToast) window.karsaToast('Gagal menyalin. Alamat: ' + teks, 'error', 9000); };
    try {
        await navigator.clipboard.writeText(teks);
        berhasil();
    } catch (e) {
        try {
            const area = document.createElement('textarea');
            area.value = teks;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            const ok = document.execCommand('copy');
            area.remove();
            ok ? berhasil() : gagal();
        } catch (_) {
            gagal();
        }
    }
});

document.getElementById('btn-restart-tour')?.addEventListener('click', async function() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    try {
        await fetch("{{ route('tur.reset') }}", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
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
  const fmtUnduh = (b) => (b/1048576).toFixed(1) + ' MB';
  function progresUnduh(text, persen){
    const txt = document.getElementById('tentang-progress-text');
    const bar = document.getElementById('tentang-progress-bar');
    if(txt) txt.textContent = text;
    if(bar) bar.style.width = Math.max(0, Math.min(100, persen||0)) + '%';
  }
  async function unduh(){
    if(!info) return;
    btnUnduh.disabled = true; prog.classList.remove('hidden'); done.classList.add('hidden');
    progresUnduh('Menyiapkan unduhan…', 0);
    try{
      if(typeof window.karsaUnduhUpdate !== 'function') throw new Error('Fungsi unduh belum siap — muat ulang halaman.');
      await window.karsaUnduhUpdate(info.url_unduh, {
        onProgress(msg){
          if(msg.phase === 'start'){
            progresUnduh(msg.already ? 'File installer sudah ada — memeriksa…' : 'Menghubungi server unduhan…', 0);
            return;
          }
          const total = msg.total || info.size_bytes || 0;
          const now = msg.downloaded || 0;
          const persen = total > 0 ? (now / total * 100) : 0;
          progresUnduh(
            total > 0
              ? 'Mengunduh ' + fmtUnduh(now) + ' / ' + fmtUnduh(total) + ' (' + Math.round(persen) + '%)'
              : 'Mengunduh ' + fmtUnduh(now),
            persen
          );
        },
        onDone(j){
          prog.classList.add('hidden');
          btnBuka.href = '/update/download-file/' + encodeURIComponent(j.file);
          btnBuka.classList.remove('hidden');
          done.classList.remove('hidden');
          txtStatus.textContent = j.already ? 'File installer sudah terunduh' : 'Unduhan selesai';
          if(window.karsaToast) window.karsaToast(j.already ? 'File installer sudah ada — klik "Simpan File Installer" untuk memilih lokasi.' : 'Unduhan selesai — klik "Simpan File Installer" untuk memilih lokasi penyimpanan.', 'success', 10000);
        },
        onError(j){
          prog.classList.add('hidden');
          txtStatus.textContent = j.message || 'Gagal';
          if(window.karsaToast) window.karsaToast(j.message || 'Gagal mengunduh installer.', 'error', 12000);
        }
      });
    }catch(e){
      prog.classList.add('hidden');
      txtStatus.textContent = 'Gagal: ' + e.message;
      if(window.karsaToast) window.karsaToast('Gagal mengunduh: '+e.message, 'error', 12000);
    }
    finally{ btnUnduh.disabled = false; }
  }
  const desktop = !!window.karsaDesktop;
  const btnManual = document.getElementById('btn-tentang-manual');
  if(desktop){
    const label = document.getElementById('tentang-unduh-label');
    if(label) label.textContent = 'Perbarui Sekarang';
    btnManual?.classList.remove('hidden');
  }

  async function perbaruiOtomatis(){
    if(!info) return;
    const yakin = window.confirm('Perbarui KARSA ke versi ' + info.versi_baru + ' sekarang?\n\nAplikasi akan tertutup dan terbuka kembali otomatis setelah pemasangan selesai. Data RKAS tidak dihapus.');
    if(!yakin) return;

    btnUnduh.disabled = true; prog.classList.remove('hidden'); done.classList.add('hidden');
    progresUnduh('Memeriksa pembaruan…', 0);
    try{
      if(typeof window.karsaPasangUpdate !== 'function') throw new Error('Fitur pembaruan belum siap — muat ulang aplikasi.');
      const hasil = await window.karsaPasangUpdate({
        onProgress(s){
          if(s.tahap === 'unduh'){
            const total = s.total || info.size_bytes || 0;
            const now = s.terunduh || 0;
            const persen = total > 0 ? (now / total * 100) : (s.persen || 0);
            progresUnduh(
              total > 0
                ? 'Mengunduh ' + fmtUnduh(now) + ' / ' + fmtUnduh(total) + ' (' + Math.round(persen) + '%)'
                : 'Mengunduh ' + fmtUnduh(now),
              persen
            );
          } else if(s.tahap === 'pasang'){
            progresUnduh('Memasang pembaruan… aplikasi akan menutup dan terbuka kembali.', 100);
          } else if(s.tahap === 'cek'){
            progresUnduh('Memeriksa pembaruan…', 0);
          }
        }
      });
      if(hasil === 'terbaru'){
        prog.classList.add('hidden');
        txtStatus.textContent = 'Sudah versi terbaru ✓';
        if(window.karsaToast) window.karsaToast('KARSA sudah versi terbaru.', 'info', 8000);
      }
    }catch(e){
      prog.classList.add('hidden');
      const pesan = (e && (e.message || e)) || 'tidak diketahui';
      txtStatus.textContent = 'Gagal memperbarui otomatis';
      if(window.karsaToast) window.karsaToast('Gagal memperbarui otomatis: ' + pesan + ' — silakan pakai "Unduh manual".', 'error', 14000);
    }
    finally{ btnUnduh.disabled = false; }
  }

  btnCek?.addEventListener('click', cek);
  btnUnduh?.addEventListener('click', () => { if(desktop) perbaruiOtomatis(); else unduh(); });
  btnManual?.addEventListener('click', (ev) => { ev.preventDefault(); unduh(); });
})();

// --- Akses Jaringan (LAN) ---
(function(){
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const badge = document.getElementById('lan-badge');
  const badgeTeks = document.getElementById('lan-badge-teks');
  const urlEl = document.getElementById('lan-url');
  const btnSalin = document.getElementById('btn-salin-lan');
  const klienEl = document.getElementById('lan-klien');
  const subnetEl = document.getElementById('lan-subnet');
  const ipEl = document.getElementById('lan-ip');
  const adapterEl = document.getElementById('lan-adapter');
  const btnToggle = document.getElementById('btn-lan-toggle');
  const btnToggleTeks = document.getElementById('btn-lan-toggle-teks');
  const prosesEl = document.getElementById('lan-proses');
  if (!badge || !urlEl) return;

  const GAYA = {
    aktif: {
      badge: 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-300',
      titik: 'bg-emerald-500',
      teks: 'LAN Aktif',
      tombol: 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 hover:bg-red-100',
    },
    terputus: {
      badge: 'bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/30 text-amber-700 dark:text-amber-300',
      titik: 'bg-amber-500',
      teks: 'Jaringan Terputus',
      tombol: 'bg-red-50 dark:bg-red-500/10 border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 hover:bg-red-100',
    },
    nonaktif: {
      badge: 'bg-slate-100 dark:bg-slate-700/60 border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300',
      titik: 'bg-slate-400',
      teks: 'LAN Tidak Aktif',
      tombol: 'bg-blue-600 border-blue-600 text-white hover:bg-blue-700',
    },
  };

  function terapkan(r) {
    const gaya = GAYA[r.status] || GAYA.nonaktif;
    badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[11px] font-bold shrink-0 ' + gaya.badge;
    const titik = badge.querySelector('span');
    if (titik) {
      titik.classList.remove('bg-emerald-500', 'bg-amber-500', 'bg-slate-400');
      titik.classList.add(gaya.titik);
    }
    if (badgeTeks) badgeTeks.textContent = gaya.teks;

    const url = r.alamat_lan || '';
    urlEl.textContent = url || 'Belum tersedia — tidak ada jaringan aktif';
    if (btnSalin) btnSalin.disabled = !url;
    if (klienEl) klienEl.textContent = r.klien_aktif ?? 0;
    if (subnetEl) subnetEl.textContent = (r.subnet && r.subnet.length) ? r.subnet.join(', ') : '—';
    if (ipEl) ipEl.textContent = r.ip_utama || '—';

    if (adapterEl) {
      adapterEl.innerHTML = '';
      if (!r.antarmuka || !r.antarmuka.length) {
        adapterEl.textContent = 'Tidak ada adapter jaringan fisik yang aktif.';
      } else {
        r.antarmuka.forEach(function(a) {
          const span = document.createElement('span');
          span.className = 'inline-flex items-center gap-1 mr-3';
          span.textContent = a.nama + ' ';
          const mono = document.createElement('span');
          mono.className = 'font-mono';
          mono.textContent = '(' + a.ip + ')';
          span.appendChild(mono);
          adapterEl.appendChild(span);
        });
      }
    }

    if (btnToggle && btnToggleTeks) {
      btnToggle.dataset.aktif = r.aktif ? '1' : '0';
      btnToggleTeks.textContent = r.aktif ? 'Matikan Akses LAN' : 'Aktifkan Akses LAN';
      btnToggle.className = 'inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold border transition-colors ' + gaya.tombol;
    }
  }

  async function muatStatus() {
    try {
      const r = await fetch("{{ route('jaringan.status') }}", {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      });
      if (!r.ok) return;
      terapkan(await r.json());
    } catch (_) {}
  }

  btnToggle?.addEventListener('click', async function () {
    const aktifSekarang = btnToggle.dataset.aktif === '1';
    btnToggle.disabled = true;
    if (prosesEl) prosesEl.textContent = 'Menyimpan…';
    try {
      const r = await fetch("{{ route('jaringan.lan') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
        body: JSON.stringify({ aktif: !aktifSekarang }),
      });
      const j = await r.json().catch(() => ({}));
      if (!r.ok) throw new Error(j.message || 'Gagal menyimpan status LAN.');
      if (j.ringkasan) terapkan(j.ringkasan);
      if (window.karsaToast) window.karsaToast(j.message || 'Status LAN diperbarui.', 'success', 9000);
    } catch (e) {
      if (window.karsaToast) window.karsaToast(e.message || 'Gagal mengubah status LAN.', 'error', 11000);
    } finally {
      if (prosesEl) prosesEl.textContent = '';
      btnToggle.disabled = false;
    }
  });

  btnSalin?.addEventListener('click', async function () {
    const teks = (urlEl.textContent || '').trim();
    if (!teks || teks.indexOf('Belum') === 0) return;
    let berhasil = false;
    try {
      await navigator.clipboard.writeText(teks);
      berhasil = true;
    } catch (_) {
      const ta = document.createElement('textarea');
      ta.value = teks;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { berhasil = document.execCommand('copy'); } catch (_) {}
      ta.remove();
    }
    if (window.karsaToast) {
      window.karsaToast(
        berhasil ? 'Alamat LAN disalin: ' + teks : 'Gagal menyalin. Alamat LAN: ' + teks,
        berhasil ? 'success' : 'error',
        berhasil ? 8000 : 12000
      );
    }
  });

  // Aturan firewall hanya bisa diubah lewat aplikasi desktop (butuh izin admin).
  async function aturFirewall(aktifkan) {
    const inv = window.__TAURI_INTERNALS__ && window.__TAURI_INTERNALS__.invoke;
    if (typeof inv !== 'function') {
      if (window.karsaToast) {
        window.karsaToast('Aturan firewall hanya bisa diatur dari aplikasi desktop KARSA di komputer utama.', 'info', 10000);
      }
      return;
    }
    if (prosesEl) prosesEl.textContent = aktifkan ? 'Meminta izin administrator…' : 'Menghapus aturan firewall…';
    try {
      await inv('atur_firewall_lan', { aktifkan: aktifkan });
      if (window.karsaToast) {
        window.karsaToast(
          aktifkan
            ? 'Aturan firewall dibuat: hanya php.exe KARSA, profil Private/Domain.'
            : 'Aturan firewall KARSA dihapus.',
          'success',
          10000
        );
      }
    } catch (e) {
      const pesan = typeof e === 'string' ? e : ((e && e.message) || 'Gagal mengubah aturan firewall.');
      if (window.karsaToast) window.karsaToast(pesan, 'error', 14000);
    } finally {
      if (prosesEl) prosesEl.textContent = '';
    }
  }
  document.getElementById('btn-firewall-buat')?.addEventListener('click', function () { aturFirewall(true); });
  document.getElementById('btn-firewall-hapus')?.addEventListener('click', function () { aturFirewall(false); });

  // Segarkan status berkala: IP LAN bisa berubah (DHCP), klien bisa masuk/keluar.
  setInterval(function () { if (!document.hidden) muatStatus(); }, 20000);
})();
</script>

@endsection
