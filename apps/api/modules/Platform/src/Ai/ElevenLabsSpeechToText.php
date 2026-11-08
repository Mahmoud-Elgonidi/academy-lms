<?php

namespace Modules\Platform\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Ai\Engines\EngineSpec;
use Modules\Platform\Ai\Engines\SpeechEngine;
use Modules\Platform\Ai\Faults\ElevenLabsFaults;
use Modules\Platform\Models\AiUsage;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\Capabilities;
use Modules\SharedKernel\Ai\HeardWord;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Ai\Transcript;
use Modules\SharedKernel\Health\ServiceFault;
use Modules\SharedKernel\Health\ServiceHealth;

/**
 * What a recording said, through ElevenLabs' speech to text (as
 * tools/course/lib/voice.mjs hears the course's narrator): the file, the
 * model and the language, words with their times, and, on a contract that
 * allows it, a request to keep nothing (enable_logging=false). It is never
 * told what the speaker was meant to say. The key is the platform's
 * (ELEVENLABS_API_KEY), sent as a header, never in a URL or a log.
 */
final class ElevenLabsSpeechToText implements SpeechEngine, SpeechToText
{
    public function __construct(private readonly AiSlots $slots) {}

    public function transcribe(AiFeature|string $capability, AudioClip $clip, string $language = 'ara'): Transcript
    {
        return $this->hear(EngineSpec::legacySpeech(), Capabilities::key($capability), $clip, $language);
    }

    public function hear(EngineSpec $engine, string $capability, AudioClip $clip, string $language): Transcript
    {
        $feature = $capability;
        $key = $engine->key;
        if ($key === '' || $engine->modelSpeech === '') {
            throw new AiFailed('not_configured');
        }
        if ($clip->bytes() === 0) {
            throw new AiFailed('unheard');
        }

        $model = $engine->modelSpeech;
        $service = $engine->service;
        $query = ($engine->options['zero_retention'] ?? false) ? '?enable_logging=false' : '';
        $timeout = $engine->seconds($this->slots->waiting());
        $response = $this->slots->hold(function () use ($engine, $key, $clip, $model, $language, $query, $feature, $service, $timeout): Response {
            $file = fopen($clip->path, 'r');
            try {
                return Http::baseUrl($engine->baseUrl.'/v1')
                    ->withHeaders(['xi-api-key' => $key])
                    ->timeout($timeout)
                    ->connectTimeout(10)
                    ->attach('file', $file, $clip->name, ['Content-Type' => $clip->mime])
                    ->post('/speech-to-text'.$query, [
                        'model_id' => $model,
                        'language_code' => $language,
                        'timestamps_granularity' => 'word',
                        'tag_audio_events' => 'false',
                    ]);
            } catch (ConnectionException $e) {
                AiUsage::note($feature, $engine->provider, $model, [], 'unavailable');
                ServiceHealth::fault($service, ServiceFault::unreachable());
                throw new AiFailed('unavailable', __('shared::ai.failed.unavailable'));
            } finally {
                if (is_resource($file)) {
                    fclose($file);
                }
            }
        });

        if (! $response->successful()) {
            // A recording it could not hear says nothing about ElevenLabs; anything else does.
            $fault = ElevenLabsFaults::of($response->status(), $response->json());
            AiUsage::note($feature, $engine->provider, $model, [], $fault === null ? 'unheard' : 'unavailable');
            if ($fault !== null) {
                ServiceHealth::fault($service, $fault);
            }
            throw new AiFailed($fault === null ? 'unheard' : 'unavailable');
        }

        $transcript = self::read((array) $response->json(), $model);
        AiUsage::note($feature, $engine->provider, $model, ['audio_seconds' => (int) ceil($transcript->seconds)], $transcript->text === '' ? 'unheard' : 'ok');
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
            if (! is_array($word) || ($word['type'] ?? 'word') !== 'word' || trim((string) ($word['text'] ?? '')) === '') {
                continue;
            }
            $words[] = new HeardWord(trim((string) $word['text']), (float) ($word['start'] ?? 0), (float) ($word['end'] ?? 0));
        }
        $seconds = $words === [] ? 0.0 : max(array_map(fn (HeardWord $w) => $w->end, $words));

        return new Transcript(trim((string) ($body['text'] ?? '')), $words, $seconds, $model);
    }
}
