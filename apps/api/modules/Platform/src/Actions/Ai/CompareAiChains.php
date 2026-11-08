<?php

namespace Modules\Platform\Actions\Ai;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Ai\Gateway\Gateway;
use Modules\Platform\Ai\Gateway\Routes;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Ai\AiEngine;
use Modules\Platform\Models\Ai\AiTestCase;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\Transcript;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * "Compare" (console › AI routing): an owner's saved test inputs for one
 * capability, each sent down two chains of engines, and what each answered,
 * how long it took and what it would cost by the engine's formula, side by
 * side. Outside any academy: nothing is noted for an academy, nothing is
 * kept but the audit line (the capability, the engines' names and how many
 * inputs). At most MAX_CASES inputs a run.
 */
final class CompareAiChains
{
    public const MAX_CASES = 10;

    private const SHOWN = 4000;

    public function __construct(
        private readonly Gateway $gateway,
        private readonly Routes $routes,
        private readonly PlatformAudit $audit,
        private readonly Capabilities $capabilities,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @param  list<array{engine?: ?string, tier?: ?string}>  $a
     * @param  list<array{engine?: ?string, tier?: ?string}>  $b
     * @param  list<string>  $caseIds
     * @return list<array{case: string, a: array<string, mixed>, b: array<string, mixed>}>
     */
    public function handle(PlatformAdmin $by, string $capability, string $kind, array $a, array $b, array $caseIds): array
    {
        OwnersOnly::check($by);
        $known = $this->capabilities->get($capability);
        if ($known === null || ! in_array($kind, $known->kinds, true)) {
            throw ValidationException::withMessages(['capability' => __('platform::routes.errors.capability')]);
        }
        $stepsA = $this->routes->chain(array_values(array_filter($a, fn ($s) => filled($s['engine'] ?? null))), $kind);
        $stepsB = $this->routes->chain(array_values(array_filter($b, fn ($s) => filled($s['engine'] ?? null))), $kind);
        if ($stepsA === [] || $stepsB === []) {
            throw ValidationException::withMessages(['chains' => __('platform::routes.compare.need_chains')]);
        }
        $cases = AiTestCase::query()->where('capability', $capability)->where('kind', $kind)->whereIn('id', $caseIds)->orderBy('title')->limit(self::MAX_CASES)->get();
        if ($cases->isEmpty()) {
            throw ValidationException::withMessages(['cases' => __('platform::routes.compare.need_cases')]);
        }
        if ($this->tenants->id() !== null) {
            throw ValidationException::withMessages(['cases' => __('platform::routes.compare.no_academy')]);
        }

        $costs = AiEngine::query()->whereIn('id', array_filter(array_map(fn ($s) => $s->engine->id, [...$stepsA, ...$stepsB])))->get()->keyBy('id');
        $rows = [];
        foreach ($cases as $case) {
            $input = $kind === 'speech'
                ? new AudioClip(Storage::disk(AiTestCase::DISK)->path((string) $case->audio_path), (string) $case->audio_mime, basename((string) $case->audio_path))
                : new AiPrompt((string) $case->system, (string) $case->prompt, $case->schema ?: SaveAiTestCase::PLAIN, $known->tier, 'low', 4000);
            $rows[] = [
                'case' => $case->title,
                'a' => $this->shown($this->gateway->tryChain($stepsA, $kind, $capability, $input, $case->language), $costs),
                'b' => $this->shown($this->gateway->tryChain($stepsB, $kind, $capability, $input, $case->language), $costs),
            ];
        }
        $names = fn (array $steps) => array_map(fn ($s) => $s->engine->name, $steps);
        $this->audit->record(null, 'platform.ai_comparison_run', 'ai_route', null, [
            'capability' => $capability, 'kind' => $kind, 'cases' => count($rows), 'first' => $names($stepsA), 'second' => $names($stepsB),
        ]);

        return $rows;
    }

    /**
     * @param  array{answer: AiAnswer|Transcript|null, engine: ?string, engine_id: ?string, failed: ?string, ms: int}  $run
     * @return array{engine: ?string, ms: int, failed: ?string, text: string, model: string, usage: string, cost: ?string}
     */
    private function shown(array $run, $costs): array
    {
        $answer = $run['answer'];
        $engine = $run['engine_id'] !== null ? $costs->get($run['engine_id']) : null;
        $text = match (true) {
            // One string answer reads as words; anything else as its JSON.
            $answer instanceof AiAnswer && count($answer->data) === 1 && is_string(array_values($answer->data)[0]) => (string) array_values($answer->data)[0],
            $answer instanceof AiAnswer => (string) json_encode($answer->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            $answer instanceof Transcript => $answer->text,
            default => '',
        };
        $usage = $answer instanceof AiAnswer ? $answer->usage : [];
        $seconds = $answer instanceof Transcript ? $answer->seconds : 0.0;
        $cost = $engine === null ? null : self::cost($engine, (int) ($usage['input'] ?? 0) + (int) ($usage['cache_write'] ?? 0) + (int) ($usage['cache_read'] ?? 0), (int) ($usage['output'] ?? 0), $seconds, $run['ms']);

        return [
            'engine' => $run['engine'],
            'ms' => $run['ms'],
            'failed' => $run['failed'] !== null ? __('shared::ai.failed.'.$run['failed']) : null,
            'text' => mb_strimwidth($text, 0, self::SHOWN, '…'),
            'model' => $answer?->model ?? '',
            'usage' => $answer instanceof AiAnswer ? __('platform::routes.compare.tokens', ['in' => (int) ($usage['input'] ?? 0), 'out' => (int) ($usage['output'] ?? 0)])
                : ($answer instanceof Transcript ? __('platform::routes.compare.seconds', ['seconds' => round($seconds, 1)]) : ''),
            'cost' => $cost,
        ];
    }

    /** What a request cost by the engine's formula (tokens, audio minutes, or the minutes a server of one's own worked); null with no formula. */
    public static function cost(AiEngine $engine, int $input, int $output, float $seconds, int $ms): ?string
    {
        $parts = [
            $engine->cost_input_per_m !== null ? $input / 1e6 * (float) $engine->cost_input_per_m : null,
            $engine->cost_output_per_m !== null ? $output / 1e6 * (float) $engine->cost_output_per_m : null,
            $engine->cost_audio_per_min !== null ? $seconds / 60 * (float) $engine->cost_audio_per_min : null,
            $engine->cost_gpu_per_min !== null ? $ms / 60000 * (float) $engine->cost_gpu_per_min : null,
        ];
        $known = array_filter($parts, fn ($p) => $p !== null);

        return $known === [] ? null : number_format(array_sum($known), 5).' '.$engine->cost_currency;
    }
}
