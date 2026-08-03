<?php

namespace App\Services;

use App\Repositories\DashboardRepository;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    protected $repository;

    public function __construct(DashboardRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getSalesSummary(array $filters)
    {
        $key = 'dashboard.sales.summary.' . auth()->id() . '.' . md5(json_encode($filters));
        return Cache::remember($key, now()->addMinutes(5), function () use ($filters) {
            return $this->repository->getSalesSummary($filters);
        });
    }

    public function getSalesStats(array $filters)
    {
        return $this->repository->getSalesStats($filters);
    }

    public function getTopProducts(array $filters)
    {
        $key = 'dashboard.top.products.' . auth()->id() . '.' . md5(json_encode($filters));
        return Cache::remember($key, now()->addMinutes(5), function () use ($filters) {
            return $this->repository->getTopProducts($filters);
        });
    }

    public function getTopMixDesignsFromBatches(array $filters)
    {
        return $this->repository->getTopMixDesignsFromBatches($filters);
    }

    public function getStockDetails(array $filters)
    {
        $key = 'dashboard.stock.details.' . auth()->id() . '.' . md5(json_encode($filters));
        return Cache::remember($key, now()->addMinutes(5), function () use ($filters) {
            return $this->repository->getStockDetails($filters);
        });
    }

    public function getTripsDetails(array $filters)
    {
        $key = 'dashboard.trips.details.' . auth()->id() . '.' . md5(json_encode($filters));
        return Cache::remember($key, now()->addMinutes(5), function () use ($filters) {
            return $this->repository->getTripsDetails($filters);
        });
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
        return $this->repository->getSalesDetailsByPaymentMode($filters);
    }

    public function getDispatchSalesAmounts(array $filters)
    {
        return $this->repository->getDispatchSalesAmounts($filters);
    }

    public function getDispatchBatchingSummary(array $filters)
    {
        return $this->repository->getDispatchBatchingSummary($filters);
    }

    public function getDispatchDetailsByTruck(array $filters)
    {
        return $this->repository->getDispatchDetailsByTruck($filters);
    }

    public function getDispatchDetails(array $filters)
    {
        return $this->repository->getDispatchDetails($filters);
    }
}
