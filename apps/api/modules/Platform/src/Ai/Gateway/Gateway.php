<?php

namespace Modules\Platform\Ai\Gateway;

use Modules\Platform\Ai\AiSlots;
use Modules\Platform\Ai\Engines\EngineSpec;
use Modules\Platform\Ai\PlatformAcademyAi;
use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\Capability;
use Modules\SharedKernel\Ai\Transcript;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The one way to a model for the whole system (docs/architecture/02,
 * section 22). Modules ask SharedKernel's LanguageModel or SpeechToText
 * with their capability's key; both are this gateway. It refuses a key no
 * module registered, says no while the helpers are off (AI_DRIVER=off) or,
 * in an academy, while AcademyAi does (the academy's switch, its plan, the
 * month's uses, today's cap), answers from the demo while AI_DRIVER=demo
 * (never in production), and otherwise asks the engines of the capability's
 * route in turn (Routes: the academy's, its plan's, the default, else the
 * server's settings): on to the next on an error, a time-out or an
 * overload (the engine is unavailable or not set up), never on a refusal or
 * an answer that cannot be used. While a person waits it starts no new try
 * once AI_TIMEOUT has passed. Each try is a line in ai_usage (the adapter
 * notes it, with the engine, how long it took and what it fell back from:
 * CallContext); nothing that was asked or answered is kept or logged. The
 * games designer is not a capability: it stays on Claude, outside here.
 */
final class Gateway
{
    /** The reasons that say the engine, not the request: the next engine may answer. */
    public const FALLS_BACK = ['unavailable', 'not_configured'];

    public function __construct(
        private readonly Capabilities $capabilities,
        private readonly Routes $routes,
        private readonly CallContext $context,
        private readonly AiSlots $slots,
        private readonly TenantContext $tenants,
    ) {}

    /** @throws AiFailed */
    public function text(string $capability, AiPrompt $prompt): AiAnswer
    {
        $route = $this->route($this->capabilities->require($capability), 'text');

        return $this->down($route, fn (Step $step) => app($step->engine->kind->textAdapter())
            ->ask($step->engine, $capability, $prompt, $step->tier ?? $prompt->tier));
    }

    /** @throws AiFailed */
    public function speech(string $capability, AudioClip $clip, string $language): Transcript
    {
        $route = $this->route($this->capabilities->require($capability), 'speech');

        return $this->down($route, fn (Step $step) => app($step->engine->kind->speechAdapter())
            ->hear($step->engine, $capability, $clip, $language));
    }

    /**
     * Down a chain the console names (the comparison): no academy, no route,
     * nothing noted for any academy.
     *
     * @param  list<Step>  $steps
     * @return array{answer: AiAnswer|Transcript|null, engine: ?string, engine_id: ?string, failed: ?string, ms: int}
     */
    public function tryChain(array $steps, string $kind, string $capability, AiPrompt|AudioClip $input, string $language = 'ara'): array
    {
        $started = hrtime(true);
        $last = null;
        foreach ($steps as $step) {
            try {
                $answer = $kind === 'speech'
                    ? app($step->engine->kind->speechAdapter())->hear($step->engine, $capability, $input, $language)
                    : app($step->engine->kind->textAdapter())->ask($step->engine, $capability, $input, $step->tier ?? $input->tier);

                return ['answer' => $answer, 'engine' => $step->engine->name, 'engine_id' => $step->engine->id, 'failed' => null, 'ms' => (int) ((hrtime(true) - $started) / 1e6)];
            } catch (AiFailed $e) {
                $last = $e->reason;
                if (! in_array($e->reason, self::FALLS_BACK, true)) {
                    break;
                }
            }
        }

        return ['answer' => null, 'engine' => null, 'engine_id' => null, 'failed' => $last ?? 'not_configured', 'ms' => (int) ((hrtime(true) - $started) / 1e6)];
    }

    private function route(Capability $capability, string $kind): ResolvedRoute
    {
        if (! in_array($kind, $capability->kinds, true)) {
            throw new AiFailed('not_configured');
        }
        if ((string) config('platform.ai.driver') === 'off') {
            throw new AiFailed('off');
        }
        if ($this->tenants->id() !== null) {
            $why = app(PlatformAcademyAi::class)->why($capability->key);
            if ($why !== null) {
                throw new AiFailed($why);
            }
        }
        if (PlatformAcademyAi::demo()) {
            return new ResolvedRoute([new Step(EngineSpec::demo())], 'demo');
        }

        return $this->routes->resolve($capability->key, $kind) ?? throw new AiFailed('not_configured');
    }

    /**
     * @template T
     *
     * @param  callable(Step): T  $ask
     * @return T
     */
    private function down(ResolvedRoute $route, callable $ask): mixed
    {
        $started = hrtime(true);
        $deadline = (int) config('platform.ai.timeout', 40) * 1000;
        $waiting = $this->slots->waiting();
        $from = null;
        $last = null;
        foreach ($route->steps as $i => $step) {
            if ($i > 0 && $waiting && (hrtime(true) - $started) / 1e6 >= $deadline) {
                break;
            }
            $this->context->begin($step->engine->id, $from);
            try {
                return $ask($step);
            } catch (AiFailed $e) {
                if (! in_array($e->reason, self::FALLS_BACK, true)) {
                    throw $e;
                }
                $last = $e;
                $from = $step->engine->id;
            } finally {
                $this->context->end();
            }
        }

        throw $last ?? new AiFailed('not_configured');
    }
}
