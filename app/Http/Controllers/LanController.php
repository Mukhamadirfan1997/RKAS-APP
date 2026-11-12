<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\JaringanService;
use App\Services\LanConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Pengaturan Akses LAN.
 *
 * Dua hal yang diatur di sini:
 * 1. Status LAN (berkas storage/app/lan.json) — boleh diubah kapan saja tanpa
 *    me-restart server, dan tanpa menyentuh database.
 * 2. Ringkasan jaringan untuk halaman Tentang (alamat lokal, alamat LAN, subnet,
 *    jumlah komputer yang sedang terhubung).
 *
 * Aturan firewall Windows TIDAK dibuat dari server: itu tugas aplikasi desktop
 * (perintah Tauri `atur_firewall_lan`) karena butuh izin administrator di
 * komputer utama. Server tidak punya hak untuk mengubah firewall.
 */
class LanController extends Controller
{
    public function __construct(
        private JaringanService $jaringan,
        private LanConfigService $lan
    ) {}

    /** Ringkasan status jaringan (JSON) untuk pembaruan berkala halaman Tentang. */
    public function status(Request $request): JsonResponse
    {
        return response()->json($this->jaringan->ringkasan((int) $request->getPort()));
    }

    /** Aktifkan / matikan akses LAN. Hanya boleh dari komputer utama. */
    public function atur(Request $request): JsonResponse|RedirectResponse
    {
        // Pengaturan jaringan hanya boleh diubah dari komputer utama. Klien LAN
        // yang sudah masuk pun tidak bisa mematikan/menyalakan akses orang lain.
        if (! $this->jaringan->ipLokal($request->ip())) {
            $pesan = 'Pengaturan akses LAN hanya bisa diubah dari komputer utama KARSA.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $pesan], 403)
                : redirect()->route('tentang.index')->withErrors(['lan' => $pesan]);
        }

        $data = $request->validate([
            'aktif' => ['required', 'boolean'],
        ]);

        $aktif = (bool) $data['aktif'];
        $sebelum = $this->lan->aktif();

        try {
            $this->lan->aturAktif($aktif, $request->user()?->id);
        } catch (RuntimeException $e) {
            $pesan = 'Status akses LAN gagal disimpan: '.$e->getMessage();

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $pesan], 500)
                : redirect()->route('tentang.index')->withErrors(['lan' => $pesan]);
        }

        if ($sebelum !== $aktif) {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => $aktif ? 'lan.aktifkan' : 'lan.matikan',
                'auditable_type' => 'pengaturan_jaringan',
                'auditable_id' => null,
                'description' => $aktif
                    ? 'Akses LAN diaktifkan. Klien dari subnet lokal diizinkan masuk (tetap wajib login).'
                    : 'Akses LAN dimatikan. Hanya komputer utama yang bisa membuka KARSA.',
                'old_values' => ['aktif' => $sebelum],
                'new_values' => ['aktif' => $aktif],
                'ip_address' => $request->ip(),
            ]);
        }

        $ringkasan = $this->jaringan->ringkasan((int) $request->getPort());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $aktif
                    ? 'Akses LAN diaktifkan.'
                    : 'Akses LAN dimatikan.',
                'ringkasan' => $ringkasan,
            ]);
        }

        $pesan = $aktif
            ? 'Akses LAN diaktifkan. Komputer lain di jaringan sekolah bisa membuka '.($ringkasan['alamat_lan'] ?? 'alamat LAN').' (langsung dari browser, tanpa aplikasi).'
            : 'Akses LAN dimatikan. Hanya komputer utama yang bisa membuka KARSA.';

        return redirect()->route('tentang.index')->with('success', $pesan);
    }
}
