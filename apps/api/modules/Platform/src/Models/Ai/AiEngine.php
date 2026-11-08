<?php

namespace Modules\Platform\Models\Ai;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\Ai\Engines\EngineKind;

/**
 * A place a model answers (console › AI engines): its kind, address, key
 * (encrypted with APP_KEY, hidden from every array and never shown again),
 * models, time-out and cost. Saved only through SaveAiEngine; platform
 * connection only.
 *
 * @property string $id
 * @property string $name
 * @property string $kind
 * @property ?string $base_url
 * @property ?string $api_key
 * @property ?string $model_best
 * @property ?string $model_fast
 * @property ?string $model_speech
 * @property ?int $timeout_seconds
 * @property ?string $cost_input_per_m
 * @property ?string $cost_output_per_m
 * @property ?string $cost_audio_per_min
 * @property ?string $cost_gpu_per_min
 * @property string $cost_currency
 * @property ?array<string, mixed> $options
 * @property bool $enabled
 * @property ?string $notes
 * @property ?string $updated_by
 * @property CarbonImmutable $updated_at
 */
final class AiEngine extends Model
{
    use HasUuids;

    protected $connection = 'pgsql_platform';

    protected $table = 'ai_engines';

    protected $guarded = [];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'options' => 'array',
            'enabled' => 'boolean',
            'timeout_seconds' => 'integer',
            'updated_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function kindOf(): EngineKind
    {
        return EngineKind::from($this->kind);
    }

    public function hasKey(): bool
    {
        return trim((string) $this->api_key) !== '';
    }

    /** The name it goes by in ServiceHealth and the health page's checks (service_ai_engine_{id}). */
    public function service(): string
    {
        return self::serviceOf($this->id);
    }

    public static function serviceOf(string $id): string
    {
        return 'ai_engine_'.$id;
    }
}
