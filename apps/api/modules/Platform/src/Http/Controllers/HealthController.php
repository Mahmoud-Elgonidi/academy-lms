<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Health\SystemHealth;

/**
 * GET /health for an uptime monitor: each check's status and the worst of
 * them (503 when something fails), with the token from HEALTH_TOKEN as a
 * bearer token or ?token=. Without a token configured there is no such page.
 * No values, names or hints: those are on the console's health page.
 */
final class HealthController
{
    public function __invoke(Request $request, SystemHealth $health): JsonResponse
    {
        $token = (string) config('platform.health.token');
        $given = (string) ($request->bearerToken() ?? $request->query('token', ''));
        abort_if($token === '' || ! hash_equals($token, $given), 404);

        $checks = $health->checks();
        $overall = SystemHealth::overall($checks);

        return response()->json([
            'status' => $overall,
            'checks' => array_map(fn (array $check) => $check['status'], $checks),
            'at' => now()->toIso8601String(),
        ], $overall === 'fail' ? 503 : 200, ['Cache-Control' => 'no-store']);
    }
}
