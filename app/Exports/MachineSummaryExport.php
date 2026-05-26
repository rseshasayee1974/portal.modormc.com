<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Reports\MachineReportService;

class MachineSummaryExport
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
        $sheet->setTitle("Machine Summary");

        // Headers
        $headers = [
            'Registration',
            'Vehicle Model',
            'Vehicle Type',
            'Make Year',
            'Capacity',
            'Owner',
            'Trips Count',
            'Total Qty',
            'Total Weight (Tons)',
            'Total Revenue',
            'General Expenses',
            'Document Alerts',
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
        $totalTrips = 0;
        $totalQty = 0;
        $totalWeight = 0;
        $totalRevenue = 0;
        $totalExpenses = 0;

        // Process in chunks
        $this->query->chunk(1000, function ($items) use (&$sheet, &$rowNum, &$totalTrips, &$totalQty, &$totalWeight, &$totalRevenue, &$totalExpenses) {
            foreach ($items as $item) {
                $mapped = $this->service->mapMachineSummaryRow($item);

                // Compile alert messages into one string
                $alertMsgs = [];
                foreach ($mapped['alerts'] as $alert) {
                    $alertMsgs[] = $alert['message'];
                }
                $alertString = implode('; ', $alertMsgs);

                $colIdx = 1;
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['registration']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['vehicle_model']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['vehicle_type']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['make_year']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['capacity']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['owner']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['trips_count']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['total_qty']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['total_weight_tons']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['total_revenue']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $mapped['general_expenses']);
                $sheet->setCellValueByColumnAndRow($colIdx++, $rowNum, $alertString);

                // Accumulate totals
                $totalTrips += $mapped['trips_count'];
                $totalQty += $mapped['total_qty'];
                $totalWeight += $mapped['total_weight_tons'];
                $totalRevenue += $mapped['total_revenue'];
                $totalExpenses += $mapped['general_expenses'];

                $rowNum++;
            }
        });

        // Write Totals Row
        $sheet->setCellValueByColumnAndRow(1, $rowNum, 'TOTAL');
        $sheet->setCellValueByColumnAndRow(7, $rowNum, $totalTrips);
        $sheet->setCellValueByColumnAndRow(8, $rowNum, $totalQty);
        $sheet->setCellValueByColumnAndRow(9, $rowNum, $totalWeight);
        $sheet->setCellValueByColumnAndRow(10, $rowNum, $totalRevenue);
        $sheet->setCellValueByColumnAndRow(11, $rowNum, $totalExpenses);

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
