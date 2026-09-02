<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PengaturanController extends Controller
{
    public function index()
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah;
        $tahunAnggaran = TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();

        return view('pengaturan.index', compact('sekolah', 'tahunAnggaran'));
    }

    public function updateSekolah(Request $request)
    {
        $validated = $request->validate([
            'npsn' => 'nullable|string|max:20',
            'nama_sekolah' => 'required|string|max:150',
            'nama_kepala_sekolah' => 'nullable|string|max:150',
            'nip_kepala_sekolah' => 'nullable|string|max:50',
            'nama_bendahara' => 'nullable|string|max:150',
            'nip_bendahara' => 'nullable|string|max:50',
            'status_sekolah' => 'required|in:negeri,swasta',
            'alamat' => 'nullable|string|max:255',
            'desa_kelurahan' => 'nullable|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
            'kabupaten_kota' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
        ]);

        PengaturanSekolah::updateOrCreate(['id' => 1], $validated);

        return redirect()->route('pengaturan.index')->with('success', 'Profil sekolah berhasil disimpan.');
    }

    public function updatePagu(Request $request)
    {
        $ta = TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();

        // Guard: pagu tidak boleh diubah saat Disahkan
        if ($ta && $ta->status_pengesahan === 'Disahkan') {
            return redirect()->back()->withErrors(['error' => 'RKAS TA ini sudah disahkan dan tidak bisa diedit. Buka kembali dari halaman Pengaturan jika perlu revisi.']);
        }

        $validated = $request->validate([
            'pagu_total' => 'required|numeric|min:0',
            'pagu_tahap1' => 'required|numeric|min:0',
            'pagu_tahap2' => 'required|numeric|min:0',
            'sumber_dana' => 'nullable|string|max:50',
            'status_pengesahan' => 'nullable|string|max:50',
        ]);

        if ($ta) {
            $ta->update($validated);
        }

        return redirect()->route('pengaturan.index')->with('success', 'Pagu anggaran berhasil disimpan.');
    }

    public function sahkan(Request $request)
    {
        $ta = TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();
        if (! $ta) {
            return redirect()->back()->withErrors(['error' => 'Tahun anggaran tidak ditemukan.']);
        }

        // Hanya dari Draft atau Pergeseran boleh disahkan
        if ($ta->status_pengesahan === 'Disahkan') {
            return redirect()->back()->withErrors(['error' => 'RKAS sudah dalam status Disahkan. Tidak perlu disahkan lagi.']);
        }
        if (! in_array($ta->status_pengesahan, ['Draft', 'Pergeseran'], true)) {
            return redirect()->back()->withErrors(['error' => 'Hanya RKAS dengan status Draft atau Pergeseran yang bisa disahkan. Status saat ini: '.$ta->status_pengesahan]);
        }

        // Buat snapshot resmi sebelum mengubah status — jika gagal, batalkan pengesahan
        $stamp = now()->format('Y-m-d_H-i-s');
        $filename = 'rkas-pengesahan-'.$ta->tahun.'-'.$stamp.'.zip';
        $zipPath = BackupService::dir().'/'.$filename;

        try {
            File::ensureDirectoryExists(BackupService::dir());
            BackupService::snapshotDbZip($zipPath);
        } catch (\Throwable $e) {
            Log::error('Gagal membuat backup pengesahan: '.$e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Gagal membuat backup pengesahan: '.$e->getMessage().' — pengesahan dibatalkan, silakan coba lagi.']);
        }

        $oldStatus = $ta->status_pengesahan;
        $ta->update(['status_pengesahan' => 'Disahkan']);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'rkas.sahkan',
            'auditable_type' => 'TahunAnggaran',
            'auditable_id' => $ta->id,
            'description' => "Pengesahan RKAS TA {$ta->tahun} ({$oldStatus} → Disahkan)",
            'old_values' => ['status_pengesahan' => $oldStatus],
            'new_values' => [
                'status_pengesahan' => 'Disahkan',
                'pagu_total' => $ta->pagu_total,
                'jumlah_item' => RkasItem::where('tahun_anggaran_id', $ta->id)->count(),
                'backup_file' => $filename,
            ],
        ]);

        return redirect()->route('pengaturan.index')->with('success', 'RKAS TA '.$ta->tahun.' berhasil disahkan. Backup resmi dibuat: '.$filename.' — data kini terkunci.');
    }

    public function bukaKembali(Request $request)
    {
        $ta = TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();
        if (! $ta) {
            return redirect()->back()->withErrors(['error' => 'Tahun anggaran tidak ditemukan.']);
        }

        if ($ta->status_pengesahan !== 'Disahkan') {
            return redirect()->back()->withErrors(['error' => 'Hanya RKAS dengan status Disahkan yang bisa dibuka kembali untuk revisi. Status saat ini: '.$ta->status_pengesahan]);
        }

        $oldStatus = $ta->status_pengesahan;
        $ta->update(['status_pengesahan' => 'Pergeseran']);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'rkas.buka-kembali',
            'auditable_type' => 'TahunAnggaran',
            'auditable_id' => $ta->id,
            'description' => "Buka kembali RKAS TA {$ta->tahun} untuk revisi (Disahkan → Pergeseran)",
            'old_values' => ['status_pengesahan' => $oldStatus],
            'new_values' => ['status_pengesahan' => 'Pergeseran'],
        ]);

        return redirect()->route('pengaturan.index')->with('success', 'RKAS TA '.$ta->tahun.' dibuka kembali untuk revisi (status: Pergeseran). Perubahan akan tercatat di audit log.');
    }
}
