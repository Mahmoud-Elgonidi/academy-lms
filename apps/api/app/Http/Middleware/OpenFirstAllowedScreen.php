<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\SharedKernel\Authorization\Ability;
use Symfony\Component\HttpFoundation\Response;

/**
 * The back office opens on the live board; staff who do not watch it (sales,
 * the accountant: revenue) land on their own first screen instead of a refusal.
 */
final class OpenFirstAllowedScreen
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->route()?->getName() === 'filament.academy.pages.live'
            && $request->user() !== null && ! Gate::allows(Ability::ViewLiveBoard->value)) {
            $route = match (true) {
                Gate::allows(Ability::ManageLeads->value) => 'filament.academy.pages.leads',
                Gate::allows(Ability::ViewBilling->value) => 'filament.academy.pages.revenue',
                Gate::allows(Ability::FindTeachers->value) => 'filament.academy.pages.find-teacher',
                default => null,
            };
            if ($route !== null) {
                return redirect()->route($route);
            }
        }

        return $next($request);
    }
}
