<?php

namespace Modules\Platform\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Ai\Engines\EngineSpec;
use Modules\Platform\Ai\Engines\SpeechEngine;
use Modules\Platform\Ai\Faults\OpenAiFaults;
use Modules\Platform\Models\AiUsage;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\HeardWord;
use Modules\SharedKernel\Ai\Transcript;
use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceHealth;
use Modules\SharedKernel\Health\ServiceProblem;

/**
 * What a recording said, through any server with OpenAI's
 * /audio/transcriptions (OpenAI's Whisper, faster-whisper-server, a GPU
 * server of the platform's own): the file, the model and the language
 * (ISO 639-1), words with their times (verbose_json). It is never told what
 * the speaker was meant to say. A key is sent only when the engine has one.
 */
final class WhisperSpeechToText implements SpeechEngine
{
    /** ISO 639-2 as the helpers say it, ISO 639-1 as these servers take it. */
    private const LANGUAGES = ['ara' => 'ar', 'eng' => 'en'];

    public function __construct(private readonly AiSlots $slots) {}

    public function hear(EngineSpec $engine, string $capability, AudioClip $clip, string $language): Transcript
    {
        $model = $engine->modelSpeech;
        if ($engine->baseUrl === '' || $model === '') {
            throw new AiFailed('not_configured');
        }
        if ($clip->bytes() === 0) {
            throw new AiFailed('unheard');
        }
        $timeout = $engine->seconds($this->slots->waiting());
        $service = $engine->service;

        $response = $this->slots->hold(function () use ($engine, $clip, $model, $language, $capability, $timeout, $service): Response {
            $file = fopen($clip->path, 'r');
            try {
                $http = Http::acceptJson()->timeout($timeout)->connectTimeout(10);

                return ($engine->key !== '' ? $http->withToken($engine->key) : $http)
                    ->attach('file', $file, $clip->name, ['Content-Type' => $clip->mime])
                    ->post($engine->baseUrl.'/audio/transcriptions', [
                        'model' => $model,
                        'language' => self::LANGUAGES[$language] ?? substr($language, 0, 2),
                        'response_format' => 'verbose_json',
                        'timestamp_granularities[]' => 'word',
                        'temperature' => '0',
                    ]);
            } catch (ConnectionException) {
                AiUsage::note($capability, $engine->provider, $model, [], 'unavailable');
                ServiceHealth::fault($service, ServiceFault::unreachable());
                throw new AiFailed('unavailable', __('shared::ai.failed.unavailable'));
            } finally {
                if (is_resource($file)) {
                    fclose($file);
                }
            }
        });

        if (! $response->successful()) {
            // A recording it could not read says nothing about the server; anything else does.
            $why = OpenAiFaults::of($response->status(), $response->json());
            $unheard = in_array($response->status(), [413, 415, 422], true)
                || $response->status() === 400 && (! $why instanceof ServiceFault || $why->problem === ServiceProblem::Config);
            AiUsage::note($capability, $engine->provider, $model, [], $unheard ? 'unheard' : 'unavailable');
            if (! $unheard) {
                ServiceHealth::fault($service, $why instanceof ServiceFault ? $why : ServiceFault::http($response->status()));
            }
            throw new AiFailed($unheard ? 'unheard' : 'unavailable');
        }

        $transcript = self::read((array) $response->json(), $model);
        AiUsage::note($capability, $engine->provider, $model, ['audio_seconds' => (int) ceil($transcript->seconds)], $transcript->text === '' ? 'unheard' : 'ok');
        ServiceHealth::ok($service);
        if (trim($transcript->text) === '') {
            throw new AiFailed('unheard');
        }

        return $transcript;
    }

    /** @param  array<string, mixed>  $body */
    public static function read(array $body, string $model): Transcript
    {
        $words = [];
        foreach ((array) ($body['words'] ?? []) as $word) {
            $text = is_array($word) ? trim((string) ($word['word'] ?? $word['text'] ?? '')) : '';
            if ($text !== '') {
                $words[] = new HeardWord($text, (float) ($word['start'] ?? 0), (float) ($word['end'] ?? 0));
            }
        }
        $seconds = isset($body['duration']) ? (float) $body['duration'] : ($words === [] ? 0.0 : max(array_map(fn (HeardWord $w) => $w->end, $words)));

        return new Transcript(trim((string) ($body['text'] ?? '')), $words, $seconds, $model);
    }
}
