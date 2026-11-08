<?php

namespace Modules\Platform\Actions\Ai;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Platform\Ai\AiProvider;
use Modules\Platform\Ai\Engines\EngineKind;
use Modules\Platform\Ai\Engines\EngineSpec;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Models\Ai\AiEngine;
use Modules\Platform\Models\Ai\AiRoute;
use Modules\Platform\Models\PlatformAdmin;
use Modules\SharedKernel\Ai\Capabilities;

/**
 * "Import the current settings" (console › AI engines, while there are no
 * engines): each provider the server's settings have a key or an address
 * for (Platform settings or .env) becomes an engine with that key and its
 * models, ElevenLabs too, and every capability gets a default route to the
 * provider in AI_DRIVER (and ElevenLabs for what it hears), so nothing an
 * academy sees changes. Audited with the engines' names.
 */
final class ImportAiEngines
{
    public function __construct(
        private readonly PlatformAudit $audit,
        private readonly Capabilities $capabilities,
    ) {}

    /** @return list<AiEngine> */
    public function handle(PlatformAdmin $by): array
    {
        OwnersOnly::check($by);
        if (AiEngine::query()->exists()) {
            throw ValidationException::withMessages(['engines' => __('platform::engines.import.not_empty')]);
        }

        return DB::connection('pgsql_platform')->transaction(function () use ($by) {
            $made = [];
            $text = null;
            foreach (AiProvider::cases() as $provider) {
                if (! $provider->hasCredentials()) {
                    continue;
                }
                $spec = EngineSpec::legacyText($provider);
                $made[] = $engine = AiEngine::query()->create([
                    'name' => $provider->label(),
                    'kind' => $spec->kind->value,
                    'base_url' => $spec->baseUrl !== '' && $spec->baseUrl !== $spec->kind->defaultBase() ? $spec->baseUrl : null,
                    'api_key' => $spec->key !== '' ? $spec->key : null,
                    'model_best' => $spec->modelBest ?: null,
                    'model_fast' => $spec->modelFast !== $spec->modelBest ? ($spec->modelFast ?: null) : null,
                    'enabled' => true,
                    'updated_by' => $by->id,
                ]);
                if ($provider === AiProvider::current() && $provider->configured()) {
                    $text = $engine;
                }
            }
            $speech = null;
            $ears = EngineSpec::legacySpeech();
            if ($ears->key !== '') {
                $made[] = $speech = AiEngine::query()->create([
                    'name' => __('platform::services.names.elevenlabs'),
                    'kind' => EngineKind::ElevenLabs->value,
                    'base_url' => $ears->baseUrl !== EngineKind::ElevenLabs->defaultBase() ? $ears->baseUrl : null,
                    'api_key' => $ears->key,
                    'model_speech' => $ears->modelSpeech ?: null,
                    'options' => ['zero_retention' => (bool) ($ears->options['zero_retention'] ?? false)],
                    'enabled' => true,
                    'updated_by' => $by->id,
                ]);
            }

            foreach ($this->capabilities->all() as $capability) {
                foreach ($capability->kinds as $kind) {
                    $engine = $kind === 'speech' ? $speech : $text;
                    if ($engine !== null) {
                        AiRoute::query()->create(['capability' => $capability->key, 'kind' => $kind, 'scope' => AiRoute::DEFAULT,
                            'chain' => [['engine' => $engine->id, 'tier' => null]], 'updated_by' => $by->id]);
                    }
                }
            }
            $this->audit->record(null, 'platform.ai_engines_imported', 'ai_engine', null, ['engines' => array_map(fn (AiEngine $e) => $e->name, $made)]);

            return $made;
        });
    }
}
