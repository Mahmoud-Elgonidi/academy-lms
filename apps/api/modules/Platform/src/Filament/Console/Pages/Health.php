<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Modules\Platform\Filament\Console\ConsoleView;
use Modules\Platform\Health\SystemHealth;
use Modules\Platform\Models\Academy;
use Modules\SharedKernel\Health\RecentErrors;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The stack's health for the platform team (docs/architecture/03, section
 * 12): every check, the backups and restore drills, and the latest errors.
 * What it shows changes with the clock (how long since each part spoke), so
 * it checks again every minute while it is in front.
 */
final class Health extends Page
{
    protected string $view = 'platform::console.health';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'health';

    public const REFRESH_SECONDS = 60;

    public static function getNavigationLabel(): string
    {
        return __('platform::health.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::health.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::health.subtitle');
    }

    /** @return array<string, array{status: string, value: string, hint: ?string}> */
    #[Computed]
    public function checks(): array
    {
        return app(SystemHealth::class)->checks();
    }

    public function overall(): string
    {
        return SystemHealth::overall($this->checks);
    }

    public function checkedAt(): string
    {
        return CarbonImmutable::now(app(ViewerTimezone::class)->effective())->locale(app()->getLocale())->translatedFormat('H:i');
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function backups(): array
    {
        $tz = app(ViewerTimezone::class)->effective();

        return array_map(fn (object $b) => [
            'kind' => $b->kind,
            'status' => $b->status,
            'when' => CarbonImmutable::parse($b->finished_at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M، H:i'),
            'took' => max(1, (int) CarbonImmutable::parse($b->started_at)->diffInSeconds(CarbonImmutable::parse($b->finished_at))),
            'size' => $b->size_bytes === null ? null : SystemHealth::bytes((int) $b->size_bytes),
            'name' => $b->name,
            'detail' => $b->detail,
        ], app(SystemHealth::class)->backupRuns());
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function errors(): array
    {
        $errors = array_slice(RecentErrors::all(), 0, 20);
        $ids = array_values(array_filter(array_unique(array_column($errors, 'academy'))));
        $names = $ids === [] ? [] : Academy::query()->whereIn('id', $ids)->get()->mapWithKeys(fn (Academy $a) => [$a->id => ConsoleView::name($a)])->all();

        return array_map(fn (array $e) => $e + [
            'short' => class_basename($e['class']),
            'academy_name' => $e['academy'] === null ? null : ($names[$e['academy']] ?? null),
            'last_human' => CarbonImmutable::createFromTimestamp($e['last'])->locale(app()->getLocale())->diffForHumans(),
        ], array_values($errors));
    }

    public function check(): void
    {
        unset($this->checks, $this->backups, $this->errors);
    }
}
