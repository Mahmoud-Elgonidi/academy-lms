<?php

namespace Modules\Platform\Actions\Ai;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Platform\Ai\Engines\EngineAddress;
use Modules\Platform\Ai\Engines\EngineKind;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Ai\AiEngine;
use Modules\Platform\Models\PlatformAdmin;

/**
 * An owner adds an engine or edits one (console › AI engines): its name,
 * kind, address (https unless on a private network, never a password in
 * it), key (kept encrypted, never shown again: blank keeps it, "remove"
 * takes it away), models, time-out, cost and notes. Its kind never changes
 * once saved (routes chose it for what it does). Audited with the engine's
 * name, kind and the fields that changed, never a value of the key.
 */
final class SaveAiEngine
{
    private const MODEL = ['nullable', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:\/\-]*$/', 'max:120'];

    private const COST = ['nullable', 'numeric', 'min:0', 'max:100000'];

    public function __construct(private readonly PlatformAudit $audit) {}

    /** @param  array<string, mixed>  $data  name, kind, base_url, api_key, model_best, model_fast, model_speech, timeout_seconds, cost_*, zero_retention, enabled, notes */
    public function handle(PlatformAdmin $by, ?string $engineId, array $data, bool $clearKey = false): AiEngine
    {
        OwnersOnly::check($by);
        $engine = $engineId !== null ? AiEngine::query()->findOrFail($engineId) : new AiEngine;
        $data['kind'] = $engine->exists ? $engine->kind : ($data['kind'] ?? null);
        $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
        $kinds = array_keys(EngineKind::options());

        $valid = Validator::make($data, [
            'name' => ['required', 'string', 'max:80', Rule::unique('pgsql_platform.ai_engines', 'name')->ignore($engine->id)],
            'kind' => ['required', Rule::in($engine->exists ? [$engine->kind] : $kinds)],
            'base_url' => ['nullable', 'url:https,http', 'max:300', new EngineAddress, Rule::requiredIf(fn () => EngineKind::tryFrom((string) $data['kind'])?->needsUrl() ?? false)],
            'api_key' => ['nullable', 'regex:/^\S+$/', 'max:2000'],
            'model_best' => self::MODEL,
            'model_fast' => self::MODEL,
            'model_speech' => self::MODEL,
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
            'cost_input_per_m' => self::COST,
            'cost_output_per_m' => self::COST,
            'cost_audio_per_min' => self::COST,
            'cost_gpu_per_min' => self::COST,
            'cost_currency' => ['nullable', 'regex:/^[A-Z]{3}$/'],
            'zero_retention' => ['nullable', 'boolean'],
            'enabled' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], __('platform::engines.fields'))->validate();

        $kind = EngineKind::from((string) $valid['kind']);
        $blank = fn (string $k) => ($valid[$k] ?? null) === null || $valid[$k] === '' ? null : $valid[$k];
        $values = [
            'name' => $valid['name'],
            'kind' => $kind->value,
            'base_url' => $blank('base_url') !== null ? rtrim((string) $valid['base_url'], '/') : null,
            'model_best' => $kind->text() ? $blank('model_best') : null,
            'model_fast' => $kind->text() ? $blank('model_fast') : null,
            'model_speech' => $kind->speech() ? $blank('model_speech') : null,
            'timeout_seconds' => $blank('timeout_seconds') !== null ? (int) $valid['timeout_seconds'] : null,
            'cost_input_per_m' => $blank('cost_input_per_m'),
            'cost_output_per_m' => $blank('cost_output_per_m'),
            'cost_audio_per_min' => $blank('cost_audio_per_min'),
            'cost_gpu_per_min' => $blank('cost_gpu_per_min'),
            'cost_currency' => $blank('cost_currency') ?? 'USD',
            'options' => $kind === EngineKind::ElevenLabs ? ['zero_retention' => (bool) ($valid['zero_retention'] ?? false)] : null,
            'enabled' => (bool) ($valid['enabled'] ?? true),
            'notes' => $blank('notes'),
        ];

        return DB::connection('pgsql_platform')->transaction(function () use ($engine, $values, $valid, $clearKey, $by) {
            $before = $engine->exists ? $engine->only(array_keys($values)) : [];
            $engine->fill($values);
            $keyChanged = false;
            if ($clearKey && $engine->hasKey()) {
                $engine->api_key = null;
                $keyChanged = true;
            } elseif (($valid['api_key'] ?? '') !== '' && $valid['api_key'] !== $engine->api_key) {
                $engine->api_key = $valid['api_key'];
                $keyChanged = true;
            }
            $engine->updated_by = $by->id;
            $new = ! $engine->exists;
            $engine->save();

            $changed = array_keys(array_filter($values, fn ($v, string $k) => ($before[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH));
            $this->audit->record(null, 'platform.ai_engine_saved', 'ai_engine', $engine->id, [
                'engine' => $engine->name,
                'kind' => $engine->kind,
                'new' => $new,
                'fields' => array_values(array_merge($new ? [] : $changed, $keyChanged ? ['api_key'] : [])),
            ]);

            return $engine;
        });
    }
}
