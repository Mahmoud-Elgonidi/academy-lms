<?php

namespace Modules\Platform\Ai\Gateway;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Modules\Platform\Ai\AiProvider;
use Modules\Platform\Ai\Engines\EngineSpec;
use Modules\Platform\Models\Ai\AiEngine;
use Modules\Platform\Models\Ai\AiRoute;
use Modules\Platform\Plans\PlatformAcademyPlan;
use Modules\SharedKernel\Tenancy\TenantContext;
use Throwable;

/**
 * Where a capability's request goes (console › AI routing): the academy's own
 * route, else its plan's, else the default, each only while it has an
 * enabled engine that does this kind of work; with none, the server's
 * settings as before engines (AI_DRIVER and its key; ElevenLabs for
 * speech). A route with a share for a second chain sends that share there,
 * the same way for the same request (Split).
 */
final class Routes
{
    /** The id the split reads for this request, kept in Laravel's Context so a queued job carries it on. */
    public const REQUEST = 'ai_request';

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly PlatformAcademyPlan $plan,
    ) {}

    /** The chain for this request (null: nothing set up for it anywhere). */
    public function resolve(string $capability, string $kind, ?string $requestId = null): ?ResolvedRoute
    {
        $academyId = $this->tenants->id();
        $scopes = array_values(array_filter([
            $academyId !== null ? AiRoute::academyScope($academyId) : null,
            ($code = $this->plan->terms()?->code) !== null ? AiRoute::planScope($code) : null,
            AiRoute::DEFAULT,
        ]));

        try {
            $routes = AiRoute::query()->where('capability', $capability)->where('kind', $kind)->whereIn('scope', $scopes)->get()->keyBy('scope');
        } catch (Throwable) {
            $routes = collect();     // no table yet: the server's settings
        }
        if ($routes->isNotEmpty()) {
            $engines = $this->engines($routes->flatMap(fn (AiRoute $r) => [...self::ids($r->chain), ...self::ids($r->split_chain ?? [])])->unique()->all());
            foreach ($scopes as $scope) {
                $route = $routes->get($scope);
                if ($route === null) {
                    continue;
                }
                $main = $this->steps($route->chain, $engines, $kind);
                if ($route->split_percent !== null && $route->split_chain !== null
                    && Split::takesSecond($requestId ?? self::requestId(), $capability, (int) $route->split_percent)) {
                    $second = $this->steps($route->split_chain, $engines, $kind);
                    if ($second !== []) {
                        return new ResolvedRoute($second, $scope, true);
                    }
                }
                if ($main !== []) {
                    return new ResolvedRoute($main, $scope);
                }
            }
        }

        $legacy = $kind === 'speech' ? EngineSpec::legacySpeech() : EngineSpec::legacyText();

        return $legacy !== null && $this->legacyReady($legacy, $kind) ? new ResolvedRoute([new Step($legacy)], 'settings') : null;
    }

    /** It can be asked now: a route with a ready engine, or the server's settings. */
    public function usable(string $capability, string $kind): bool
    {
        $route = $this->resolve($capability, $kind);

        return $route !== null && array_filter($route->steps, fn (Step $s) => $s->engine->ready($kind)) !== [];
    }

    /** The steps of a chain the console names (the comparison), with the engines as saved. */
    public function chain(array $chain, string $kind): array
    {
        return $this->steps($chain, $this->engines(self::ids($chain)), $kind, keepUnready: true);
    }

    public static function requestId(): string
    {
        $id = Context::get(self::REQUEST);
        if (! is_string($id) || $id === '') {
            $id = (string) Str::uuid();
            Context::add(self::REQUEST, $id);
        }

        return $id;
    }

    /**
     * @param  list<array{engine?: string, tier?: ?string}>  $chain
     * @param  array<string, AiEngine>  $engines
     * @return list<Step>
     */
    private function steps(array $chain, array $engines, string $kind, bool $keepUnready = false): array
    {
        $steps = [];
        foreach ($chain as $link) {
            $engine = $engines[(string) ($link['engine'] ?? '')] ?? null;
            if ($engine === null || ! $engine->enabled || ! $engine->kindOf()->serves($kind)) {
                continue;
            }
            $spec = EngineSpec::of($engine);
            if (! $keepUnready && ! $spec->ready($kind)) {
                continue;
            }
            $tier = $link['tier'] ?? null;
            $steps[] = new Step($spec, in_array($tier, ['fast', 'best'], true) ? $tier : null);
        }

        return $steps;
    }

    /** @return array<string, AiEngine> */
    private function engines(array $ids): array
    {
        $ids = array_values(array_filter($ids, fn ($id) => is_string($id) && Str::isUuid($id)));

        return $ids === [] ? [] : AiEngine::query()->whereIn('id', $ids)->get()->keyBy('id')->all();
    }

    /** @return list<string> */
    private static function ids(array $chain): array
    {
        return array_values(array_filter(array_map(fn ($link) => is_array($link) ? (string) ($link['engine'] ?? '') : '', $chain)));
    }

    /** The server's settings are complete enough: the provider in AI_DRIVER configured, or the speech key set. */
    private function legacyReady(EngineSpec $legacy, string $kind): bool
    {
        return $kind === 'speech' ? $legacy->key !== '' : (AiProvider::current()?->configured() ?? false);
    }
}
