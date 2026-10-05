<?php

namespace Modules\Identity\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Sanctum token that remembers its academy, so a bearer token alone decides
 * the tenant (see App\Http\Middleware\ResolveTenant).
 *
 * @property string $academy_id
 */
final class PersonalAccessToken extends SanctumToken
{
    protected $fillable = ['name', 'token', 'abilities', 'expires_at', 'academy_id'];

    protected static function booted(): void
    {
        self::creating(function (self $token): void {
            $token->academy_id ??= app(TenantContext::class)->require()->id;
        });
    }
}
