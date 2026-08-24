<?php

namespace App\Services\Reports;

use InvalidArgumentException;
use Illuminate\Contracts\Container\Container;

class ReportServiceFactory
{
    public function __construct(private readonly Container $container) {}

    /**
     * Resolve the report service class for the given report type.
     */
    public function make(string $type): ReportServiceInterface
    {
        $serviceClass = match (strtolower($type)) {
            'ledger'               => LedgerReportService::class,
            'patron'               => PatronReportService::class,
            'purchase'             => PurchaseReportService::class,
            'sales'                => SalesReportService::class,
            'payment', 'receipt'   => VoucherReportService::class,
            'inventory_stock'      => InventoryStockReportService::class,
            'inventory_inward'     => InventoryInwardReportService::class,
            'production_batch'     => ProductionBatchReportService::class,
            'machines_list'        => MachinesListReportService::class,
            'payroll_personnel'    => PayrollPersonnelReportService::class,
            'silo_stock_valuation' => SiloStockValuationReportService::class,
            'gstr1'                => Gstr1ReportService::class,
            'gstr3b'               => Gstr3bReportService::class,
            'tds_certificate'      => TdsCertificateReportService::class,
            'esi_pf_challan'       => EsiPfChallanReportService::class,
            'sales_register'       => SalesRegisterService::class,
            'purchase_register'    => PurchaseRegisterService::class,
            'machine_summary', 'vehicle_pl' => MachineReportService::class,
            default                => throw new InvalidArgumentException("Unsupported report type: {$type}"),
        };

        return $this->container->make($serviceClass);
    }
}
