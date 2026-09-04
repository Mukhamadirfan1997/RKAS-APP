@once
<style>
    * { box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 0; }
    .kop { text-align: left; margin-bottom: 12px; border-bottom: 3px solid #1e3a8a; padding-bottom: 7px; }
    .kop h1 { font-size: 18px; margin: 0 0 4px; color: #1e3a8a; letter-spacing: 0.3px; font-weight: 800; }
    .kop h1 span.tahun { color: #ffffff; background: #1e3a8a; padding: 3px 9px; border-radius: 4px; font-size: 13px; vertical-align: middle; margin-left: 8px; }
    .kop .sub { font-size: 10px; color: #475569; line-height: 1.4; }
    h2.center { text-align: center; font-size: 14px; margin: 0 0 4px; font-weight: 800; color: #1e293b; letter-spacing: 0.2px; }
    p.center { text-align: center; font-size: 10px; margin: 0 0 8px; color: #475569; }
    .status-badge { display: inline-block; padding: 4px 16px; border-width: 1.5px; border-style: solid; font-size: 9px; font-weight: 800; letter-spacing: 1px; border-radius: 4px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; table-layout: fixed; }
    th, td { border: 1px solid #cbd5e1; padding: 7px 8px; text-align: left; vertical-align: middle; overflow: hidden; word-wrap: break-word; }
    th { background: #e2e8f0; font-size: 9.5px; text-transform: uppercase; text-align: center; font-weight: 800; letter-spacing: 0.3px; color: #1e293b; }
    th.col-kode-barang { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
    th.col-kode-rekening { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
    th.col-kontrol { background: #f1f5f9; color: #334155; font-size: 8px; line-height: 1.2; }
    th.col-kontrol small { display: block; font-size: 7px; font-weight: 400; text-transform: none; color: #64748b; margin-top: 1px; }
    th.col-validasi { background: #f1f5f9; color: #334155; font-size: 8px; line-height: 1.2; }
    th.col-validasi small { display: block; font-size: 7px; font-weight: 400; text-transform: none; color: #64748b; margin-top: 1px; }
    td { font-size: 11px; line-height: 1.4; }
    td.r, th.r { text-align: right; }
    td.c, th.c { text-align: center; }
    .kegiatan-row td { background: #f1f5f9; font-weight: 700; color: #1e293b; border-color: #cbd5e1; }
    .rekening-row td { background: #fffbeb; font-weight: 700; color: #92400e; border-color: #fde68a; }
    .item-row td { border-color: #e2e8f0; }
    .item-row:nth-child(even) td { background: #f8fafc; }
    .item-row:nth-child(odd) td { background: #ffffff; }
    .item-row td.col-kode-barang { background: #eff6ff; color: #1e40af; text-align: center; font-size: 9px; font-weight: 600; }
    .item-row:nth-child(even) td.col-kode-barang { background: #dbeafe; }
    .item-row td.col-kode-rekening { background: #fffbeb; color: #92400e; text-align: center; font-size: 9px; font-weight: 600; }
    .item-row:nth-child(even) td.col-kode-rekening { background: #fef3c7; }
    .badge-kontrol { display: inline-block; padding: 3px 7px; border-radius: 10px; font-size: 8px; font-weight: 800; letter-spacing: 0.3px; }
    .badge-kontrol.ok { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .badge-kontrol.selisih { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .badge-validasi { display: inline-block; padding: 3px 7px; border-radius: 10px; font-size: 8px; font-weight: 800; }
    .badge-validasi.benar { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .badge-validasi.salah { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .subtotal td { background: #f1f5f9; font-weight: 700; font-size: 10px; border-color: #94a3b8; }
    .grand td { background: #1e3a8a; color: #fff; font-weight: 800; border-color: #1e3a8a; font-size: 10px; }
    .grand td.r { color: #fff; }
    .grand-sisa td { background: #f1f5f9; font-weight: 700; color: #1e293b; text-align: right; padding: 10px 11px; font-size: 10px; border-color: #cbd5e1; }
    .blok-kegiatan { page-break-inside: avoid; margin-bottom: 3px; border: 1px solid #cbd5e1; border-radius: 3px; overflow: hidden; }
    .blok-kegiatan .kegiatan-title { background: #f8fafc; padding: 5px 9px; font-size: 10px; font-weight: 700; border-bottom: 1px solid #cbd5e1; color: #1e293b; }
    .blok-kegiatan .kegiatan-title span.kode { background: #dbeafe; color: #1e40af; padding: 3px 8px; border-radius: 3px; font-size: 9px; border: 1px solid #93c5fd; }
    .legend { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 9px 12px; margin: 10px 0 12px; font-size: 9px; color: #334155; line-height: 1.6; }
    .legend strong { color: #1e293b; }
    .legend .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; vertical-align: middle; margin-right: 3px; }
    .footer { margin-top: 22px; }
    .ttd { width: 100%; margin-top: 14px; }
    .ttd td { border: none; padding: 0; vertical-align: top; }
    .ttd .kolom { width: 33.33%; text-align: center; font-size: 11px; }
    .ttd .kolom p { margin: 5px 0; }
    .ttd .jarak-ttd { height: 85px; }
    .auraian { color: #1e293b; font-weight: 600; }
    .keterangan { color: #64748b; font-style: italic; font-size: 8px; display: block; margin-top: 2px; }
</style>
@endonce
