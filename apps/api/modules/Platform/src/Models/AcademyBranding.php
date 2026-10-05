<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\Branding\BrandTokens;
use Modules\SharedKernel\Tenancy\BelongsToTenant;

/**
 * The current academy's look. A tenant table on the app connection (RLS),
 * unlike Academy, which is read through the platform connection.
 *
 * @property string $id
 * @property string $academy_id
 * @property ?string $template_ref
 * @property array<string, string> $tokens
 * @property ?string $logo_path
 * @property ?string $favicon_path
 * @property ?string $updated_by
 */
final class AcademyBranding extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'academy_branding';

    protected $fillable = ['template_ref', 'tokens', 'logo_path', 'favicon_path', 'updated_by'];

    protected function casts(): array
    {
        return ['tokens' => 'array'];
    }

    public function brandTokens(): BrandTokens
    {
        return BrandTokens::fromArray($this->tokens);
    }
}
