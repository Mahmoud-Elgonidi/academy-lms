<?php

namespace Modules\Scheduling\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Scheduling\Actions\Policy\SavePolicySettings;
use Modules\Scheduling\Domain\Models\PolicyVersion;
use Modules\Scheduling\Domain\Policy\Conditions\NoticeAtLeast;
use Modules\Scheduling\Domain\Policy\Conditions\NoticeBelow;
use Modules\Scheduling\Domain\Policy\Conditions\QuotaLeft;
use Modules\Scheduling\Domain\Policy\Conditions\SessionTypeIs;
use Modules\Scheduling\Domain\Policy\PolicyEngine;
use Modules\Scheduling\Domain\Policy\PolicyInput;
use Modules\Scheduling\Domain\Policy\PolicySettings as Settings;
use Modules\Scheduling\Domain\Policy\Rule;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;
use Modules\SharedKernel\Time\ZoneHint;
use UnitEnum;

/**
 * The academy's cancellation, makeup and absence policy: the numbers, and the
 * fixed rules read with those numbers (docs/architecture/02, section 4). No
 * rule builder in the MVP; each save is a new version.
 *
 * @property-read Schema $form
 */
final class PolicySettings extends Page
{
    protected string $view = 'scheduling::filament.policy-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 85;

    protected static ?string $slug = 'settings/policy';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManagePolicies->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('scheduling::policy.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('scheduling::policy.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('scheduling::policy.subtitle').' '.ZoneHint::yours();
    }

    public function mount(): void
    {
        $this->form->fill(PolicyVersion::settingsInForce()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        $field = fn (string $key) => TextInput::make($key)->label(__('scheduling::policy.fields.'.$key))
            ->numeric()->integer()->required()->minValue(Settings::FIELDS[$key][1])->maxValue(Settings::FIELDS[$key][2])
            ->helperText(__('scheduling::policy.hints.'.$key) === 'scheduling::policy.hints.'.$key ? null : __('scheduling::policy.hints.'.$key));

        return $schema->statePath('data')->components([
            Section::make(__('scheduling::policy.sections.student'))->columns(2)->schema([
                $field('student_notice_hours'), $field('quota_per_block'), $field('late_cancel_teacher_pay_pct'), $field('student_absent_teacher_pay_pct'),
            ]),
            Section::make(__('scheduling::policy.sections.teacher'))->columns(2)->schema([
                $field('teacher_notice_hours'), $field('teacher_late_after_min'), $field('teacher_absent_after_min'),
            ]),
            Section::make(__('scheduling::policy.sections.attendance'))->columns(2)->schema([
                $field('student_wait_short_min'), $field('student_wait_long_min'), $field('completion_pct'),
            ]),
            Section::make(__('scheduling::policy.sections.credit'))->columns(2)->schema([
                $field('makeup_validity_days'), $field('unpaid_cancel_hours'),
            ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $version = app(SavePolicySettings::class)->handle($data, (string) auth()->id());
        Notification::make()->success()->title(__('scheduling::policy.saved', ['n' => $version->version]))->send();
    }

    public function versionLine(): ?string
    {
        $version = PolicyVersion::query()->orderByDesc('version')->first();
        if ($version === null) {
            return null;
        }
        $by = $version->created_by ? DB::table('users')->where('id', $version->created_by)->value('name') : null;

        return __('scheduling::policy.version', [
            'n' => $version->version,
            'date' => $version->created_at->setTimezone(app(ViewerTimezone::class)->effective())->locale(app()->getLocale())->translatedFormat('j F Y'),
        ]).($by ? ' · '.$by : '');
    }

    /** @return list<array{rule: int, event: string, condition: string, credit: string, pay: string, record: ?string}> */
    public function ruleRows(): array
    {
        $settings = PolicyVersion::settingsInForce();

        return array_map(function (Rule $rule) use ($settings) {
            $sample = $this->sampleFor($rule, $settings);
            $decision = $rule->decide($settings, $sample);
            $late = $rule->decide($settings, new PolicyInput($sample->event, -1, $sample->sessionType, $sample->quotaUsed));
            $conditions = array_map(function ($c) use ($settings) {
                [$key, $params] = $c->describe($settings);
                if ($key === 'session_type') {
                    $params['type'] = __('scheduling::policy.types.'.$params['type']);
                }

                return __('scheduling::policy.conditions.'.$key, $params);
            }, $rule->conditions);

            return [
                'rule' => $rule->number,
                'event' => __('scheduling::policy.events.'.$rule->event->value),
                'condition' => $conditions === [] ? __('scheduling::policy.rules.always') : implode(' · ', $conditions),
                'credit' => $decision->allowed ? __('scheduling::policy.credit.'.$decision->credit) : __('scheduling::policy.credit.refused'),
                'refused' => ! $decision->allowed,
                'pay' => $decision->allowed ? $decision->teacherPayPct.'%'.($late->teacherPayPct !== $decision->teacherPayPct ? ' · '.$late->teacherPayPct.'% ↯' : '') : '—',
                'record' => $decision->kpi ? __('scheduling::policy.kpi.'.$decision->kpi).' · '.__('scheduling::policy.severity.'.$decision->kpiSeverity) : null,
            ];
        }, PolicyEngine::rules());
    }

    /** An input that meets every condition of the rule, to read its outcome. */
    private function sampleFor(Rule $rule, Settings $settings): PolicyInput
    {
        $notice = 1000.0;
        $quota = 0;
        $type = 'regular';
        foreach ($rule->conditions as $c) {
            match (true) {
                $c instanceof NoticeAtLeast => null,
                $c instanceof NoticeBelow => $notice = -1,
                $c instanceof QuotaLeft => $quota = $c->isSatisfiedBy(new PolicyInput($rule->event, $notice, $type, 0), $settings) ? 0 : $settings->get('quota_per_block'),
                $c instanceof SessionTypeIs => $type = $c->describe($settings)[1]['type'],
                default => null,
            };
        }

        return new PolicyInput($rule->event, $notice, $type, $quota);
    }
}
