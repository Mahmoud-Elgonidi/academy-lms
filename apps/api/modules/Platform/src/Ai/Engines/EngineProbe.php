<?php

namespace Modules\Platform\Ai\Engines;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Ai\AiProbe;
use Modules\Platform\Ai\Faults\ElevenLabsFaults;
use Modules\Platform\Ai\Faults\OpenAiFaults;
use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceHealth;

/**
 * "Try it" for an engine (console › AI engines, and the daily
 * `platform:services`): requests that change nothing there and hold no
 * one's data. Words: one tiny request to each of its models, as AiProbe
 * asks (the answer's format sent as the helpers send it). Speech:
 * ElevenLabs' plan, or a Whisper-compatible server's list of models. It
 * says in our words whether the engine took the key and, when not, why and
 * what to do (Health\Faults: the provider's code, never its words or the
 * key). Only a saved engine's try is written to ServiceHealth (never what is
 * typed and not saved).
 */
final class EngineProbe
{
    private const TIMEOUT = 10;

    public function __construct(private readonly AiProbe $probe) {}

    /** @return list<array{level: string, text: string}> */
    public function try(EngineSpec $engine, bool $record = false): array
    {
        $lines = [];
        $say = function (string $level, string $text) use (&$lines): void {
            $lines[] = ['level' => $level, 'text' => $text];
        };
        $name = $engine->name;

        if ($engine->kind === EngineKind::Demo) {
            return [['level' => 'info', 'text' => __('platform::engines.try.demo')]];
        }
        if ($engine->kind->needsKey() && $engine->key === '') {
            return [['level' => 'fail', 'text' => __('platform::engines.try.no_key', ['engine' => $name])]];
        }
        if ($engine->baseUrl === '') {
            return [['level' => 'fail', 'text' => __('platform::engines.try.no_url', ['engine' => $name])]];
        }

        $faults = [];
        if ($engine->kind->text()) {
            $models = array_values(array_unique(array_filter([$engine->modelBest, $engine->modelFast])));
            if ($models === []) {
                $say('fail', __('platform::engines.try.no_model', ['engine' => $name]));
            }
            foreach ($models as $model) {
                $fault = $this->probe->probe($engine->kind->provider(), $model, $engine->key, $engine->baseUrl);
                if ($fault === null) {
                    $say('ok', __('platform::engines.try.model_ok', ['engine' => $name, 'model' => $model]));
                } else {
                    $faults[] = [$fault, $model];
                    $say('fail', $this->problem($fault, $name, $model));
                }
            }
        } else {
            $fault = $this->hearing($engine);
            if ($fault === null) {
                $say('ok', __('platform::engines.try.speech_ok', ['engine' => $name, 'model' => $engine->modelSpeech ?: '-']));
            } else {
                $faults[] = [$fault, ''];
                $say('fail', $this->problem($fault, $name, ''));
            }
        }

        if ($record && $engine->service !== '') {
            $faults === []
                ? ServiceHealth::ok($engine->service, via: 'check')
                : ServiceHealth::fault($engine->service, $faults[0][0], $faults[0][1], via: 'check');
        }

        return $lines;
    }

    private function hearing(EngineSpec $engine): ?ServiceFault
    {
        try {
            if ($engine->kind === EngineKind::ElevenLabs) {
                $answer = Http::withHeaders(['xi-api-key' => $engine->key])->acceptJson()->timeout(self::TIMEOUT)->get($engine->baseUrl.'/v1/user/subscription');
                // A key limited to speech to text may not read the plan: it was taken all the same.
                if ($answer->successful() || data_get($answer->json(), 'detail.status') === 'missing_permissions') {
                    return null;
                }

                return ElevenLabsFaults::of($answer->status(), $answer->json()) ?? ServiceFault::http($answer->status());
            }
            $http = Http::acceptJson()->timeout(self::TIMEOUT);
            $answer = ($engine->key !== '' ? $http->withToken($engine->key) : $http)->get($engine->baseUrl.'/models');
            if ($answer->successful()) {
                return null;
            }
            $fault = OpenAiFaults::of($answer->status(), $answer->json());

            return $fault instanceof ServiceFault ? $fault : ServiceFault::http($answer->status());
        } catch (ConnectionException) {
            return ServiceFault::unreachable();
        }
    }

    private function problem(ServiceFault $fault, string $name, string $model): string
    {
        $what = __('platform::services.problems.'.$fault->problem->value, ['service' => $name]);
        $todo = __('platform::services.todo.'.$fault->problem->value, ['service' => $name]);
        $code = $fault->code !== '' ? ' ('.$fault->code.')' : '';

        return trim(($model !== '' ? __('platform::engines.try.model', ['model' => $model]).' ' : '').$what.$code.' '.$todo);
    }
}
