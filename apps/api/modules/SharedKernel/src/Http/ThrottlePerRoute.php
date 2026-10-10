<?php

namespace Modules\SharedKernel\Http;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * `throttle:N,M` with a count of its own for each route. Laravel keys an
 * unnamed limit by the person (or the address) alone, so every door with one
 * would share a single count: a family that sent a proof, asked for a copy
 * and linked Google within a minute would find the tightest door shut
 * without having used it. Named limiters (api, links) keep their own keys.
 */
final class ThrottlePerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        $door = $route === null ? '' : ($route->getName() ?? implode('|', $route->methods()).' '.$route->uri());

        return sha1($door.'|'.parent::resolveRequestSignature($request));
    }
}
