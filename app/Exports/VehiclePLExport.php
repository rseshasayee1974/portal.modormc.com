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
            $sheet->setCellValueByColumnAndRow($colIndex, 1, $header);
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
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['registration']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['vehicle_model']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['trip_revenue']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['trip_cost']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['fuel_expenses']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['maintenance_expenses']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['other_expenses']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['total_cost']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['net_profit']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['margin_pct'] . '%');

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

        // Compute overall margin percentage
        $totalMarginPct = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100.0 : 0.0;

        // Write Totals Row
        $sheet->setCellValueByColumnAndRow(1, $rowNum, 'TOTAL');
        $sheet->setCellValueByColumnAndRow(3, $rowNum, $totalRevenue);
        $sheet->setCellValueByColumnAndRow(4, $rowNum, $totalTripCost);
        $sheet->setCellValueByColumnAndRow(5, $rowNum, $totalFuel);
        $sheet->setCellValueByColumnAndRow(6, $rowNum, $totalMaint);
        $sheet->setCellValueByColumnAndRow(7, $rowNum, $totalOther);
        $sheet->setCellValueByColumnAndRow(8, $rowNum, $totalCost);
        $sheet->setCellValueByColumnAndRow(9, $rowNum, $totalProfit);
        $sheet->setCellValueByColumnAndRow(10, $rowNum, round($totalMarginPct, 2) . '%');

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
        $spreadsheet->disconnectCells();
    }
}
