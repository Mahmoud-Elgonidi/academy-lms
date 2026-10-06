<?php

namespace Modules\Scheduling\Filament;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\Policy\PolicyDecider;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Policy\Decision;
use Modules\Scheduling\Domain\Policy\PolicyEvent;

/**
 * The cancel dialog, shared by the live board and the student account: who
 * asked, why, and what the policy will do before anyone confirms.
 */
final class PolicyPreview
{
    /** @return list<mixed> */
    public static function cancelFields(Session $session): array
    {
        return [
            ToggleButtons::make('by')->label(__('scheduling::policy.asked_by.label'))
                ->options(collect(['student', 'teacher', 'academy'])->mapWithKeys(fn ($b) => [$b => __('scheduling::policy.asked_by.'.$b)])->all())
                ->inline()->required()->live()
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('reason', CancelSession::REASONS[$state ?? 'student'][0] ?? 'other')),
            Select::make('reason')->label(__('scheduling::board.cancel.reason'))
                ->options(fn (Get $get) => collect(CancelSession::REASONS[$get('by') ?? 'student'] ?? [])->mapWithKeys(fn ($r) => [$r => __('scheduling::policy.reasons.'.$r)])->all())
                ->required()->native(false)->live(),
            Callout::make(__('scheduling::policy.preview.title'))
                ->description(fn (Get $get) => self::describe($session, CancelSession::event($get('by') ?? 'student', $get('reason') ?? 'other'), (bool) $get('waive')))
                ->color(fn (Get $get) => self::decision($session, CancelSession::event($get('by') ?? 'student', $get('reason') ?? 'other'), (bool) $get('waive'))->credit === 'forfeit' ? 'warning' : 'info'),
            Toggle::make('waive')->label(__('scheduling::policy.waive.label'))->helperText(__('scheduling::policy.waive.hint'))->live()
                ->visible(fn (Get $get) => self::decision($session, CancelSession::event($get('by') ?? 'student', $get('reason') ?? 'other'))->credit === 'forfeit' || $get('waive')),
            Textarea::make('note')->label(__('scheduling::board.contact.note'))->rows(2)->maxLength(500),
        ];
    }

    public static function decision(Session $session, PolicyEvent $event, bool $waive = false): Decision
    {
        $decision = app(PolicyDecider::class)->decide($session, $event)[0];

        return $waive ? $decision->waived() : $decision;
    }

    /** What happens, in a few lines: credit, teacher pay, the record, and the rule it comes from. */
    public static function describe(Session $session, PolicyEvent $event, bool $waive = false): HtmlString
    {
        [$decision, $input, $version] = app(PolicyDecider::class)->decide($session, $event);
        if ($waive) {
            $decision = $decision->waived();
        }
        $policy = $version->policy();

        $lines = $decision->allowed
            ? array_filter([
                __('scheduling::policy.preview.credit.'.$decision->credit, ['days' => $policy->get('makeup_validity_days')]),
                __('scheduling::policy.preview.teacher_pay', ['pct' => $decision->teacherPayPct]),
                $decision->kpi ? __('scheduling::policy.preview.kpi', ['kpi' => __('scheduling::policy.kpi.'.$decision->kpi), 'severity' => __('scheduling::policy.severity.'.$decision->kpiSeverity)]) : null,
            ])
            : [__('scheduling::policy.preview.refused', ['why' => __('scheduling::policy.refusals.'.$decision->refusal, ['hours' => $policy->get('student_notice_hours'), 'n' => $policy->get('quota_per_block')])])];

        $meta = __('scheduling::policy.preview.rule', [
            'n' => $decision->rule,
            'v' => $version->version,
            'hours' => max(0, (int) floor($input->noticeHours)),
            'used' => $input->quotaUsed,
            'quota' => $policy->get('quota_per_block'),
        ]);

        return new HtmlString(implode('<br>', array_map('e', $lines)).'<br><small style="opacity:.75">'.e($meta).'</small>');
    }
}
