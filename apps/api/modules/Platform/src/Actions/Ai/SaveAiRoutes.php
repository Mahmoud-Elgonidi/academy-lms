<?php

namespace Modules\Platform\Actions\Ai;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\Ai\AiEngine;
use Modules\Platform\Models\Ai\AiRoute;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasPlan;
use Modules\SharedKernel\Ai\Capabilities;

/**
 * An owner sets where a capability's requests go (console › AI routing),
 * for one kind of work (text, speech) and scope (default, a plan, one
 * academy): an ordered chain of up to five engines that do that work, each
 * once, each with an optional tier; and optionally a share of requests
 * (1–99%) sent to a second chain to compare. An empty chain removes the
 * route: the next scope holds (in the end, the server's settings). Audited
 * with the capability, the scope and the engines' names.
 */
final class SaveAiRoutes
{
    public const MAX_STEPS = 5;

    public function __construct(
        private readonly PlatformAudit $audit,
        private readonly Capabilities $capabilities,
    ) {}

    /**
     * @param  list<array{engine?: ?string, tier?: ?string}>  $chain  empty: remove the route
     * @param  list<array{engine?: ?string, tier?: ?string}>  $splitChain
     */
    public function handle(PlatformAdmin $by, string $capability, string $kind, string $scope, array $chain, ?int $splitPercent = null, array $splitChain = []): ?AiRoute
    {
        OwnersOnly::check($by);
        $known = $this->capabilities->get($capability);
        if ($known === null || ! in_array($kind, $known->kinds, true)) {
            throw ValidationException::withMessages(['capability' => __('platform::routes.errors.capability')]);
        }
        $academyId = $this->scope($scope);

        $chain = $this->chain('chain', $chain, $kind);
        $split = $splitPercent !== null && $splitPercent > 0;
        if ($split && ($splitPercent < 1 || $splitPercent > 99)) {
            throw ValidationException::withMessages(['split_percent' => __('platform::routes.errors.split_percent')]);
        }
        $splitChain = $split ? $this->chain('split_chain', $splitChain, $kind) : [];
        if ($split && ($chain === [] || $splitChain === [])) {
            throw ValidationException::withMessages(['split_chain' => __('platform::routes.errors.split_chain')]);
        }

        return DB::connection('pgsql_platform')->transaction(function () use ($by, $capability, $kind, $scope, $chain, $split, $splitPercent, $splitChain, $academyId) {
            $route = AiRoute::query()->where(['capability' => $capability, 'kind' => $kind, 'scope' => $scope])->lockForUpdate()->first();
            $names = AiEngine::query()->whereIn('id', array_column([...$chain, ...$splitChain], 'engine'))->pluck('name', 'id');
            $data = [
                'capability' => $capability,
                'kind' => $kind,
                'scope' => str_starts_with($scope, 'academy:') ? 'academy' : $scope,
                'engines' => array_map(fn (array $s) => (string) $names[$s['engine']], $chain),
                'split' => $split ? (int) $splitPercent : 0,
                'split_engines' => array_map(fn (array $s) => (string) $names[$s['engine']], $splitChain),
            ];

            if ($chain === []) {
                $route?->delete();
                $this->audit->record($academyId, 'platform.ai_route_removed', 'ai_route', $route?->id, $data);

                return null;
            }
            $route ??= new AiRoute(['capability' => $capability, 'kind' => $kind, 'scope' => $scope]);
            $route->fill([
                'chain' => $chain,
                'split_percent' => $split ? (int) $splitPercent : null,
                'split_chain' => $split ? $splitChain : null,
                'updated_by' => $by->id,
            ])->save();
            $this->audit->record($academyId, 'platform.ai_route_saved', 'ai_route', $route->id, $data);

            return $route;
        });
    }

    /** The academy a scope names (null for the default or a plan); refuses a plan or academy that is not there. */
    private function scope(string $scope): ?string
    {
        if ($scope === AiRoute::DEFAULT) {
            return null;
        }
        [$type, $id] = array_pad(explode(':', $scope, 2), 2, '');
        $exists = match ($type) {
            'plan' => $id !== '' && SaasPlan::query()->where('code', $id)->exists(),
            'academy' => preg_match('/^[0-9a-f-]{36}$/', $id) === 1 && Academy::query()->whereKey($id)->exists(),
            default => false,
        };
        if (! $exists) {
            throw ValidationException::withMessages(['scope' => __('platform::routes.errors.scope')]);
        }

        return $type === 'academy' ? $id : null;
    }

    /**
     * @param  list<array{engine?: ?string, tier?: ?string}>  $chain
     * @return list<array{engine: string, tier: ?string}>
     */
    private function chain(string $field, array $chain, string $kind): array
    {
        $steps = array_values(array_filter($chain, fn ($s) => is_array($s) && filled($s['engine'] ?? null)));
        if (count($steps) > self::MAX_STEPS) {
            throw ValidationException::withMessages([$field => __('platform::routes.errors.too_many', ['max' => self::MAX_STEPS])]);
        }
        $ids = array_map(fn (array $s) => (string) $s['engine'], $steps);
        if (count(array_unique($ids)) !== count($ids)) {
            throw ValidationException::withMessages([$field => __('platform::routes.errors.twice')]);
        }
        $engines = $ids === [] ? collect() : AiEngine::query()->whereIn('id', array_filter($ids, fn (string $id) => preg_match('/^[0-9a-f-]{36}$/', $id) === 1))->get()->keyBy('id');
        $out = [];
        foreach ($steps as $s) {
            $engine = $engines->get((string) $s['engine']);
            if ($engine === null || ! $engine->kindOf()->serves($kind)) {
                throw ValidationException::withMessages([$field => __('platform::routes.errors.engine', ['kind' => __('platform::routes.kinds.'.$kind)])]);
            }
            $tier = $s['tier'] ?? null;
            $out[] = ['engine' => $engine->id, 'tier' => in_array($tier, ['fast', 'best'], true) ? $tier : null];
        }

        return $out;
    }
}
