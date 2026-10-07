<?php

namespace Modules\Platform\Models;

use Carbon\CarbonImmutable;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use SensitiveParameter;

/**
 * Someone of the platform team: signs in to the console alone, always with
 * a second factor (an authenticator app, with recovery codes), and reaches
 * an academy's data only by signing in as one of its people, with a reason.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property bool $is_active
 * @property string $locale
 * @property string $timezone
 * @property ?CarbonImmutable $last_login_at
 */
final class PlatformAdmin extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasLocalePreference, HasName
{
    use HasUuids;

    protected $connection = 'pgsql_platform';

    protected $fillable = ['name', 'email', 'password', 'is_active', 'locale', 'timezone'];

    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    protected $attributes = ['is_active' => true, 'locale' => 'ar', 'timezone' => 'UTC'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_login_at' => 'immutable_datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'console' && $this->is_active;
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function preferredLocale(): string
    {
        return $this->locale ?: 'ar';
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->forceFill(['app_authentication_secret' => $secret])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return ?array<string> */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /** @param  ?array<string>  $codes */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->forceFill(['app_authentication_recovery_codes' => $codes])->save();
    }
}
