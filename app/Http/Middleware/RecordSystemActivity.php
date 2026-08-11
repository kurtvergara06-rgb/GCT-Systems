<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordSystemActivity
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        $response = $next($request);

        if (
            $actor !== null &&
            $response->getStatusCode() >= 200 &&
            $response->getStatusCode() < 400
        ) {
            try {
                $this->activityLogService->recordRequest($actor, $request);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $response;
    }
}
