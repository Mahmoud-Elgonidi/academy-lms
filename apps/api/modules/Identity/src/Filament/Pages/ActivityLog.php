<?php

namespace Modules\Identity\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditEntries;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;
use UnitEnum;

/**
 * The academy's own audit log (docs/architecture/01: "سجل مراجعة لكل عملية
 * حساسة"): money, policy, payroll, imports, accounts, and every time the
 * platform team signed in as someone here. Row-level security keeps it to
 * this academy's entries.
 */
final class ActivityLog extends Page
{
    protected string $view = 'identity::activity';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 95;

    protected static ?string $slug = 'settings/activity';

    private const PAGE = 50;

    #[Url]
    public string $group = '';

    #[Url]
    public string $who = '';

    public int $limit = self::PAGE;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewAuditLog->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('identity::activity.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('identity::activity.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('identity::activity.subtitle');
    }

    /** @return array{rows: list<array<string, mixed>>, more: bool} */
    #[Computed]
    public function feed(): array
    {
        $query = DB::table('audit_logs')->orderByDesc('created_at')->orderByDesc('id')->limit($this->limit + 1);
        if (isset(AuditEntries::GROUPS[$this->group])) {
            $query->where(function (Builder $q) {
                foreach (AuditEntries::GROUPS[$this->group] as $prefix) {
                    $q->orWhere('action', 'like', $prefix.'%');
                }
            });
        }
        if (in_array($this->who, ['platform', 'user', 'system'], true)) {
            $query->where('actor_type', $this->who);
        }
        $rows = $query->get()->all();
        $more = count($rows) > $this->limit;
        $rows = array_slice($rows, 0, $this->limit);

        $ids = array_values(array_unique(array_filter(array_map(fn (object $r) => $r->on_behalf_of, $rows))));
        $names = $ids === [] ? [] : User::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
        $tz = app(ViewerTimezone::class)->effective();

        return [
            'rows' => array_map(fn (object $r) => AuditEntries::describe($r, $tz, $names) + ['id' => $r->id], $rows),
            'more' => $more,
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['group', 'who'], true)) {
            $this->limit = self::PAGE;
        }
    }

    public function more(): void
    {
        $this->limit += self::PAGE;
    }
}
