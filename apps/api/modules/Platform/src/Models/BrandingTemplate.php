<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\Branding\BrandTokens;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * A look the academy saved from its own edits.
 *
 * @property string $id
 * @property string $academy_id
 * @property string $name
 * @property array<string, string> $tokens
 * @property ?string $created_by
 */
final class BrandingTemplate extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['name', 'tokens', 'created_by'];

    protected function casts(): array
    {
        return ['tokens' => 'array'];
    }

    public function brandTokens(): BrandTokens
    {
        return BrandTokens::fromArray($this->tokens);
    }

    public function ref(): string
    {
        return 'saved:'.$this->id;
    }
}
