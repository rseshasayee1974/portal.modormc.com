<?php

namespace App\Services;

use App\Repositories\DashboardRepository;

class DashboardService
{
    protected $repository;

    public function __construct(DashboardRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getSalesSummary(array $filters)
    {
        return $this->repository->getSalesSummary($filters);
    }

    public function getSalesStats(array $filters)
    {
        return $this->repository->getSalesStats($filters);
    }

    public function getTopProducts(array $filters)
    {
        return $this->repository->getTopProducts($filters);
    }

    public function getStockDetails(array $filters)
    {
        return $this->repository->getStockDetails($filters);
    }

    public function getTripsDetails(array $filters)
    {
        return $this->repository->getTripsDetails($filters);
    }

    public function getCustomerDetails(array $filters)
    {
        return $this->repository->getCustomerDetails($filters);
    }

    public function getAlerts(array $filters)
    {
        return $this->repository->getAlerts($filters);
    }

    public function getPlants()
    {
        return $this->repository->getPlants();
    }

    public function getSalesDetails(array $filters)
    {
        $summary = $this->repository->getSalesSummary($filters);
        $stats = $this->repository->getSalesStats($filters);

        // This is a simplified version, ideally separate queries for cash/credit breakdown
        return [
            'credit_sales' => [
                'amount' => $summary['credit_sales']['amount'],
                'trips' => 7, // Placeholder
                'quantity_mt' => 13.24, // Placeholder
                'quantity_cft' => 27 // Placeholder
            ],
            'cash_sales' => [
                'amount' => $summary['cash_sales']['amount'],
                'trips' => 7, // Placeholder
                'quantity_mt' => 13.24, // Placeholder
                'quantity_cft' => 27 // Placeholder
            ]
        ];
    }
}
