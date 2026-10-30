<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Platform\Console\AuditFeed;
use Modules\Platform\Filament\Console\ConsoleView;
use Modules\Platform\Models\Academy;
use Modules\SharedKernel\Audit\AuditEntries;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\SharedKernel\Time\ZoneHint;

/**
 * The audit log across the platform (docs/architecture/03: audit_logs):
 * every academy's sensitive actions and the platform team's own, with who
 * did each (the team named, and on whose account when signed in as someone).
 */
final class AuditLog extends Page
{
    protected string $view = 'platform::console.audit';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'audit';

    private const PAGE = 50;

    #[Url]
    public string $academy = '';

    #[Url]
    public string $group = '';

    #[Url]
    public string $who = '';

    public int $limit = self::PAGE;

    public static function getNavigationLabel(): string
    {
        return __('platform::console.nav.audit');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::console.audit.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::console.audit.subtitle').' '.ZoneHint::yours();
    }

    /** @return array<string, string> */
    #[Computed]
    public function academies(): array
    {
        return Academy::query()->orderBy('name_ar')->get()->mapWithKeys(fn (Academy $a) => [$a->id => ConsoleView::name($a)])->all();
    }

    /** @return array{rows: list<array<string, mixed>>, more: bool} */
    #[Computed]
    public function feed(): array
    {
        $tz = app(ViewerTimezone::class)->effective();
        $feed = app(AuditFeed::class)->entries($this->academy ?: null, $this->group ?: null, $this->who ?: null, $this->limit);
        $isAr = app()->getLocale() === 'ar';

        $rows = array_map(fn (object $row) => AuditEntries::describe($row, $tz, $feed['names']) + [
            'id' => $row->id,
            'academy' => $row->academy_id === null ? null : ($isAr ? $row->academy_ar : $row->academy_en),
            'academy_url' => $row->academy_id === null ? null : AcademyOverview::getUrl(['academy' => $row->academy_id]),
            'ip' => $row->ip,
        ], $feed['rows']);

        return ['rows' => $rows, 'more' => $feed['more']];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['academy', 'group', 'who'], true)) {
            $this->limit = self::PAGE;
        }
    }

    public function more(): void
    {
        $this->limit += self::PAGE;
    }
}
