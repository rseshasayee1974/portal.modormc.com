<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\DashboardService;
use OpenApi\Attributes as OA;



class DashboardController extends Controller
{
    protected $service;

    public function __construct(DashboardService $service)
    {
        $this->service = $service;
    }

    private function getFilters(Request $request)
    {
        return $request->only(['from_date', 'to_date', 'plant_id', 'type']);
    }

    #[OA\Get(
        path: "/dashboard/sales-summary",
        summary: "Get sales summary (Donut Chart data)",
        tags: ["Dashboard"],
        security: [["bearerAuth" => []]]
    )]
    #[OA\Response(response: 200, description: "Successful operation")]
    public function salesSummary(Request $request)
    {
        $data = $this->service->getSalesSummary($this->getFilters($request));
        return response()->json(['status' => true, 'data' => $data]);
    }

    #[OA\Get(path: "/dashboard/sales-stats", summary: "Get sales statistics (MT, UNIT/CFT, TRIPS)", tags: ["Dashboard"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Successful operation")]
    public function salesStats(Request $request)
    {
        $data = $this->service->getSalesStats($this->getFilters($request));
        return response()->json(['status' => true, 'data' => $data]);
    }

    #[OA\Get(path: "/dashboard/top-products", summary: "Get top selling products", tags: ["Dashboard"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Successful operation")]
    public function topProducts(Request $request)
    {
        $data = $this->service->getTopProducts($this->getFilters($request));
        return response()->json(['status' => true, 'data' => $data]);
    }

    #[OA\Get(path: "/dashboard/top-mix-designs", summary: "Get top 5 mix designs from batches", tags: ["Dashboard"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Successful operation")]
    public function topMixDesigns(Request $request)
    {
        $data = $this->service->getTopMixDesignsFromBatches($this->getFilters($request));
        return response()->json(['status' => true, 'data' => $data]);
    }

    #[OA\Get(path: "/dashboard/sales-details", summary: "Get detailed sales breakdown (Cash vs Credit)", tags: ["Dashboard"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Successful operation")]
    #[OA\Response(response: 401, description: "Unauthorized")]
    public function salesDetails(Request $request)
    {
        $data = $this->service->getSalesDetails($this->getFilters($request));
        return response()->json(['status' => true, 'data' => $data]);
    }

    #[OA\Get(path: "/dashboard/dispatch-sales-amount", summary: "Get dispatch cash and credit sales amounts", tags: ["Dashboard"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Successful operation")]
    #[OA\Response(response: 401, description: "Unauthorized")]
    public function dispatchSalesAmount(Request $request)
    {
        $data = $this->service->getDispatchSalesAmounts($this->getFilters($request));
        return response()->json(['status' => true, 'data' => $data]);
    }

    #[OA\Get(path: "/dashboard/dispatch-batching-summary", summary: "Get dispatch batching summary", tags: ["Dashboard"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Successful operation")]
    #[OA\Response(response: 401, description: "Unauthorized")]
    public function dispatchBatchingSummary(Request $request)
    {
        $data = $this->service->getDispatchBatchingSummary($this->getFilters($request));
        return response()->json(['status' => true, 'data' => $data]);
    }

    #[OA\Get(path: "/dashboard/dispatch-details", summary: "Get truck wise dispatch details", tags: ["Dashboard"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Successful operation")]
    #[OA\Response(response: 401, description: "Unauthorized")]
    public function dispatchDetails(Request $request)
    {
        $data = $this->service->getDispatchDetailsByTruck($this->getFilters($request));
        return response()->json(['status' => true, 'data' => $data]);
    }

    #[OA\Get(path: "/dashboard/stock-details", summary: "Get current stock levels", tags: ["Dashboard"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Successful operation")]
    #[OA\Response(response: 401, description: "Unauthorized")]
    public function stockDetails(Request $request)
    {
        $data = $this->service->getStockDetails($this->getFilters($request));
        return response()->json(['status' => true, 'data' => $data]);
    }

    #[OA\Get(path: "/dashboard/trips-details", summary: "Get trip counts by vehicle", tags: ["Dashboard"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Successful operation")]
    #[OA\Response(response: 401, description: "Unauthorized")]
    public function tripsDetails(Request $request)
    {
        $data = $this->service->getTripsDetails($this->getFilters($request));
        return response()->json(['status' => true, 'data' => $data]);
    }

    #[OA\Get(path: "/dashboard/customer-details", summary: "Get recent customer activities", tags: ["Dashboard"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Successful operation")]
    public function customerDetails(Request $request)
    {
        $data = $this->service->getCustomerDetails($this->getFilters($request));
        return response()->json(['status' => true, 'data' => $data]);
    }

    #[OA\Get(path: "/dashboard/alerts", summary: "Get dashboard alerts", tags: ["Dashboard"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Successful operation")]
    public function alerts(Request $request)
    {
        $data = $this->service->getAlerts($this->getFilters($request));
        return response()->json(['status' => true, 'data' => $data]);
    }

    #[OA\Get(path: "/master/plants", summary: "Get plant list for filtering", tags: ["Master Data"], security: [["bearerAuth" => []]])]
    #[OA\Response(response: 200, description: "Successful operation")]
    public function plants()
    {
        $data = $this->service->getPlants();
        return response()->json(['status' => true, 'data' => $data]);
    }
}
