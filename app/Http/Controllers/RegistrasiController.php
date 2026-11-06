<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PengaturanSekolah;
use App\Models\TahunAnggaran;
use App\Models\User;
use App\Services\BackupService;
use App\Support\Instalasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Registrasi awal & pemulihan database pada instalasi baru.
 *
 * Aturan penting:
 * - Tidak ada akun default. Akun operator hanya lahir dari halaman ini.
 * - Kedua halaman hanya bisa diakses selama belum ada akun (users kosong).
 *   Pengguna lama (sudah punya akun) tidak pernah melihat/memakainya.
 */
class RegistrasiController extends Controller
{
    public function show()
    {
        if (! Instalasi::bolehRegistrasi()) {
            return redirect()->route('login');
        }

        return view('auth.registrasi', $this->data());
    }

    public function store(Request $request)
    {
        if (! Instalasi::bolehRegistrasi()) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            // Profil sekolah
            'npsn' => 'required|string|max:20',
            'nama_sekolah' => 'required|string|max:150',
            'status_sekolah' => 'required|in:negeri,swasta',
            'nama_kepala_sekolah' => 'required|string|max:150',
            'nip_kepala_sekolah' => 'nullable|string|max:50',
            'nama_bendahara' => 'required|string|max:150',
            'nip_bendahara' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:255',
            'desa_kelurahan' => 'nullable|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
            'kabupaten_kota' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            // Akun operator
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
            ]);

            PengaturanSekolah::updateOrCreate(['id' => 1], [
                'npsn' => $validated['npsn'],
                'nama_sekolah' => $validated['nama_sekolah'],
                'status_sekolah' => $validated['status_sekolah'],
                'nama_kepala_sekolah' => $validated['nama_kepala_sekolah'],
                'nip_kepala_sekolah' => $validated['nip_kepala_sekolah'] ?? null,
                'nama_bendahara' => $validated['nama_bendahara'],
                'nip_bendahara' => $validated['nip_bendahara'] ?? null,
                'alamat' => $validated['alamat'] ?? null,
                'desa_kelurahan' => $validated['desa_kelurahan'] ?? null,
                'kecamatan' => $validated['kecamatan'] ?? null,
                'kabupaten_kota' => $validated['kabupaten_kota'] ?? null,
                'provinsi' => $validated['provinsi'] ?? null,
            ]);

            // Tahun anggaran awal: pagu dikosongkan (diisi operator di Pengaturan → Pagu),
            // karena aplikasi tidak boleh mengarang nominal anggaran sekolah.
            $tahun = (int) date('Y');
            TahunAnggaran::firstOrCreate(
                ['tahun' => $tahun],
                [
                    'sumber_dana' => 'BOSP REGULER',
                    'pagu_total' => 0,
                    'pagu_tahap1' => 0,
                    'pagu_tahap2' => 0,
                    'is_active' => true,
                    'status_pengesahan' => 'Draft',
                ]
            );

            return $user;
        });

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'instalasi.registrasi',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'description' => 'Registrasi awal aplikasi: profil sekolah & akun operator dibuat.',
            'new_values' => [
                'sekolah' => $validated['nama_sekolah'],
                'npsn' => $validated['npsn'],
                'email' => $user->email,
            ],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('login')
            ->with('status', 'Registrasi berhasil. Silakan masuk memakai akun yang baru dibuat.')
            ->with('registrasi_email', $user->email);
    }

    public function showPulihkan()
    {
        if (! Instalasi::bolehRegistrasi()) {
            return redirect()->route('login');
        }

        return view('auth.pulihkan', $this->data());
    }

    public function pulihkan(Request $request)
    {
        if (! Instalasi::bolehRegistrasi()) {
            return redirect()->route('login');
        }

        // PHP membuang seluruh body bila melebihi post_max_size; tanpa cek ini
        // pengguna hanya melihat "pilih berkas" padahal berkas sudah dipilih.
        $batasUnggah = BackupService::batasUnggahByte();
        $maksMb = BackupService::batasUnggahMb();
        $konten = (int) $request->server('CONTENT_LENGTH', 0);
        if ($konten > 0 && $batasUnggah > 0 && $konten > $batasUnggah) {
            return back()->withErrors([
                'file' => "Ukuran berkas backup melebihi batas server {$maksMb} MB. "
                    .'Pilih berkas backup yang lebih kecil atau minta bantuan operator lain.',
            ]);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:zip|max:'.($maksMb * 1024),
        ], [
            'file.required' => 'Pilih berkas backup (.zip) terlebih dahulu.',
            'file.mimes' => 'Berkas backup harus berformat .zip.',
            'file.max' => "Ukuran berkas backup melebihi batas server {$maksMb} MB.",
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $zip = $request->file('file');

        // Salin ke folder backup agar tetap ada walau berkas sementara dihapus PHP.
        File::ensureDirectoryExists(BackupService::dir());
        $nama = 'rkas-pulihkan-'.now()->format('Y-m-d_H-i-s').'.zip';
        $tujuan = BackupService::dir().'/'.$nama;
        File::copy($zip->getRealPath(), $tujuan);

        try {
            BackupService::restoreFromZip($tujuan);
        } catch (\Throwable $e) {
            Log::error('Pulihkan (instalasi baru) gagal: '.$e->getMessage());

            return back()->withErrors(['file' => 'Gagal memulihkan: '.$e->getMessage()]);
        }

        // Bila backup berisi akun → langsung bisa masuk. Bila tidak → lanjut registrasi.
        if (Instalasi::belumTerdaftar()) {
            return redirect()->route('registrasi')
                ->with('status', 'Backup berhasil dipulihkan. Berkas backup ini belum berisi akun operator, silakan lengkapi registrasi.');
        }

        return redirect()->route('login')
            ->with('status', 'Backup berhasil dipulihkan. Silakan masuk memakai akun yang ada di dalam backup.');
    }

    /**
     * Data pendukung tampilan (identitas aplikasi + sisa hari percobaan lisensi).
     */
    private function data(): array
    {
        return [
            'tahunAwal' => (int) date('Y'),
            'maksUnggahMb' => BackupService::batasUnggahMb(),
        ];
    }
}
