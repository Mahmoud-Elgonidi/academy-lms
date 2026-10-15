<?php

namespace Modules\Platform\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Platform\Models\AiUsage;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\HeardWord;
use Modules\SharedKernel\Ai\SpeechToText;
use Modules\SharedKernel\Ai\Transcript;

/**
 * What a recording said, through ElevenLabs' speech to text (as
 * tools/course/lib/voice.mjs hears the course's narrator): the file, the
 * model and the language, words with their times, and, on a contract that
 * allows it, a request to keep nothing (enable_logging=false). It is never
 * told what the speaker was meant to say. The key is the platform's
 * (ELEVENLABS_API_KEY), sent as a header, never in a URL or a log.
 */
final class ElevenLabsSpeechToText implements SpeechToText
{
    public function __construct(private readonly AiSlots $slots) {}

    public function transcribe(AiFeature $feature, AudioClip $clip, string $language = 'ara'): Transcript
    {
        $config = config('platform.ai.speech');
        $key = (string) ($config['key'] ?? '');
        if ($key === '') {
            throw new AiFailed('not_configured');
        }
        if ($clip->bytes() === 0) {
            throw new AiFailed('unheard');
        }

        $model = (string) $config['model'];
        $query = ($config['zero_retention'] ?? false) ? '?enable_logging=false' : '';
        $response = $this->slots->hold(function () use ($config, $key, $clip, $model, $language, $query, $feature): Response {
            $file = fopen($clip->path, 'r');
            try {
                return Http::baseUrl(rtrim((string) $config['base_url'], '/').'/v1')
                    ->withHeaders(['xi-api-key' => $key])
                    ->timeout((int) $config['timeout'])
                    ->connectTimeout(10)
                    ->attach('file', $file, $clip->name, ['Content-Type' => $clip->mime])
                    ->post('/speech-to-text'.$query, [
                        'model_id' => $model,
                        'language_code' => $language,
                        'timestamps_granularity' => 'word',
                        'tag_audio_events' => 'false',
                    ]);
            } catch (ConnectionException $e) {
                AiUsage::note($feature, 'elevenlabs', $model, [], 'unavailable');
                AiHealth::failed('elevenlabs', 'connection: '.mb_substr($e->getMessage(), 0, 200));
                throw new AiFailed('unavailable', __('shared::ai.failed.unavailable'));
            } finally {
                if (is_resource($file)) {
                    fclose($file);
                }
            }
        });

        if (! $response->successful()) {
            $reason = $response->status() === 422 || $response->status() === 400 ? 'unheard' : 'unavailable';
            AiUsage::note($feature, 'elevenlabs', $model, [], $reason);
            if ($reason === 'unavailable') {
                AiHealth::failed('elevenlabs', 'HTTP '.$response->status());
            }
            throw new AiFailed($reason);
        }

        $transcript = self::read((array) $response->json(), $model);
        AiUsage::note($feature, 'elevenlabs', $model, ['audio_seconds' => (int) ceil($transcript->seconds)], $transcript->text === '' ? 'unheard' : 'ok');
        AiHealth::ok('elevenlabs');
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
