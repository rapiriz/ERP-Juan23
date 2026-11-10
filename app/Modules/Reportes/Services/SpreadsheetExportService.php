<?php

namespace App\Modules\Reportes\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpreadsheetExportService
{
    public function download(string $title, array $headers, array $rows, string $filename, array $summary = []): StreamedResponse
    {
        return response()->streamDownload(function () use ($title, $headers, $rows, $summary): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Reporte');
            $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

            $sheet->setCellValueExplicit('A1', $title, DataType::TYPE_STRING);
            $sheet->mergeCells("A1:{$lastColumn}1");
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            foreach ($headers as $index => $header) {
                $sheet->setCellValueExplicit([$index + 1, 3], $header, DataType::TYPE_STRING);
            }
            $sheet->getStyle("A3:{$lastColumn}3")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle("A3:{$lastColumn}3")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('075BD8');

            foreach ($rows as $rowIndex => $row) {
                foreach (array_values($row) as $columnIndex => $value) {
                    if (is_int($value) || is_float($value)) {
                        $sheet->setCellValue([$columnIndex + 1, $rowIndex + 4], $value);
                    } else {
                        $sheet->setCellValueExplicit([$columnIndex + 1, $rowIndex + 4], (string) $value, DataType::TYPE_STRING);
                    }
                }
            }
            $summaryRow = count($rows) + 6;
            foreach ($summary as $label => $value) {
                $sheet->setCellValueExplicit([1, $summaryRow], $label, DataType::TYPE_STRING);
                if (is_int($value) || is_float($value)) {
                    $sheet->setCellValue([2, $summaryRow], $value);
                } else {
                    $sheet->setCellValueExplicit([2, $summaryRow], (string) $value, DataType::TYPE_STRING);
                }
                $sheet->getStyle('A'.$summaryRow.':B'.$summaryRow)->getFont()->setBold(true);
                $summaryRow++;
            }

            foreach (range(1, count($headers)) as $columnIndex) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
            }
            $sheet->freezePane('A4');
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
