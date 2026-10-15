<?php

namespace Modules\Platform\Ai;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Lib\Streaming\MessageAccumulator;
use GuzzleHttp\Client as Guzzle;
use Modules\Platform\Games\Designer\ClaudeGameDesigner;
use Modules\Platform\Models\AiUsage;
use Modules\SharedKernel\Ai\AiAnswer;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Ai\AiPrompt;
use Modules\SharedKernel\Ai\LanguageModel;
use Throwable;

/**
 * The academies' helpers' words, through the Claude API (anthropic-ai/sdk),
 * as the games designer asks: one streamed request, the system prompt
 * cached, the answer held to the prompt's JSON schema, and the server's own
 * fallback model where the requested model has one. While someone waits on
 * a screen it does not retry and takes one of the few slots; in the
 * background it retries twice. Neither the prompt nor the answer is ever
 * logged: only the tokens it took, in ai_usage.
 */
final class ClaudeLanguageModel implements LanguageModel
{
    public function __construct(private readonly AiSlots $slots) {}

    public function json(AiFeature $feature, AiPrompt $prompt): AiAnswer
    {
        $config = config('platform.ai');
        $key = (string) ($config['key'] ?? '');
        $model = (string) ($config['models'][$prompt->tier] ?? '');
        if ($key === '' || $model === '') {
            throw new AiFailed('not_configured');
        }

        $waiting = $this->slots->waiting();
        $timeout = (float) ($waiting ? $config['timeout'] : $config['job_timeout']);
        $http = new Guzzle(['timeout' => $timeout, 'connect_timeout' => 10]);
        $client = new Client(
            apiKey: $key,
            baseUrl: ($config['base_url'] ?? null) ?: null,
            requestOptions: ['maxRetries' => $waiting ? 0 : 2, 'timeout' => $timeout, 'transporter' => $http, 'streamingTransporter' => $http],
        );
        $request = self::request($prompt, $model, (array) ($config['fallbacks'] ?? []));

        return $this->slots->hold(function () use ($client, $request, $feature, $model) {
            try {
                $stream = $client->beta->messages->createStream(...$request);
                $accumulator = MessageAccumulator::forBetaMessages();
                foreach ($stream as $event) {
                    $accumulator->accumulate($event);
                }
                $message = $accumulator->message();
            } catch (Throwable $e) {
                AiUsage::note($feature, 'anthropic', $model, [], 'unavailable');
                AiHealth::failed('anthropic', class_basename($e).': '.mb_substr($e->getMessage(), 0, 200));
                throw new AiFailed('unavailable', __('shared::ai.failed.unavailable'));
            }
            assert($message instanceof BetaMessage);

            try {
                $answer = self::read($message);
            } catch (AiFailed $e) {
                AiUsage::note($feature, 'anthropic', $message->model, self::usage($message), $e->reason);
                throw $e;
            }
            AiUsage::note($feature, 'anthropic', $answer->model, $answer->usage, 'ok');
            AiHealth::ok('anthropic');

            return $answer;
        });
    }

    /**
     * The request's named arguments: the system prompt cached, the answer held
     * to the prompt's schema, and the server's fallback only for a model that has one.
     *
     * @param  list<string>  $fallbacks  the models the server can fall back from
     * @return array<string, mixed>
     */
    public static function request(AiPrompt $prompt, string $model, array $fallbacks): array
    {
        $request = [
            'maxTokens' => $prompt->maxTokens,
            'messages' => [['role' => 'user', 'content' => $prompt->user]],
            'model' => $model,
            'system' => [['type' => 'text', 'text' => $prompt->system, 'cacheControl' => ['type' => 'ephemeral']]],
            'outputConfig' => [
                'effort' => in_array($prompt->effort, AiPrompt::EFFORTS, true) ? $prompt->effort : 'low',
                'format' => ['type' => 'json_schema', 'schema' => $prompt->schema],
            ],
        ];
        if (in_array($model, $fallbacks, true)) {
            $request += ['fallbacks' => 'default', 'betas' => [ClaudeGameDesigner::FALLBACK_BETA]];
        }

        return $request;
    }

    /** The answer as data, or why it cannot be used. */
    public static function read(BetaMessage $message): AiAnswer
    {
        if ($message->stopReason === 'refusal') {
            throw new AiFailed('refused');
        }
        if ($message->stopReason === 'max_tokens') {
            throw new AiFailed('too_long');
        }

        $text = '';
        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text;
            }
        }
        $data = json_decode($text, true);
        if (! is_array($data) || array_is_list($data) && $data !== []) {
            throw new AiFailed('unreadable');
        }

        return new AiAnswer($data, $message->model, self::usage($message));
    }

    /** @return array{input?: int, output?: int, cache_read?: int, cache_write?: int} */
    private static function usage(BetaMessage $message): array
    {
        $usage = $message->usage;

        return array_filter([
            'input' => $usage->inputTokens,
            'output' => $usage->outputTokens,
            'cache_read' => $usage->cacheReadInputTokens,
            'cache_write' => $usage->cacheCreationInputTokens,
        ], fn ($v) => $v !== null);
    }
}
