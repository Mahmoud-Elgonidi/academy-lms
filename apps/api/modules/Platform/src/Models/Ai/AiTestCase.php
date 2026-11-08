<?php

namespace Modules\Platform\Models\Ai;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * An input the comparison tool runs through two chains (console › AI
 * routing › Compare): for text a system prompt, a request and the answer's
 * schema; for speech a recording on the private disk (platform/ai-tests/).
 * Written by the platform's owners, never from a learner's data.
 *
 * @property string $id
 * @property string $capability
 * @property string $kind
 * @property string $title
 * @property ?string $system
 * @property ?string $prompt
 * @property ?array<string, mixed> $schema
 * @property ?string $audio_path
 * @property ?string $audio_mime
 * @property string $language
 * @property ?string $notes
 * @property ?string $created_by
 */
final class AiTestCase extends Model
{
    use HasUuids;

    public const DISK = 'local';

    public const FOLDER = 'platform/ai-tests';

    protected $connection = 'pgsql_platform';

    protected $table = 'ai_test_cases';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['schema' => 'array'];
    }
}
