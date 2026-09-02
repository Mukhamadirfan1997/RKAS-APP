<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PengaturanSekolah;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('rkas.index');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'login',
                'auditable_type' => User::class,
                'auditable_id' => Auth::id(),
                'description' => 'Pengguna masuk ke aplikasi.',
                'ip_address' => $request->ip(),
            ]);

            return redirect()->intended(route('rkas.index'));
        }

        return back()->withErrors([
            'email' => 'Email atau kata sandi tidak cocok.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    // Lupa password via NPSN + email (offline, B)
    public function showForgot()
    {
        if (Auth::check()) {
            return redirect()->route('rkas.index');
        }
        return view('auth.lupa-password');
    }

    public function handleForgot(Request $request)
    {
        $request->validate([
            'npsn' => 'required|string|max:20',
            'email' => 'required|email|max:150',
        ]);

        $sekolah = PengaturanSekolah::first();
        $user = User::where('email', trim($request->email))->first();

        if (! $sekolah || empty($sekolah->npsn)) {
            return back()->withErrors(['npsn' => 'NPSN belum diatur di Pengaturan → Profil Sekolah. Hubungi operator.'])->onlyInput('email');
        }
        if (! $user) {
            AuditLog::create([
                'user_id' => null,
                'action' => 'password.reset_via_npsn',
                'auditable_type' => User::class,
                'auditable_id' => null,
                'description' => 'Gagal verifikasi NPSN+email (email tidak ditemukan): '.trim($request->email),
                'ip_address' => $request->ip(),
            ]);
            return back()->withErrors(['email' => 'Email tidak ditemukan.'])->onlyInput('email');
        }

        $validNpsn = hash_equals(trim((string) $sekolah->npsn), trim((string) $request->npsn));
        $validEmail = hash_equals(strtolower(trim($user->email)), strtolower(trim($request->email)));

        if (! $validNpsn || ! $validEmail) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'password.reset_via_npsn',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'description' => 'Gagal verifikasi NPSN+email',
                'ip_address' => $request->ip(),
            ]);
            return back()->withErrors(['npsn' => 'NPSN atau email tidak cocok.'])->onlyInput('email');
        }

        $request->session()->put('pw_reset_npsn_verified', true);
        $request->session()->put('pw_reset_npsn_at', now()->timestamp);
        $request->session()->put('pw_reset_user_id', $user->id);

        return redirect()->route('auth.reset');
    }

    public function showReset(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('rkas.index');
        }
        if (! $request->session()->get('pw_reset_npsn_verified')) {
            return redirect()->route('auth.forgot')->withErrors(['npsn' => 'Silakan verifikasi NPSN dan email terlebih dahulu.']);
        }
        $at = $request->session()->get('pw_reset_npsn_at', 0);
        if (now()->timestamp - $at > 600) {
            $request->session()->forget(['pw_reset_npsn_verified', 'pw_reset_npsn_at', 'pw_reset_user_id']);
            return redirect()->route('auth.forgot')->withErrors(['npsn' => 'Sesi verifikasi kedaluwarsa (10 menit). Silakan verifikasi ulang.']);
        }
        return view('auth.reset-password');
    }

    public function handleReset(Request $request)
    {
        if (! $request->session()->get('pw_reset_npsn_verified')) {
            return redirect()->route('auth.forgot')->withErrors(['npsn' => 'Sesi tidak valid. Verifikasi ulang NPSN+email.']);
        }
        $at = $request->session()->get('pw_reset_npsn_at', 0);
        if (now()->timestamp - $at > 600) {
            $request->session()->forget(['pw_reset_npsn_verified', 'pw_reset_npsn_at', 'pw_reset_user_id']);
            return redirect()->route('auth.forgot')->withErrors(['npsn' => 'Sesi kedaluwarsa. Verifikasi ulang.']);
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $userId = $request->session()->get('pw_reset_user_id');
        $user = $userId ? User::find($userId) : User::first();
        if (! $user) {
            return back()->withErrors(['password' => 'User tidak ditemukan.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'password.reset_via_npsn',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'description' => 'Password di-reset via NPSN+email',
            'ip_address' => $request->ip(),
        ]);

        $request->session()->forget(['pw_reset_npsn_verified', 'pw_reset_npsn_at', 'pw_reset_user_id']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('rkas.index')->with('success', 'Password berhasil di-reset. Anda otomatis masuk.');
    }
}
