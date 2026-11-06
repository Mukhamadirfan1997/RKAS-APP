<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Berkas Terlalu Besar — KARSA</title>
    <link rel="icon" href="{{ asset('icons/logo.png') }}">
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 24px; background: #f1f5f9; color: #0f172a;
            font-family: Inter, "Segoe UI", system-ui, -apple-system, sans-serif;
        }
        .card {
            width: 100%; max-width: 520px; background: #fff; border: 1px solid #e2e8f0;
            border-radius: 18px; padding: 28px; box-shadow: 0 10px 30px rgba(15, 23, 42, .08);
        }
        .logo { width: 44px; height: 44px; border-radius: 12px; object-fit: contain; }
        .badge {
            display: inline-flex; align-items: center; gap: 6px; margin-bottom: 10px;
            padding: 3px 10px; border-radius: 999px; background: #fef3c7; color: #92400e;
            font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
        }
        h1 { margin: 0 0 8px; font-size: 20px; letter-spacing: -.01em; }
        p { margin: 0 0 12px; font-size: 14px; line-height: 1.65; color: #334155; }
        .tip {
            margin-top: 16px; padding: 12px 14px; border-radius: 12px; background: #eff6ff;
            border: 1px solid #bfdbfe; color: #1e3a8a; font-size: 12.5px; line-height: 1.6;
        }
        a.btn {
            display: inline-flex; align-items: center; justify-content: center; margin-top: 18px;
            width: 100%; padding: 11px 16px; border-radius: 12px; background: #2563eb; color: #fff;
            font-size: 14px; font-weight: 700; text-decoration: none;
        }
        a.btn:hover { background: #1d4ed8; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f172a; color: #e2e8f0; }
            .card { background: #1e293b; border-color: #334155; box-shadow: none; }
            h1 { color: #f8fafc; }
            p { color: #cbd5e1; }
            .tip { background: rgba(59, 130, 246, .12); border-color: rgba(59, 130, 246, .35); color: #bfdbfe; }
        }
    </style>
</head>
<body>
    <main class="card">
        <img src="{{ asset('icons/logo.png') }}" alt="KARSA" class="logo">
        <div class="badge">Berkas terlalu besar</div>
        <h1>Unggahan melebihi batas server ({{ $maksMb }} MB)</h1>
        <p>{{ $pesan }}</p>
        <p>Data aplikasi <strong>tidak berubah</strong>. Berkas backup yang lebih besar dari
            {{ $maksMb }} MB tidak dapat dipulihkan lewat unggahan; gunakan berkas backup yang lebih kecil.</p>
        <div class="tip">
            Ukuran berkas backup biasanya &plusmn;&frac12; sampai &frac17; ukuran database mentah.
            Bila perlu memindahkan data besar, buat backup baru lalu pilih berkas terbaru dari daftar
            <strong>Backup</strong> di dalam aplikasi.
        </div>
        <a class="btn" href="{{ url('/') }}">&larr; Kembali ke Aplikasi</a>
    </main>
</body>
</html>
