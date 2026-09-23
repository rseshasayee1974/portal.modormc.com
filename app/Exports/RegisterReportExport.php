<?php

namespace App\Exports;

use App\Services\Reports\RegisterReportColumns;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RegisterReportExport
{
    public function export(string $path, string $title, array $filters, array $report): void
    {
        $book = $this->workbook($title, $filters, $report);
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
    }

    public function workbook(string $title, array $filters, array $report): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle($title);
        $columns = $report['columns'];
        $last = Coordinate::stringFromColumnIndex(count($columns));
        $sheet->mergeCells("A1:{$last}1")->setCellValue('A1', $title.' - '.ucfirst($report['register_view']));
        $period = $filters['period_label'] ?? ('Period: '.$filters['from_date'].' to '.$filters['to_date']);
        $sheet->mergeCells("A2:{$last}2")->setCellValue('A2', $period);
        $sheet->mergeCells("A3:{$last}3")->setCellValue('A3', $report['note']);
        $sheet->fromArray(array_column($columns, 'label'), null, 'A4');
        $sheet->getDefaultRowDimension()->setRowHeight(22);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(4)->setRowHeight(32);
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1D2D3E');
        $sheet->getStyle("A4:{$last}4")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A4:{$last}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('334155');
        $sheet->getStyle("A4:{$last}4")->getAlignment()->setWrapText(true);

        foreach ($report['data'] as $index => $row) {
            $r = $index + 5;
            foreach ($columns as $c => $column) {
                $cell = Coordinate::stringFromColumnIndex($c + 1).$r;
                $value = RegisterReportColumns::value($row, $column['key']);
                if ($column['format'] === 'number') {
                    $sheet->setCellValueExplicit($cell, (float) $value, DataType::TYPE_NUMERIC);
                } elseif ($column['format'] === 'date' && $value !== '') {
                    $sheet->setCellValueExplicit($cell, Date::PHPToExcel(new \DateTimeImmutable($value)), DataType::TYPE_NUMERIC);
                } else {
                    // Preserve leading zeros and prevent party/product names becoming formulas.
                    $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                }
            }
            if ($index % 2 === 1) {
                $sheet->getStyle("A{$r}:{$last}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
            }
        }
        $totalRow = count($report['data']) + 5;
        $sheet->setCellValue('A'.$totalRow, 'TOTAL');
        foreach ($columns as $c => $column) {
            $letter = Coordinate::stringFromColumnIndex($c + 1);
            if ($column['total']) {
                $sheet->setCellValueExplicit($letter.$totalRow,
                    RegisterReportColumns::value($report['totals'], $column['total']), DataType::TYPE_NUMERIC);
            }
            $format = match ($column['format']) {
                'date' => 'dd/mm/yyyy', 'number' => '#,##0.00', default => '@',
            };
            $sheet->getStyle("{$letter}5:{$letter}{$totalRow}")->getNumberFormat()->setFormatCode($format);
            $width = $column['format'] === 'number' ? 17 : 24;
            if (in_array($column['key'], ['product_name', 'customer_name', 'supplier_name', 'created_by', 'irn', 'description', 'unloading'])) {
                $width = $column['key'] === 'irn' ? 68 : 34;
            }
            if ($column['key'] === 'description') $width = 60;
            $sheet->getColumnDimension($letter)->setWidth($width);
        }
        $sheet->getStyle("A{$totalRow}:{$last}{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalRow}:{$last}{$totalRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
        $sheet->freezePane('F5');
        $sheet->setAutoFilter('A4:'.$last.max(4, $totalRow - 1));
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        return $book;
    }
}
