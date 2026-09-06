<?php

namespace App\Http\Controllers;

use App\Services\LisensiService;
use Illuminate\Http\Request;

class AktivasiController extends Controller
{
    public function index()
    {
        $deviceCode = LisensiService::getOrCreateDeviceCode();
        $isRejoso = LisensiService::isRejosoExempt();
        $isTrialActive = LisensiService::isTrialActive();
        $sisaHari = LisensiService::sisaHari();
        $trialEnd = LisensiService::getTrialEndDate();
        $isReadOnly = LisensiService::isReadOnlyMode();
        $tahunAktif = LisensiService::getActiveTahun();
        $tahunAktifLicensed = $tahunAktif ? LisensiService::isYearLicensed((int)$tahunAktif->tahun) : false;
        $kontak = config('karsa.kontak');

        return view('aktivasi.index', compact(
            'deviceCode', 'isRejoso', 'isTrialActive', 'sisaHari', 'trialEnd',
            'isReadOnly', 'tahunAktif', 'tahunAktifLicensed', 'kontak'
        ));
    }

    public function activate(Request $request)
    {
        $request->validate([
            'kode_aktivasi' => 'required|string|max:32',
        ]);

        $deviceCode = LisensiService::getOrCreateDeviceCode();
        $tahunAktif = LisensiService::getActiveTahun();
        if (!$tahunAktif) {
            return redirect()->back()->withErrors(['error' => 'Tahun anggaran aktif tidak ditemukan.']);
        }
        $tahun = (int) $tahunAktif->tahun;
        $kode = trim($request->input('kode_aktivasi'));

        if (LisensiService::tryActivate($deviceCode, $tahun, $kode)) {
            return redirect()->route('aktivasi.index')->with('success', "Aktivasi berhasil untuk tahun {$tahun}. Terima kasih!");
        }

        return redirect()->back()->withErrors(['error' => 'Kode aktivasi tidak valid untuk tahun '.$tahun.' dan perangkat ini. Periksa kembali atau hubungi pengembang.']);
    }
}
