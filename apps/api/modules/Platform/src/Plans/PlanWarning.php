<?php

namespace Modules\Platform\Plans;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Platform\Filament\Pages\AcademyPlanPage;
use Modules\Platform\Models\Plans\PlanUpgradeRequest;
use Modules\Platform\Models\Plans\UsageTopUp;
use Modules\Platform\Plans\Requests\PlanRequests;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * Whether the academy's staff should hear about their plan, once a limit is
 * at 80% or more: each monthly one (video minutes, AI uses) on its own line,
 * with the ways on (an extra pack for this month, a bigger plan; for video,
 * lessons on Zoom or Google Meet), and the other limit closest to full.
 * Counted at most every ten minutes per academy, since it shows on every
 * page; a request waiting for the platform team is said in place of its button.
 */
final class PlanWarning
{
    public const MINUTES = 10;

    /** Settings › Live classroom, where the academy moves its lessons to the teachers' outside links (Classroom's page, by route name). */
    private const CLASSROOM = 'filament.academy.pages.settings.classroom';

    public function __construct(
        private readonly PlanUsage $usage,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * The lines to show, fullest first; none when nothing is close.
     *
     * @return list<array{limit: string, state: string, text: string, actions: list<array{label: string, url: ?string}>}>
     */
    public function forViewer(): array
    {
        if (! $this->tenants->check() || ! Gate::allows(Ability::ViewPlan->value)) {
            return [];
        }
        $academy = $this->tenants->require();
        $close = Cache::remember('plan-warning:'.$academy->id, now()->addMinutes(self::MINUTES), function (): array {
            $monthly = [];
            $other = null;
            foreach ($this->usage->lines() as $line) {
                $ratio = $line->ratio();
                if ($ratio === null || $ratio < 0.8) {
                    continue;
                }
                $row = ['limit' => $line->limit->value, 'used' => $line->used, 'max' => $line->allowed(), 'ratio' => is_infinite($ratio) ? 1e9 : $ratio];
                if ($line->limit->monthly()) {
                    $monthly[] = $row;
                } elseif ($other === null || $row['ratio'] > $other['ratio']) {
                    $other = $row;
                }
            }
            $rows = $other === null ? $monthly : [...$monthly, $other];
            usort($rows, fn (array $a, array $b) => $b['ratio'] <=> $a['ratio']);

            return $rows;
        });
        if ($close === []) {
            return [];
        }

        $waiting = UsageTopUp::query()->where('academy_id', $academy->id)->where('status', 'requested')->pluck('limit')->all();
        $upgradeWaiting = PlanUpgradeRequest::query()->where('academy_id', $academy->id)->where('status', 'requested')->exists();

        return array_map(fn (array $row) => $this->line($row, $waiting, $upgradeWaiting, $academy->defaultTimezone), $close);
    }

    /**
     * @param  array{limit: string, used: ?int, max: ?int, ratio: float}  $row
     * @param  list<string>  $waiting  limits with an extra pack waiting
     * @return array{limit: string, state: string, text: string, actions: list<array{label: string, url: ?string}>}
     */
    private function line(array $row, array $waiting, bool $upgradeWaiting, string $timezone): array
    {
        $limit = Limit::from($row['limit']);
        $state = $row['ratio'] >= 1 ? 'full' : 'near';
        $words = [
            'what' => __('shared::plans.limits.'.$limit->value),
            'usage' => PlanFormat::usage($limit, $row['used'], $row['max']),
            'renews' => PlanRequests::renews($timezone),
        ];
        $b = 'platform::plans.banner.';
        $upgrade = $upgradeWaiting
            ? ['label' => __($b.'upgrade_waiting'), 'url' => null]
            : ['label' => __($b.'upgrade'), 'url' => $this->planUrl(['upgrade' => 1])];

        if ($limit->monthly()) {
            $kind = $limit === Limit::VideoMinutes ? 'video' : 'ai';
            $actions = [in_array($limit->value, $waiting, true)
                ? ['label' => __($b.'buy_waiting'), 'url' => null]
                : ['label' => __($b.'buy_'.$kind), 'url' => $this->planUrl(['buy' => $limit->value])]];
            $actions[] = $upgrade;
            // The month's video minutes: lessons on Zoom or Google Meet cost nothing, and no lesson is ever cut.
            if ($limit === Limit::VideoMinutes && Route::has(self::CLASSROOM)) {
                $actions[] = ['label' => __($b.'video_action'), 'url' => route(self::CLASSROOM)];
            }

            return ['limit' => $limit->value, 'state' => $state, 'text' => __($b.$kind.'_'.$state, $words), 'actions' => $actions];
        }

        return [
            'limit' => $limit->value,
            'state' => $state,
            'text' => __($b.$state, $words).' '.__($b.($limit->held() ? 'held' : 'watched').'_'.$state),
            'actions' => [$upgrade, ['label' => __($b.'action'), 'url' => $this->planUrl([])]],
        ];
    }

    /** @param  array<string, mixed>  $query */
    private function planUrl(array $query): ?string
    {
        return Route::has('filament.academy.pages.settings.plan') ? AcademyPlanPage::getUrl($query, panel: 'academy') : null;
    }

    public function forget(string $academyId): void
    {
        Cache::forget('plan-warning:'.$academyId);
    }
}
