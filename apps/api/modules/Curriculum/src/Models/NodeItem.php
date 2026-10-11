<?php

namespace Modules\Curriculum\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Curriculum\Contracts\DTOs\PracticeItem;
use Modules\SharedKernel\Games\ItemKind;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * One practice item on a lesson; changed only through SaveNodeItems.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $node_id
 * @property int $ordinal
 * @property string $kind choice | order | pair
 * @property ?string $prompt
 * @property array<string, mixed> $data
 * @property ?string $created_by
 */
final class NodeItem extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'node_items';

    protected $fillable = ['node_id', 'ordinal', 'kind', 'prompt', 'data', 'created_by'];

    protected function casts(): array
    {
        return ['ordinal' => 'integer', 'data' => 'array'];
    }

    public function toData(): PracticeItem
    {
        $d = $this->data;

        return new PracticeItem(
            id: $this->id,
            nodeId: $this->node_id,
            kind: ItemKind::from($this->kind),
            prompt: $this->prompt,
            options: array_values(array_map('strval', $d['options'] ?? [])),
            answer: isset($d['answer']) ? (int) $d['answer'] : null,
            parts: array_values(array_map('strval', $d['parts'] ?? [])),
            left: isset($d['left']) ? (string) $d['left'] : null,
            right: isset($d['right']) ? (string) $d['right'] : null,
        );
    }
}
