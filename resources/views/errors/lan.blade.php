@php
    $judulAlasan = [
        'nonaktif' => 'Akses LAN Tidak Aktif',
        'luar_subnet' => 'Jaringan Tidak Diizinkan',
    ][$alasan] ?? 'Akses Ditolak';
@endphp
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judulAlasan }} — KARSA</title>
    <link rel="icon" href="{{ asset('icons/logo.png') }}">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 24px; background: #f1f5f9; color: #0f172a;
            font-family: Inter, "Segoe UI", system-ui, -apple-system, sans-serif;
        }
        .kartu {
            width: 100%; max-width: 480px; background: #ffffff; border: 1px solid #e2e8f0;
            border-radius: 18px; padding: 28px; box-shadow: 0 18px 40px -24px rgba(15, 23, 42, .35);
        }
        .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
        .logo img { width: 34px; height: 34px; object-fit: contain; }
        .logo span { font-weight: 800; letter-spacing: .08em; font-size: 15px; }
        .ikon {
            width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center;
            justify-content: center; font-size: 22px; margin-bottom: 14px;
        }
        .nonaktif .ikon, .luar_subnet .ikon { background: #fef3c7; }
        h1 { font-size: 18px; margin: 0 0 10px; line-height: 1.35; }
        p { margin: 0 0 12px; font-size: 14px; line-height: 1.65; color: #334155; }
        ul { margin: 0 0 14px; padding-left: 20px; font-size: 13.5px; line-height: 1.7; color: #334155; }
        code {
            background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px;
            padding: 1px 6px; font-size: 12.5px; font-family: ui-monospace, Consolas, monospace;
        }
        .kaki { border-top: 1px solid #e2e8f0; padding-top: 14px; margin-top: 6px; font-size: 12.5px; color: #64748b; }
    </style>
</head>
<body>
    <main class="kartu {{ $alasan }}">
        <div class="logo">
            <img src="{{ asset('icons/logo.png') }}" alt="KARSA">
            <span>KARSA</span>
        </div>

        <div class="ikon" aria-hidden="true">{{ $alasan === 'nonaktif' ? '🔌' : '🛡️' }}</div>

        <h1>{{ $judulAlasan }}</h1>

        <p>{{ $pesan }}</p>

        @if ($alasan === 'nonaktif')
            <p>Aplikasi KARSA tetap bisa dipakai seperti biasa di komputer utama. Bila Anda memang ingin membuka akses dari komputer lain di jaringan sekolah, minta operator mengaktifkannya:</p>
            <ul>
                <li>Buka aplikasi KARSA di komputer utama.</li>
                <li>Masuk ke halaman <strong>Tentang Aplikasi</strong>.</li>
                <li>Pada kartu <strong>Akses Jaringan (LAN)</strong>, aktifkan akses LAN.</li>
            </ul>
        @else
            <p>Akses hanya diizinkan dari jaringan lokal yang sama dengan komputer KARSA (jaringan sekolah). Jaringan lain — termasuk VPN, tethering, atau adapter virtual — tidak diizinkan.</p>
            <ul>
                <li>Pastikan kedua komputer terhubung ke jaringan sekolah yang sama.</li>
                <li>Apabila alamat IP komputer KARSA berubah, minta alamat terbaru kepada operator.</li>
            </ul>
        @endif

        <div class="kaki">
            Tidak ada data yang ditampilkan atau dikirim. Semua data RKAS tetap tersimpan di komputer utama.
        </div>
    </main>
</body>
</html>
