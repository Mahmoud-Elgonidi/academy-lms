<?php

namespace Modules\SharedKernel\Http;

use Closure;
use Illuminate\Http\Request;
use Modules\SharedKernel\Time\ViewerTimezone;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads the device timezone from X-Timezone and reports the timezone the
 * response's times were written in, so a client can tell when it differs.
 */
final class ResolveViewerTimezone
{
    public function __construct(private readonly ViewerTimezone $viewer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->viewer->reset();
        $this->viewer->setDevice($request->headers->get(ViewerTimezone::HEADER));

        $response = $next($request);

        // Worked out after the route ran, when the user (and their profile zone) is known.
        $response->headers->set(ViewerTimezone::HEADER, $this->viewer->effective());
        $response->setVary(array_merge($response->getVary(), [ViewerTimezone::HEADER]));

        return $response;
    }
}
