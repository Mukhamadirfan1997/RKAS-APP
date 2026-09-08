<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

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
            'flatGroups' => $this->data['flatGroups'] ?? $this->groups,
            'groups' => $this->groups,
            'hierarchy' => $this->data['hierarchy'] ?? collect(),
            'grandPerBulanVol' => $this->data['grandPerBulanVol'] ?? [],
            'grandPerBulanJml' => $this->data['grandPerBulanJml'] ?? $this->data['grandPerBulan'] ?? [],
            'grandPerBulan' => $this->data['grandPerBulan'] ?? [],
            'grandTahap1' => $this->data['grandTahap1'] ?? 0,
            'grandTahap1Jml' => $this->data['grandTahap1Jml'] ?? $this->data['grandTahap1'] ?? 0,
            'grandTahap2' => $this->data['grandTahap2'] ?? 0,
            'grandTahap2Jml' => $this->data['grandTahap2Jml'] ?? $this->data['grandTahap2'] ?? 0,
            'grandTotal' => $this->data['grandTotal'] ?? 0,
            'grandKoreksi' => $this->data['grandKoreksi'] ?? 0,
            'grandKontrol' => $this->data['grandKontrol'] ?? 0,
            'sisaPagu' => $this->data['sisaPagu'] ?? 0,
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $highestColumn = $sheet->getHighestColumn();
                $highestRow = $sheet->getHighestRow();

                // Page setup: landscape, fit to width, print area full range
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getPageSetup()->setPrintArea("A1:{$highestColumn}{$highestRow}");
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->getPageMargins()->setTop(0.3);
                $sheet->getPageMargins()->setBottom(0.3);
                $sheet->getPageMargins()->setLeft(0.2);
                $sheet->getPageMargins()->setRight(0.2);

                // Deteksi baris header (cari "No" di kolom A + "Kode Barang" di B) — kop kini 5 baris sebelum header
                $headerRow = 2;
                for ($r = 1; $r <= min(10, $highestRow); $r++) {
                    $a = trim((string) ($sheet->getCell("A{$r}")->getValue() ?? ''));
                    $b = trim((string) ($sheet->getCell("B{$r}")->getValue() ?? ''));
                    if ($a === 'No' && $b === 'Kode Barang') {
                        $headerRow = $r;
                        break;
                    }
                }
                $freezeRow = $headerRow + 1;
                $sheet->freezePane("D{$freezeRow}");
                $sheet->setAutoFilter("A{$headerRow}:{$highestColumn}{$headerRow}");

                // Column widths untuk 36 kolom (A-AJ): No, Kode Barang, Uraian, Kode Rekening, Vol, Satuan, Harga, Jan-Jun+ Tahap I, Jul-Des+ Tahap II, Jumlah, Kontrol, Validasi
                $widths = [
                    'A' => 6,  // No
                    'B' => 13, // Kode Barang
                    'C' => 34, // Uraian
                    'D' => 14, // Kode Rekening
                    'E' => 9,  // Volume
                    'F' => 9,  // Satuan
                    'G' => 12, // Harga Satuan
                ];
                foreach ($widths as $col => $w) {
                    $sheet->getColumnDimension($col)->setWidth($w);
                }
                // Jan-Jun Vol/Jml H-S (12 cols) + Tahap I T
                $monthCols1 = ['H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S'];
                foreach ($monthCols1 as $col) {
                    $isVol = in_array($col, ['H', 'J', 'L', 'N', 'P', 'R']);
                    $sheet->getColumnDimension($col)->setWidth($isVol ? 8 : 10);
                }
                $sheet->getColumnDimension('T')->setWidth(13); // JUMLAH TAHAP I
                // Jul-Des Vol/Jml U-AF (12 cols) + Tahap II AG
                $monthCols2 = ['U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF'];
                foreach ($monthCols2 as $col) {
                    $isVol = in_array($col, ['U', 'W', 'Y', 'AA', 'AC', 'AE']);
                    $sheet->getColumnDimension($col)->setWidth($isVol ? 8 : 10);
                }
                $sheet->getColumnDimension('AG')->setWidth(13); // JUMLAH TAHAP II
                $sheet->getColumnDimension('AH')->setWidth(16); // TOTAL (TAHAP I + TAHAP II) - final, menempel langsung setelah JUMLAH TAHAP II
                $sheet->getColumnDimension('AI')->setWidth(10); // Kontrol
                $sheet->getColumnDimension('AJ')->setWidth(11); // Validasi

                // Styling & outline untuk flat 3 level: Kegiatan=1, Rekening=2, Item=0 (data mulai setelah header)
                $firstDataRow = $headerRow + 1;
                for ($row = $firstDataRow; $row <= $highestRow; $row++) {
                    $valA = $sheet->getCell("A{$row}")->getValue();
                    $valB = $sheet->getCell("B{$row}")->getValue();
                    $valC = $sheet->getCell("C{$row}")->getValue();
                    $valE = $sheet->getCell("E{$row}")->getValue();
                    $aVal = trim((string) ($valA ?? ''));
                    $cVal = trim((string) ($sheet->getCell("C{$row}")->getValue() ?? ''));
                    $isGrand = str_contains($aVal, 'GRAND') || str_contains($cVal, 'GRAND') || str_contains((string) $valB, 'GRAND');

                    if ($isGrand) {
                        $sheet->getRowDimension($row)->setOutlineLevel(0);
                        $sheet->getStyle("A{$row}:AJ{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
                        $sheet->getStyle("A{$row}:AJ{$row}")->getFont()->setBold(true);

                        continue;
                    }

                    // Deteksi flat: Kegiatan B+ C terisi, D kosong, A kosong ; Rekening D+ C terisi, B kosong
                    $bStr = trim((string) ($valB ?? ''));
                    $cStr = trim((string) ($sheet->getCell("C{$row}")->getValue() ?? ''));
                    $dStr = trim((string) ($sheet->getCell("D{$row}")->getValue() ?? ''));
                    $aStr = trim((string) ($valA ?? ''));

                    $isKegiatan = $bStr !== '' && $dStr === '' && $cStr !== '' && $aStr === '';
                    $isRekening = $dStr !== '' && $bStr === '' && $cStr !== '' && $aStr === '';
                    $isItem = is_numeric($valA) && $valA !== '' && $valA !== null;

                    if ($isKegiatan) {
                        $sheet->getRowDimension($row)->setOutlineLevel(1);
                        $sheet->getStyle("A{$row}:AJ{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
                        $sheet->getStyle("A{$row}:AJ{$row}")->getFont()->setBold(true)->getColor()->setRGB('1E293B');
                    } elseif ($isRekening) {
                        $sheet->getRowDimension($row)->setOutlineLevel(2);
                        $sheet->getStyle("A{$row}:AJ{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
                        $sheet->getStyle("A{$row}:AJ{$row}")->getFont()->setBold(true)->getColor()->setRGB('334155');
                    } elseif ($isItem) {
                        $sheet->getRowDimension($row)->setOutlineLevel(0);
                        $sheet->getStyle("A{$row}:AJ{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
                        $sheet->getStyle("A{$row}:AJ{$row}")->getFont()->setBold(false);
                    } else {
                        $sheet->getRowDimension($row)->setOutlineLevel(0);
                    }
                }
                $sheet->setShowSummaryBelow(false);
            },
        ];
    }
}
