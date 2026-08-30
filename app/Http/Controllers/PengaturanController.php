<?php

namespace App\Http\Controllers;

use App\Models\PengaturanSekolah;
use App\Models\TahunAnggaran;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'pagu_total' => 'required|numeric|min:0',
            'pagu_tahap1' => 'required|numeric|min:0',
            'pagu_tahap2' => 'required|numeric|min:0',
            'sumber_dana' => 'nullable|string|max:50',
            'status_pengesahan' => 'nullable|string|max:50',
        ]);

        $ta = TahunAnggaran::where('tahun', 2026)->first() ?? TahunAnggaran::first();

        if ($ta) {
            $ta->update($validated);
        }

        return redirect()->route('pengaturan.index')->with('success', 'Pagu anggaran berhasil disimpan.');
    }
}
