<?php

namespace Modules\Scheduling\Filament;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Modules\Scheduling\Actions\CancelSession;
use Modules\Scheduling\Actions\Policy\PolicyDecider;
use Modules\Scheduling\Domain\Enums\ParticipantRole;
use Modules\Scheduling\Domain\Models\Session;
use Modules\Scheduling\Domain\Policy\Decision;
use Modules\Scheduling\Domain\Policy\PolicyEvent;

/**
 * The cancel dialog, shared by the live board and the student account: who
 * asked, why, and what the policy will do before anyone confirms. On a group
 * lesson the student's side never cancels it: the office picks whose seat to
 * excuse (one still open), and the preview is for that student's course.
 */
final class PolicyPreview
{
    /** @return list<mixed> */
    public static function cancelFields(Session $session): array
    {
        $seats = $session->isGroup() ? self::openSeats($session) : [];
        $group = $session->isGroup();
        $event = fn (Get $get) => CancelSession::event($get('by') ?? 'student', $get('reason') ?? 'other');
        // A group lesson asked off by the student side waits on whose seat it is.
        $seatFor = fn (Get $get) => $group && ($get('by') ?? 'student') === 'student' ? ($seats[(string) $get('student')]['enrollment'] ?? false) : null;

        return [
            ToggleButtons::make('by')->label(__('scheduling::policy.asked_by.label'))
                ->options(collect(['student', 'teacher', 'academy'])->mapWithKeys(fn ($b) => [$b => __('scheduling::policy.asked_by.'.$b)])->all())
                ->inline()->required()->live()
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('reason', CancelSession::REASONS[$state ?? 'student'][0] ?? 'other')),
            Select::make('student')->label(__('scheduling::group_outcomes.board.student'))
                ->options(array_map(fn (array $s) => $s['name'], $seats))
                ->helperText($seats === [] ? __('scheduling::group_outcomes.board.none_open') : __('scheduling::group_outcomes.board.student_hint'))
                ->visible(fn (Get $get) => $group && ($get('by') ?? 'student') === 'student')
                ->required(fn (Get $get) => $group && ($get('by') ?? 'student') === 'student')
                ->native(false)->live(),
            Select::make('reason')->label(__('scheduling::board.cancel.reason'))
                ->options(fn (Get $get) => collect(CancelSession::REASONS[$get('by') ?? 'student'] ?? [])->mapWithKeys(fn ($r) => [$r => __('scheduling::policy.reasons.'.$r)])->all())
                ->required()->native(false)->live(),
            Callout::make(__('scheduling::policy.preview.title'))
                ->description(fn (Get $get) => $seatFor($get) === false
                    ? __('scheduling::group_outcomes.board.'.($seats === [] ? 'none_open' : 'pick'))
                    : self::describe($session, $event($get), (bool) $get('waive'), $seatFor($get)))
                ->color(fn (Get $get) => $seatFor($get) === false ? 'gray' : (self::decision($session, $event($get), (bool) $get('waive'), $seatFor($get))->credit === 'forfeit' ? 'warning' : 'info')),
            Toggle::make('waive')->label(__('scheduling::policy.waive.label'))->helperText(__('scheduling::policy.waive.hint'))->live()
                ->visible(fn (Get $get) => $seatFor($get) !== false && (self::decision($session, $event($get), false, $seatFor($get))->credit === 'forfeit' || $get('waive'))),
            Textarea::make('note')->label(__('scheduling::board.contact.note'))->rows(2)->maxLength(500),
        ];
    }

    /**
     * A group lesson's seats still open to excuse (seated, nothing decided), by student: name and course.
     *
     * @return array<string, array{name: string, enrollment: string}>
     */
    public static function openSeats(Session $session): array
    {
        return DB::table('session_participants as p')
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'p.academy_id')->on('u.id', '=', 'p.user_id'))
            ->where('p.session_id', $session->id)->where('p.role', ParticipantRole::Student->value)
            ->where('p.is_active', true)->whereNull('p.outcome')->whereNotNull('p.enrollment_id')
            ->orderBy('u.name')->get(['p.user_id', 'u.name', 'p.enrollment_id'])
            ->mapWithKeys(fn (object $p) => [(string) $p->user_id => ['name' => (string) $p->name, 'enrollment' => (string) $p->enrollment_id]])->all();
    }

    public static function decision(Session $session, PolicyEvent $event, bool $waive = false, ?string $enrollmentId = null): Decision
    {
        $decision = app(PolicyDecider::class)->decide($session, $event, null, $enrollmentId)[0];

        return $waive ? $decision->waived() : $decision;
    }

    /** What happens, in a few lines: credit, teacher pay, the record, and the rule it comes from. */
    public static function describe(Session $session, PolicyEvent $event, bool $waive = false, ?string $enrollmentId = null): HtmlString
    {
        [$decision, $input, $version] = app(PolicyDecider::class)->decide($session, $event, null, $enrollmentId);
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
