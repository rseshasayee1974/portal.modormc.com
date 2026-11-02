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
        $key = $this->cacheKey('sales.summary', $filters);
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
        $key = $this->cacheKey('top.products', $filters);
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
        $key = $this->cacheKey('stock.details', $filters);
        return Cache::remember($key, now()->addMinutes(5), function () use ($filters) {
            return $this->repository->getStockDetails($filters);
        });
    }

    public function getTripsDetails(array $filters)
    {
        $key = $this->cacheKey('trips.details', $filters);
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

    private function cacheKey(string $view, array $filters): string
    {
        $plantIds = app(DashboardPlantAccess::class)->query()->orderBy('id')->pluck('id')->all();

        return 'dashboard.access.v2.' . $view . '.' . auth()->id() . '.' . hash('sha256', json_encode([$plantIds, $filters]));
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
