<?php

namespace Modules\Platform\Games\Designer;

use Anthropic\Beta\Messages\BetaMessage;
use Anthropic\Client;
use Anthropic\Lib\Streaming\MessageAccumulator;
use GuzzleHttp\Client as Guzzle;
use Modules\Platform\Ai\Faults\AnthropicFaults;
use Modules\SharedKernel\Health\ServiceHealth;
use Modules\SharedKernel\Health\ServiceProblem;
use Throwable;

/**
 * The designer, through the Claude API (anthropic-ai/sdk): one streamed
 * request per round, with the system prompt cached between rounds, the
 * answer held to DesignerPrompt::schema(), thinking at high effort, and the
 * server's own fallback model when the requested one declines (the
 * server-side-fallback beta). A round of revisions is a fresh request that
 * carries the story, the conversation and the current page, so nothing
 * depends on the API keeping state. The key is the platform's
 * (ANTHROPIC_API_KEY), never an academy's; nothing personal is ever sent.
 */
final class ClaudeGameDesigner implements GameDesigner
{
    public const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function design(DesignBrief $brief): DesignedGame
    {
        $config = config('platform.games.designer');
        $key = (string) ($config['key'] ?? '');
        if ($key === '' || (string) ($config['model'] ?? '') === '') {
            throw new DesignFailed('not_configured');
        }

        $http = new Guzzle(['timeout' => (float) $config['timeout'], 'connect_timeout' => 15]);
        $client = new Client(
            apiKey: $key,
            baseUrl: ($config['base_url'] ?? null) ?: null,
            requestOptions: ['maxRetries' => 2, 'timeout' => (float) $config['timeout'], 'transporter' => $http, 'streamingTransporter' => $http],
        );

        try {
            $stream = $client->beta->messages->createStream(
                maxTokens: (int) $config['max_tokens'],
                messages: [['role' => 'user', 'content' => DesignerPrompt::user($brief)]],
                model: (string) $config['model'],
                system: [['type' => 'text', 'text' => DesignerPrompt::system(), 'cacheControl' => ['type' => 'ephemeral']]],
                outputConfig: [
                    'effort' => (string) $config['effort'],
                    'format' => ['type' => 'json_schema', 'schema' => DesignerPrompt::schema()],
                ],
                fallbacks: 'default',
                betas: [self::FALLBACK_BETA],
            );
            $accumulator = MessageAccumulator::forBetaMessages();
            foreach ($stream as $event) {
                $accumulator->accumulate($event);
            }
            $message = $accumulator->message();
        } catch (Throwable $e) {
            $fault = AnthropicFaults::of($e);
            ServiceHealth::fault('anthropic', $fault, $fault->problem === ServiceProblem::Model ? (string) $config['model'] : '');
            throw new DesignFailed('unavailable', $fault->problem->value.' ('.$fault->code.') '.class_basename($e).': '.mb_substr($e->getMessage(), 0, 300));
        }

        assert($message instanceof BetaMessage);
        ServiceHealth::ok('anthropic', (string) $config['model']);

        return self::read($message);
    }

    /** The round's answer, or why it cannot be used. */
    public static function read(BetaMessage $message): DesignedGame
    {
        if ($message->stopReason === 'refusal') {
            throw new DesignFailed('refused', (string) ($message->stopDetails->explanation ?? ''));
        }
        if ($message->stopReason === 'max_tokens') {
            throw new DesignFailed('too_long');
        }

        $text = '';
        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                $text .= $block->text;
            }
        }
        $data = json_decode($text, true);
        if (! is_array($data) || ! is_string($data['html'] ?? null) || trim($data['html']) === '') {
            throw new DesignFailed('unreadable', mb_substr($text, 0, 200));
        }

        $str = fn (string $key, int $max) => mb_substr(trim((string) ($data[$key] ?? '')), 0, $max);
        $list = fn (string $key, array $allowed) => array_values(array_unique(array_intersect(array_map('strval', (array) ($data[$key] ?? [])), $allowed)));
        $min = max(1, min(12, (int) ($data['min_items'] ?? 1)));
        $usage = $message->usage;

        return new DesignedGame(
            titleAr: $str('title_ar', 80),
            titleEn: $str('title_en', 80),
            summaryAr: $str('summary_ar', 300),
            summaryEn: $str('summary_en', 300),
            howToPlayAr: $str('how_to_play_ar', 300),
            howToPlayEn: $str('how_to_play_en', 300),
            stages: $list('stages', ['kids', 'middle', 'teen', 'classic']),
            kinds: $list('kinds', ['choice', 'order', 'pair']),
            minItems: $min,
            maxItems: max($min, min(12, (int) ($data['max_items'] ?? $min))),
            message: $str('message_ar', 4000),
            html: trim($data['html']),
            servedModel: $message->model,
            usage: array_filter([
                'input' => $usage->inputTokens,
                'output' => $usage->outputTokens,
                'cache_read' => $usage->cacheReadInputTokens,
                'cache_write' => $usage->cacheCreationInputTokens,
            ], fn ($v) => $v !== null),
        );
    }
}
