<?php

namespace App\Http\Controllers\Api;

use App\DTOs\NotificationDTO;
use App\Exceptions\RateLimitExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Throwable;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    public function store(StoreNotificationRequest $request): JsonResponse
    {
        try {
            $dto          = NotificationDTO::fromRequest($request);
            $notification = $this->service->send($dto);

            return (new NotificationResource($notification))
                ->response()
                ->setStatusCode(201);
        } catch (RateLimitExceededException $e) {
            return response()->json([
                'message'     => $e->getMessage(),
                'retry_after' => $e->retryAfter,
            ], 429);
        } catch (Throwable $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
}
