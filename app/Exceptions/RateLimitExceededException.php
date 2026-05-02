<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class RateLimitExceededException extends Exception
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct("Rate limit exceeded. Try again in {$retryAfter} seconds.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message'     => $this->getMessage(),
            'retry_after' => $this->retryAfter,
        ], 429);
    }
}
