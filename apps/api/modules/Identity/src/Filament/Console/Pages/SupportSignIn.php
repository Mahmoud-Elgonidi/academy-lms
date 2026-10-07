<?php

namespace Modules\Identity\Filament\Console\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Identity\Actions\Impersonation\EndImpersonation;
use Modules\Identity\Actions\Impersonation\StartImpersonation;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\Impersonation;
use Modules\Identity\Models\User;
use Modules\Platform\Contracts\ConsoleApi;
use Modules\Platform\Contracts\TenantDirectory;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\SharedKernel\Tenancy\TenantRef;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Signing in as someone of an academy for support (docs/architecture/01:
 * "الدخول بحساب مستخدم مع تسجيله"): pick the academy and the person, say why
 * and for how long, and open the one-time link. The academy's latest sign-ins
 * are here too, and one still running can be ended.
 */
final class SupportSignIn extends Page
{
    protected string $view = 'identity::console.support-sign-in';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'support-sign-in';

    #[Url]
    public string $academy = '';

    public string $search = '';

    public ?string $person = null;

    public string $reason = '';

    public int $minutes = 30;

    /** @var array{url: string, name: string, until: int}|null */
    public ?array $ready = null;

    public static function getNavigationLabel(): string
    {
        return __('identity::impersonation.console.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('identity::impersonation.console.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('identity::impersonation.console.subtitle');
    }

    /** @return array<string, string> */
    #[Computed]
    public function academies(): array
    {
        $isAr = app()->getLocale() === 'ar';
        $list = [];
        foreach (app(TenantDirectory::class)->active() as $tenant) {
            $list[$tenant->id] = $isAr ? $tenant->nameAr : $tenant->nameEn;
        }
        asort($list);

        return $list;
    }

    /** @return list<array{id: string, name: string, contact: ?string, roles: list<string>}> */
    #[Computed]
    public function people(): array
    {
        $tenant = $this->tenant();
        if ($tenant === null) {
            return [];
        }

        return app(TenantContext::class)->run($tenant, function () {
            $query = User::query()->with('roleRecords')->where('status', 'active')->orderBy('name')->limit(12);
            $term = trim($this->search);
            if ($term !== '') {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
                $digits = preg_replace('/\D/', '', $term) ?? '';
                $query->where(function ($q) use ($like, $digits) {
                    $q->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like)->orWhere('username', 'ilike', $like);
                    if (strlen($digits) >= 4) {
                        $q->orWhere('phone_e164', 'like', '%'.$digits.'%');
                    }
                });
            }

            return $query->get()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'contact' => $u->email ?? $u->phone_e164 ?? $u->username,
                'roles' => array_map(fn (Role $r) => $r->value, $u->roles()),
            ])->all();
        });
    }

    /** @return ?array{id: string, name: string, contact: ?string, roles: list<string>} */
    #[Computed]
    public function chosen(): ?array
    {
        $tenant = $this->tenant();
        if ($tenant === null || $this->person === null) {
            return null;
        }

        return app(TenantContext::class)->run($tenant, function () {
            $u = User::query()->with('roleRecords')->where('status', 'active')->find($this->person);

            return $u === null ? null : ['id' => $u->id, 'name' => $u->name, 'contact' => $u->email ?? $u->phone_e164 ?? $u->username, 'roles' => array_map(fn (Role $r) => $r->value, $u->roles())];
        });
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function recent(): array
    {
        $tenant = $this->tenant();
        if ($tenant === null) {
            return [];
        }
        $tz = app(ViewerTimezone::class)->effective();

        return app(TenantContext::class)->run($tenant, fn () => Impersonation::query()->with('user')->latest('created_at')->limit(8)->get()
            ->map(fn (Impersonation $i) => [
                'id' => $i->id,
                'user' => $i->user?->name ?? '—',
                'admin' => $i->admin_name,
                'minutes' => $i->minutes,
                'reason' => $i->reason,
                'state' => $i->state(),
                'how' => $i->ended_reason,
                'when' => $i->created_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y · H:i'),
            ])->all());
    }

    public function updatedAcademy(): void
    {
        $this->person = null;
        $this->search = '';
        $this->ready = null;
        $this->resetErrorBag();
    }

    public function choose(string $id): void
    {
        $this->person = $id;
        $this->ready = null;
        $this->resetErrorBag();
    }

    public function clearPerson(): void
    {
        $this->person = null;
        $this->ready = null;
    }

    public function start(): void
    {
        $tenant = $this->tenant();
        $admin = app(ConsoleApi::class)->currentAdmin();
        if ($tenant === null || $this->person === null || $admin === null) {
            return;
        }
        $this->resetErrorBag();

        try {
            $result = app(StartImpersonation::class)->handle($tenant, $this->person, $admin, $this->reason, $this->minutes);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        $this->ready = [
            'url' => $result['url'],
            'name' => $this->chosen['name'] ?? '',
            'until' => $result['impersonation']->redeem_by->getTimestamp(),
        ];
        $this->reason = '';
        unset($this->recent);
    }

    public function end(string $id): void
    {
        $tenant = $this->tenant();
        if ($tenant === null) {
            return;
        }
        app(TenantContext::class)->run($tenant, function () use ($id) {
            $impersonation = Impersonation::query()->find($id);
            if ($impersonation !== null) {
                app(EndImpersonation::class)->handle($impersonation, 'ended');
            }
        });
        unset($this->recent);
        Notification::make()->title(__('identity::impersonation.console.ended_done'))->success()->send();
    }

    private function tenant(): ?TenantRef
    {
        if ($this->academy === '' || ! array_key_exists($this->academy, $this->academies)) {
            return null;
        }

        return app(TenantDirectory::class)->byId($this->academy);
    }
}
