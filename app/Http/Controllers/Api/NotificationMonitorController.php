<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationMonitorController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'user_id', 'channel', 'from_date', 'to_date']);
        $filters['per_page'] = $request->integer('per_page', 15);

        $paginated = $this->service->getRecent($filters);

        return NotificationResource::collection($paginated)->response();
    }

    public function summary(Request $request): JsonResponse
    {
        $userId  = $request->query('user_id');
        $summary = $this->service->getSummary($userId ?: null);

        return response()->json(['data' => $summary, 'cached' => true]);
    }
}
