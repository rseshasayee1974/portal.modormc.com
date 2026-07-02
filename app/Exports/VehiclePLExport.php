<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Reports\MachineReportService;

class VehiclePLExport
{
    protected Builder $query;
    protected MachineReportService $service;

    public function __construct(Builder $query, MachineReportService $service)
    {
        $this->query = $query;
        $this->service = $service;
    }

    /**
     * Generate the Excel file and save it to the specified path.
     */
    public function export(string $filePath): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator("Modormc ERP");
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Vehicle PL Report");

        // Headers
        $headers = [
            'Registration',
            'Vehicle Model',
            'Trip Revenue',
            'Trip Cost',
            'Fuel Expenses',
            'Maintenance Expenses',
            'Other Expenses',
            'Total Cost',
            'Net Profit',
            'Profit Margin %',
        ];

        // Write headers
        $colIndex = 1;
        foreach ($headers as $header) {
            $this->setCell($sheet, $colIndex, 1, $header);
            $colIndex++;
        }

        // Apply style to header row
        $lastHeaderLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastHeaderLetter}1")->getFont()->setBold(true);

        $rowNum = 2;
        $totalRevenue = 0;
        $totalTripCost = 0;
        $totalFuel = 0;
        $totalMaint = 0;
        $totalOther = 0;
        $totalCost = 0;
        $totalProfit = 0;

        // Process in chunks
        $this->query->chunk(1000, function ($items) use (&$sheet, &$rowNum, &$totalRevenue, &$totalTripCost, &$totalFuel, &$totalMaint, &$totalOther, &$totalCost, &$totalProfit) {
            foreach ($items as $item) {
                $mapped = $this->service->mapVehiclePLRow($item);

                $colIdx = 1;
                $this->setCell($sheet, $colIdx++, $rowNum, $mapped['registration']);
                $this->setCell($sheet, $colIdx++, $rowNum, $mapped['vehicle_model']);
                $this->setCell($sheet, $colIdx++, $rowNum, $mapped['trip_revenue']);
                $this->setCell($sheet, $colIdx++, $rowNum, $mapped['trip_cost']);
                $this->setCell($sheet, $colIdx++, $rowNum, $mapped['fuel_expenses']);
                $this->setCell($sheet, $colIdx++, $rowNum, $mapped['maintenance_expenses']);
                $this->setCell($sheet, $colIdx++, $rowNum, $mapped['other_expenses']);
                $this->setCell($sheet, $colIdx++, $rowNum, $mapped['total_cost']);
                $this->setCell($sheet, $colIdx++, $rowNum, $mapped['net_profit']);
                $this->setCell($sheet, $colIdx++, $rowNum, $mapped['margin_pct'] . '%');

                // Accumulate totals
                $totalRevenue += $mapped['trip_revenue'];
                $totalTripCost += $mapped['trip_cost'];
                $totalFuel += $mapped['fuel_expenses'];
                $totalMaint += $mapped['maintenance_expenses'];
                $totalOther += $mapped['other_expenses'];
                $totalCost += $mapped['total_cost'];
                $totalProfit += $mapped['net_profit'];

                $rowNum++;
            }
        });

        // Compute overall margin %
        $totalMarginPct = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100.0 : 0.0;

        // Write Totals Row
        $this->setCell($sheet, 1, $rowNum, 'TOTAL');
        $this->setCell($sheet, 3, $rowNum, $totalRevenue);
        $this->setCell($sheet, 4, $rowNum, $totalTripCost);
        $this->setCell($sheet, 5, $rowNum, $totalFuel);
        $this->setCell($sheet, 6, $rowNum, $totalMaint);
        $this->setCell($sheet, 7, $rowNum, $totalOther);
        $this->setCell($sheet, 8, $rowNum, $totalCost);
        $this->setCell($sheet, 9, $rowNum, $totalProfit);
        $this->setCell($sheet, 10, $rowNum, round($totalMarginPct, 2) . '%');

        // Styling the total row
        $sheet->getStyle("A{$rowNum}:{$lastHeaderLetter}{$rowNum}")->getFont()->setBold(true);

        // Auto-size columns
        for ($c = 1; $c <= count($headers); $c++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Save
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
        $spreadsheet->disconnectWorksheets();
    }

    private function setCell($sheet, int $colIndex, int $rowIndex, $value): void
    {
        $cellAddress = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;
        $sheet->setCellValue($cellAddress, $value);
    }
}
}
