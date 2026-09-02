<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PengaturanController extends Controller
{
    private function resolveTahun(Request $request, bool $allowQuery = false): ?TahunAnggaran
    {
        if ($allowQuery && $request->filled('tahun')) {
            $ta = TahunAnggaran::where('tahun', (int) $request->tahun)->first();
            if ($ta) return $ta;
        }
        return TahunAnggaran::where('is_active', true)->first()
            ?? TahunAnggaran::where('tahun', 2026)->first()
            ?? TahunAnggaran::first();
    }

    public function index(Request $request)
    {
        $sekolah = PengaturanSekolah::first() ?? new PengaturanSekolah;
        $tahunAnggaran = $this->resolveTahun($request, true) ?? new TahunAnggaran(['tahun'=>2026]);
        $daftarTahun = TahunAnggaran::orderBy('tahun','desc')->get();
        $user = auth()->user();

        return view('pengaturan.index', compact('sekolah', 'tahunAnggaran', 'daftarTahun', 'user'));
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

    public function updateAkun(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email,'.$user->id,
            'current_password' => 'required|string|current_password',
            'password' => 'nullable|string|min:8|confirmed',
        ], [
            'current_password.current_password' => 'Password saat ini tidak cocok.',
        ]);

        $old = $user->only(['name', 'email']);
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'akun.update',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'description' => 'Akun operator diperbarui',
            'old_values' => $old,
            'new_values' => ['name' => $user->name, 'email' => $user->email, 'password_changed' => ! empty($validated['password'])],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('pengaturan.index')->with('success', 'Akun berhasil diperbarui.');
    }

    public function updatePagu(Request $request)
    {
        $ta = $this->resolveTahun($request, true) ?? TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();

        // Guard: pagu tidak boleh diubah saat Disahkan
        if ($ta && $ta->status_pengesahan === 'Disahkan') {
            return redirect()->back()->withErrors(['error' => "RKAS TA {$ta->tahun} sudah disahkan dan tidak bisa diedit. Buka kembali dari halaman Pengaturan jika perlu revisi."]);
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
        $ta = $this->resolveTahun($request, true) ?? TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();
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
        $ta = $this->resolveTahun($request, true) ?? TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();
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

    public function storeTahun(Request $request)
    {
        $validated = $request->validate([
            'tahun' => 'required|integer|min:2020|max:2100|unique:tahun_anggaran,tahun',
            'sumber_dana' => 'nullable|string|max:50',
            'pagu_total' => 'nullable|numeric|min:0',
            'pagu_tahap1' => 'nullable|numeric|min:0',
            'pagu_tahap2' => 'nullable|numeric|min:0',
            'copy_from' => 'nullable|integer|exists:tahun_anggaran,tahun',
        ]);

        $paguTotal = $validated['pagu_total'] ?? 180320000;
        $paguT1 = $validated['pagu_tahap1'] ?? (int)($paguTotal/2);
        $paguT2 = $validated['pagu_tahap2'] ?? ($paguTotal - $paguT1);

        $ta = TahunAnggaran::create([
            'tahun' => $validated['tahun'],
            'sumber_dana' => $validated['sumber_dana'] ?? 'BOSP REGULER',
            'pagu_total' => $paguTotal,
            'pagu_tahap1' => $paguT1,
            'pagu_tahap2' => $paguT2,
            'is_active' => false,
            'status_pengesahan' => 'Draft',
        ]);

        if (! empty($validated['copy_from'])) {
            $src = TahunAnggaran::where('tahun', $validated['copy_from'])->first();
            if ($src) {
                $items = RkasItem::where('tahun_anggaran_id', $src->id)->with('alokasiBulan')->get();
                foreach ($items as $srcItem) {
                    $new = $srcItem->replicate();
                    $new->tahun_anggaran_id = $ta->id;
                    $new->no_urut = $srcItem->no_urut;
                    $new->save();
                    foreach ($srcItem->alokasiBulan as $ab) {
                        $new->alokasiBulan()->create([
                            'bulan' => $ab->bulan,
                            'volume' => $ab->volume,
                            'satuan' => $ab->satuan,
                            'jumlah' => $ab->jumlah,
                        ]);
                    }
                }
            }
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'tahun.create',
            'auditable_type' => TahunAnggaran::class,
            'auditable_id' => $ta->id,
            'description' => "Buat TA {$ta->tahun} (copy dari ".($validated['copy_from'] ?? 'kosong').")",
            'new_values' => ['tahun'=>$ta->tahun,'pagu_total'=>$ta->pagu_total],
        ]);

        return redirect()->route('pengaturan.index', ['tahun'=>$ta->tahun])->with('success', "TA {$ta->tahun} berhasil dibuat (Draft).");
    }

    public function activateTahun(Request $request, $id)
    {
        $ta = TahunAnggaran::findOrFail($id);
        TahunAnggaran::query()->update(['is_active'=>false]);
        $ta->update(['is_active'=>true]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'tahun.aktifkan',
            'auditable_type' => TahunAnggaran::class,
            'auditable_id' => $ta->id,
            'description' => "Aktifkan TA {$ta->tahun} sebagai tahun aktif",
            'new_values' => ['tahun'=>$ta->tahun],
        ]);

        return redirect()->route('pengaturan.index', ['tahun'=>$ta->tahun])->with('success', "TA {$ta->tahun} diaktifkan. Lembar kerja sekarang menampilkan TA {$ta->tahun}.");
    }
}
