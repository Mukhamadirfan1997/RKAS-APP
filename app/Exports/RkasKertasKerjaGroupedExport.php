<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class RkasKertasKerjaGroupedExport implements FromView, WithEvents
{
    public function __construct(
        protected $sekolah,
        protected $tahunAnggaran,
        protected $groups,
        protected array $data
    ) {}

    public function view(): View
    {
        return view('rkas.excel-grouped', [
            'sekolah' => $this->sekolah,
            'tahunAnggaran' => $this->tahunAnggaran,
            'groups' => $this->groups,
            'grandPerBulan' => $this->data['grandPerBulan'],
            'grandTahap1' => $this->data['grandTahap1'],
            'grandTahap2' => $this->data['grandTahap2'],
            'grandTotal' => $this->data['grandTotal'],
            'grandKoreksi' => $this->data['grandKoreksi'],
            'grandKontrol' => $this->data['grandKontrol'],
            'sisaPagu' => $this->data['sisaPagu'],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Freeze panes: kolom setelah Uraian (C) dan baris setelah header (4)
                // Header tabel ada di baris 4 (setelah 3 baris kop), data mulai baris 5
                $sheet->freezePane('D5');

                // Auto filter di header
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();
                $sheet->setAutoFilter("A4:{$highestColumn}4");

                // Outline level per kegiatan: item rows level 1 agar bisa collapse per kegiatan
                // Deteksi: header kegiatan mengandung "KEGIATAN", subtotal mengandung "Subtotal", item mengandung ID/barang
                for ($row = 5; $row <= $highestRow; $row++) {
                    $valA = $sheet->getCell("A{$row}")->getValue();
                    $valC = $sheet->getCell("C{$row}")->getValue();
                    $isHeader = is_string($valC) && str_starts_with(trim((string) $valC), 'KEGIATAN');
                    $isSubtotal = is_string($valC) && str_contains((string) $valC, 'Subtotal');
                    $isGrand = is_string($valC) && str_contains((string) $valC, 'GRAND TOTAL');

                    if ($isHeader || $isSubtotal || $isGrand) {
                        $sheet->getRowDimension($row)->setOutlineLevel(0);
                        // Bold header/subtotal sudah di view, tapi pastikan outline collapsed false
                    } else {
                        // Item row - level 1
                        // Cek apakah row ini memang item (punya No di kolom A)
                        if (is_numeric($valA) || (is_string($valA) && $valA !== '' && $valA !== null)) {
                            $sheet->getRowDimension($row)->setOutlineLevel(1);
                            $sheet->getRowDimension($row)->setVisible(true);
                        }
                    }
                }
                // Aktifkan outline summary below = false agar collapse di atas
                $sheet->setShowSummaryBelow(false);

                // Atur lebar kolom agar ID + Uraian berdekatan dan terbaca
                $sheet->getColumnDimension('A')->setWidth(6);
                $sheet->getColumnDimension('B')->setWidth(18); // ID Barang ARKAS
                $sheet->getColumnDimension('C')->setWidth(42); // Uraian
                $sheet->getColumnDimension('D')->setWidth(22); // Kode Rekening
                $sheet->getColumnDimension('E')->setWidth(14); // Volume
                $sheet->getColumnDimension('F')->setWidth(14); // Harga
                // Bulan G-R
                foreach (range('G', 'R') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(13);
                }
                $sheet->getColumnDimension('S')->setWidth(16);
                $sheet->getColumnDimension('T')->setWidth(13);
                $sheet->getColumnDimension('U')->setWidth(16);
                $sheet->getColumnDimension('V')->setWidth(10);
            },
        ];
    }
}
