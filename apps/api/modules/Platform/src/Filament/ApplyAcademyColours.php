<?php

namespace Modules\Platform\Filament;

use Closure;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Registers the current academy's palette with Filament, after the academy is resolved. */
final class ApplyAcademyColours
{
    public function handle(Request $request, Closure $next): Response
    {
        $colours = PlatformPlugin::colours();
        if ($colours !== []) {
            FilamentColor::register($colours);
        }

        return $next($request);
    }
}
