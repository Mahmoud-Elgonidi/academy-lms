<?php

namespace Modules\Platform\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Tenancy\BelongsToTenant;
use Modules\SharedKernel\Tenancy\TenantContext;
use Throwable;

/**
 * One request an academy's helper made to a provider: which helper, which
 * provider and model, the tokens or seconds of audio it took, and how it
 * went. Nothing about who asked and nothing that was said. Never changed
 * (the grants are revoked); it is how the platform sees cost and the daily cap.
 *
 * @property string $feature
 * @property string $provider
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_read_tokens
 * @property int $audio_seconds
 * @property string $outcome
 * @property CarbonImmutable $created_at
 */
final class AiUsage extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'ai_usage';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    /**
     * Notes one request for the academy in context (none: nothing to note).
     *
     * @param  array{input?: int, output?: int, cache_read?: int, cache_write?: int, audio_seconds?: int}  $usage
     */
    public static function note(AiFeature $feature, string $provider, string $model, array $usage, string $outcome): void
    {
        if (app(TenantContext::class)->id() === null) {
            return;
        }
        try {
            self::query()->create([
                'feature' => $feature->value,
                'provider' => $provider,
                'model' => mb_substr($model, 0, 80),
                'input_tokens' => (int) ($usage['input'] ?? 0) + (int) ($usage['cache_write'] ?? 0),
                'output_tokens' => (int) ($usage['output'] ?? 0),
                'cache_read_tokens' => (int) ($usage['cache_read'] ?? 0),
                'audio_seconds' => (int) ($usage['audio_seconds'] ?? 0),
                'outcome' => mb_substr($outcome, 0, 20),
            ]);
        } catch (Throwable $e) {
            // Counting a request never stops the helper that made it.
            report($e);
        }
    }

    /** The academy's requests since the start of today (the server's clock). */
    public static function today(): int
    {
        return self::query()->where('created_at', '>=', now()->startOfDay())->count();
    }
}
