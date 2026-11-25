<?php
/*
Author: ragul-onemodo
Created: 2026-11-25 11:23:00 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers;

use App\Services\WebNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebNotificationController extends Controller
{
    public function __construct(private WebNotificationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $entityId = session('active_entity_id') ? (int) session('active_entity_id') : null;
        $plantId = session('active_plant_id') ? (int) session('active_plant_id') : null;

        return response()->json($this->service->feed($user, $entityId, $plantId));
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $plantId = session('active_plant_id') ? (int) session('active_plant_id') : null;
        $this->service->markAsRead($request->user(), $plantId, $id);

        return response()->json(['success' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $entityId = session('active_entity_id') ? (int) session('active_entity_id') : null;
        $plantId = session('active_plant_id') ? (int) session('active_plant_id') : null;
        $this->service->markAllAsRead($request->user(), $entityId, $plantId);

        return response()->json(['success' => true]);
    }
}
