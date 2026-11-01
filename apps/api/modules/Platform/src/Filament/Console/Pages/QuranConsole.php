<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;
use Modules\Platform\Console\PlatformAudit;
use Modules\Platform\Filament\Console\ConsoleView;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\SaasSubscription;
use Modules\Platform\Plans\PlanTerms;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Text\QuranFetchFailed;
use Modules\SharedKernel\Text\QuranSource;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The console's «القرآن» (docs/architecture/02, section 26): the platform's
 * copy of the Quran on the server (where it is, Tanzil's notice, its
 * verses and checksum) and fetching it again (owners), and the academies
 * whose plan has the Quran's area, by their plan or set for them alone.
 * Read from the platform's own tables: what an academy does with the Quran
 * is the academy's.
 */
final class QuranConsole extends Page
{
    protected string $view = 'platform::console.quran-page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'quran';

    public static function getNavigationLabel(): string
    {
        return __('platform::console.quran.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::console.quran.title');
    }

    public function getSubheading(): ?string
    {
        return __('platform::console.quran.subtitle');
    }

    public static function isOwner(): bool
    {
        $admin = auth('platform')->user();

        return $admin instanceof PlatformAdmin && $admin->is_owner && $admin->is_active;
    }

    /** @return array{available: bool, where: ?string, notice: ?string, verses: ?int, text_sha256: ?string, data_sha256: ?string, fetched_at: ?string} */
    #[Computed]
    public function quran(): array
    {
        $status = app(QuranSource::class)->status();
        if ($status['fetched_at'] !== null) {
            $status['fetched_at'] = CarbonImmutable::parse($status['fetched_at'])->setTimezone(app(ViewerTimezone::class)->effective())
                ->locale(app()->getLocale())->translatedFormat(Clock::pattern('j M Y، H:i'));
        }

        return $status;
    }

    /**
     * Every academy and whether the Quran is theirs: by their plan, set for them alone, taken away, or on no plan (which has everything).
     *
     * @return array{on: list<array<string, mixed>>, off: int}
     */
    #[Computed]
    public function academies(): array
    {
        $on = [];
        $off = 0;
        foreach (Academy::query()->orderBy('name_'.(app()->getLocale() === 'ar' ? 'ar' : 'en'))->get() as $academy) {
            $subscription = SaasSubscription::currentFor($academy->id);
            $terms = $subscription === null ? null : PlanTerms::of($subscription);
            if ($terms !== null && ! $terms->has(Feature::Quran)) {
                $off++;

                continue;
            }
            $on[] = [
                'id' => $academy->id,
                'name' => ConsoleView::name($academy),
                'slug' => $academy->slug,
                'plan' => $terms?->name(),
                'how' => match (true) {
                    $terms === null => 'no_plan',
                    in_array(Feature::Quran->value, $subscription->plan->features, true) => 'plan',
                    default => 'academy',
                },
                'url' => AcademyOverview::getUrl(['academy' => $academy->id]),
            ];
        }

        return ['on' => $on, 'off' => $off];
    }

    /** Fetches Tanzil's files to the server again (checked whole before they replace the copy in use); owners only. */
    public function fetchQuranAction(): Action
    {
        return Action::make('fetchQuran')->label(fn () => __($this->quran['available'] ? 'platform::settings.quran.fetch_again' : 'platform::settings.quran.fetch'))
            ->icon('heroicon-m-arrow-down-tray')->color(fn () => $this->quran['available'] ? 'gray' : 'primary')
            ->authorize(fn () => self::isOwner())
            ->requiresConfirmation()->modalDescription(__('platform::settings.quran.confirm'))
            ->action(function () {
                try {
                    $status = app(QuranSource::class)->fetch();
                } catch (QuranFetchFailed $e) {
                    Notification::make()->danger()->title(__('platform::settings.quran.failed'))->body($e->getMessage())->persistent()->send();

                    return;
                }
                app(PlatformAudit::class)->record(null, 'platform.quran_fetched', 'platform_settings', null, ['verses' => $status['verses']]);
                unset($this->quran);
                Notification::make()->success()->title(__('platform::settings.quran.fetched'))->send();
            });
    }
}
