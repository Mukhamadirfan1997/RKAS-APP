<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class RkasKertasKerjaExport implements FromView
{
    public function __construct(
        protected $sekolah,
        protected $tahunAnggaran,
        protected $items
    ) {}

    public function view(): View
    {
        return view('rkas.excel', [
            'sekolah' => $this->sekolah,
            'tahunAnggaran' => $this->tahunAnggaran,
            'items' => $this->items,
        ]);
    }
}
