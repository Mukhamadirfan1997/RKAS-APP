<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TurController extends Controller
{
    private const MAP = [
        'dashboard' => 'tour_dashboard_seen',
        'rkas' => 'tour_rkas_seen',
        'monitoring' => 'tour_monitoring_seen',
    ];

    public function tandaiSelesai(Request $request, string $nama)
    {
        if (! array_key_exists($nama, self::MAP)) {
            return response()->json(['message' => 'Nama tur tidak valid.'], 422);
        }

        $user = Auth::user();
        $col = self::MAP[$nama];
        Log::info('Tur tandaiSelesai', ['nama' => $nama, 'col' => $col, 'user_id' => $user->id]);
        $user->forceFill([$col => true])->save();

        return response()->json(['ok' => true, 'nama' => $nama, $col => true]);
    }

    public function resetSemua(Request $request)
    {
        $user = Auth::user();
        $user->forceFill([
            'tour_dashboard_seen' => false,
            'tour_rkas_seen' => false,
            'tour_monitoring_seen' => false,
        ])->save();

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('dashboard.index')->with('success', 'Tur di-reset. Akan tampil lagi saat membuka tiap halaman.');
    }
}
