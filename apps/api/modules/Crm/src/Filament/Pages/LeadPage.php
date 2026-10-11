<?php

namespace Modules\Crm\Filament\Pages;

use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Billing\Contracts\BillingApi;
use Modules\Crm\Actions\AssignLead;
use Modules\Crm\Actions\LogLeadContact;
use Modules\Crm\Actions\MarkLeadLost;
use Modules\Crm\Actions\PrepareLearner;
use Modules\Crm\Actions\ReopenLead;
use Modules\Crm\Actions\UpdateLead;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Filament\Concerns\Attempts;
use Modules\Crm\Filament\LeadForm;
use Modules\Crm\Models\Lead;
use Modules\Crm\Models\LeadActivity;
use Modules\Crm\Models\LeadTrial;
use Modules\Crm\Support\LeadFields;
use Modules\Identity\Contracts\IdentityApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Timezones;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * One lead (docs/architecture/01, section 14 and flow "أ"): how to reach
 * them and what they want, on their own clock; where they stand on the way
 * to a subscription; their trial lessons with the teacher's assessment;
 * and the timeline, where a call or a message is logged. The next step is
 * the first button: book a trial (Find a teacher, filled in), or enrol them
 * on the plan the teacher suggested.
 */
final class LeadPage extends Page
{
    use Attempts;

    protected string $view = 'crm::filament.lead';

    protected static ?string $slug = 'leads/{lead}';

    protected static bool $shouldRegisterNavigation = false;

    public string $leadId;

    public string $kind = 'call';

    public string $body = '';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageLeads->value);
    }

    public function mount(string $lead): void
    {
        $this->leadId = Lead::query()->findOrFail($lead)->id;
    }

    #[Computed]
    public function lead(): Lead
    {
        return Lead::query()->findOrFail($this->leadId);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->lead->contact_name;
    }

    public function getBreadcrumbs(): array
    {
        return [Leads::getUrl() => __('crm::leads.nav'), $this->lead->contact_name];
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function details(): array
    {
        $lead = $this->lead;
        $l = 'crm::leads.';
        $locale = app()->getLocale();
        $subject = $lead->subject_id ? DB::table('subjects')->where('id', $lead->subject_id)->value($locale === 'ar' ? 'name_ar' : 'name_en') : null;
        $days = collect($lead->preferred_days)->sortBy(fn (int $d) => $locale === 'ar' ? ($d + 1) % 7 : $d)->map(fn (int $d) => LeadForm::dayName($d))->implode('، ');

        return [
            'stage' => $lead->stage->value,
            'stageLabel' => __($l.'stages.'.$lead->stage->value),
            'step' => $lead->stage->step(),
            'phone' => $lead->phone_e164,
            // No number once the person's account was erased (Privacy).
            'whatsapp' => $lead->phone_e164 === null ? null : 'https://wa.me/'.ltrim($lead->phone_e164, '+'),
            'email' => $lead->email,
            'country' => $lead->country ? __('crm::countries.'.$lead->country) : null,
            'zone' => Timezones::city($lead->timezone),
            'localTime' => CarbonImmutable::now($lead->timezone)->locale($locale)->translatedFormat('l H:i'),
            'learner' => $lead->for_child ? $lead->learner_name : null,
            'age' => $lead->for_child && $lead->learner_birth_year ? CarbonImmutable::now()->year - $lead->learner_birth_year : null,
            'wants' => $lead->preferred_from !== null && $lead->preferred_to !== null && $days !== ''
                ? __($l.'page.wants', ['subject' => $subject ?? __($l.'page.no_subject'), 'days' => $days, 'from' => sprintf('%02d:00', $lead->preferred_from), 'to' => sprintf('%02d:00', $lead->preferred_to)])
                : ($subject ?? __($l.'page.no_subject')).' · '.__($l.'page.wants_any'),
            'source' => __($l.'sources.'.$lead->source->value),
            'utm' => collect([$lead->getAttribute('utm_source'), $lead->getAttribute('utm_medium'), $lead->getAttribute('utm_campaign')])->filter()->implode(' / '),
            'notes' => $lead->notes,
            'owner' => $lead->owner_id ? DB::table('users')->where('id', $lead->owner_id)->value('name') : null,
            'lostReason' => $lead->lost_reason ? __($l.'lost_reasons.'.$lead->lost_reason) : null,
            'changed' => $lead->stage_changed_at->setTimezone(app(ViewerTimezone::class)->effective())->locale($locale)->translatedFormat('j F Y'),
            'enrollmentUrl' => $lead->enrollment_id ? route('filament.academy.resources.enrollments.view', ['record' => $lead->enrollment_id]) : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function trials(): array
    {
        $lead = $this->lead;
        $locale = app()->getLocale();
        $plans = collect(app(BillingApi::class)->planOptions(null))->keyBy('id');

        return LeadTrial::query()->where('lead_id', $lead->id)->orderByDesc('starts_at')->get()->map(function (LeadTrial $t) use ($lead, $locale, $plans) {
            $teacher = DB::table('users')->where('id', $t->teacher_id)->first(['name', 'timezone']);

            return [
                'id' => $t->id,
                'status' => $t->status,
                'statusLabel' => __('crm::trials.statuses.'.$t->status),
                'teacher' => $teacher->name ?? '',
                'their' => $t->starts_at->setTimezone($lead->timezone)->locale($locale)->translatedFormat('l j F · H:i'),
                'teacherAt' => $t->starts_at->setTimezone($teacher->timezone ?? 'UTC')->format('H:i').' '.Timezones::city($teacher->timezone ?? 'UTC'),
                'assessed' => $t->isAssessed(),
                'level' => $t->level,
                'plan' => $t->recommended_plan_id ? ($plans[$t->recommended_plan_id]->name ?? null) : null,
                'perWeek' => $t->recommended_per_week,
                'assessment' => $t->assessment,
            ];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function timeline(): array
    {
        $l = 'crm::leads.';
        $locale = app()->getLocale();
        $viewer = app(ViewerTimezone::class)->effective();
        $activities = LeadActivity::query()->where('lead_id', $this->leadId)->orderByDesc('created_at')->limit(100)->get();
        $people = $activities->flatMap(fn (LeadActivity $a) => [$a->actor_id, $a->data['teacher'] ?? null, $a->kind === 'assigned' ? ($a->data['to'] ?? null) : null]);
        $names = DB::table('users')->whereIn('id', $people->filter()->unique()->values())->pluck('name', 'id');
        $at = fn (?string $iso) => $iso ? CarbonImmutable::parse($iso)->setTimezone($this->lead->timezone)->locale($locale)->translatedFormat('l j F · H:i') : '';

        return $activities->map(fn (LeadActivity $a) => [
            'id' => $a->id,
            'kind' => $a->kind,
            'text' => match ($a->kind) {
                'created' => __($l.'activity.created', ['source' => __($l.'sources.'.($a->data['source'] ?? 'other'))]),
                'assigned' => __($l.'activity.assigned', ['name' => $names[$a->data['to'] ?? ''] ?? '—']),
                'stage' => __($l.'activity.stage', ['stage' => __($l.'stages.'.($a->data['to'] ?? 'new'))]),
                'trial_booked' => __($l.'activity.trial_booked', ['teacher' => $names[$a->data['teacher'] ?? ''] ?? '—', 'when' => $at($a->data['at'] ?? null)]),
                'trial_moved' => __($l.'activity.trial_moved', ['when' => $at($a->data['at'] ?? null)]),
                'trial_missed' => __($l.'activity.trial_missed', ['outcome' => __('crm::trials.outcomes.'.($a->data['outcome'] ?? 'cancelled'))]),
                'assessed' => __($l.'activity.assessed', ['level' => $a->data['level'] ?? '']),
                'lost' => __($l.'activity.lost', ['reason' => __($l.'lost_reasons.'.($a->data['reason'] ?? 'other'))]),
                default => __($l.'activity.'.$a->kind),
            },
            'body' => $a->body,
            'who' => $a->actor_id ? ($names[$a->actor_id] ?? '—') : __($l.'activity.system'),
            'when' => $a->created_at->setTimezone($viewer)->locale($locale)->translatedFormat('j M · H:i'),
            'ago' => $a->created_at->locale($locale)->diffForHumans(),
        ])->all();
    }

    /** Logs a call, a WhatsApp message or a note from the timeline. */
    public function log(): void
    {
        try {
            app(LogLeadContact::class)->handle($this->lead, $this->kind, $this->body, (string) auth()->id());
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        $this->body = '';
        $this->refreshLead();
        Notification::make()->success()->title(__('crm::leads.page.logged'))->send();
    }

    protected function getHeaderActions(): array
    {
        $l = 'crm::leads.actions.';
        $open = fn () => $this->lead->stage->isOpen();
        $trialAhead = fn () => LeadTrial::query()->where('lead_id', $this->leadId)->where('status', 'booked')->exists();
        $afterTrial = fn () => in_array($this->lead->stage, [LeadStage::Attended, LeadStage::Assessed], true);

        return [
            Action::make('enroll')->label(__($l.'enroll'))->icon(Heroicon::OutlinedAcademicCap)
                ->visible(fn () => $open() && Gate::allows(Ability::ManageSessions->value))
                ->color(fn () => $afterTrial() ? 'primary' : 'gray')
                ->action(fn (Action $action) => $this->toFindTeacher($action, 'series')),
            Action::make('bookTrial')->label(__($l.'book_trial'))->icon(Heroicon::OutlinedSparkles)
                ->visible(fn () => $open() && ! $trialAhead() && Gate::allows(Ability::BookTrials->value))
                ->color(fn () => $afterTrial() ? 'gray' : 'primary')
                ->action(fn (Action $action) => $this->toFindTeacher($action, 'trial')),
            Action::make('reopen')->label(__($l.'reopen'))->icon(Heroicon::OutlinedArrowUturnLeft)
                ->visible(fn () => $this->lead->stage === LeadStage::Lost)
                ->requiresConfirmation()
                ->action(function (Action $action) use ($l) {
                    $this->attempt($action, fn () => app(ReopenLead::class)->handle($this->lead, (string) auth()->id()));
                    $this->refreshLead();
                    Notification::make()->success()->title(__($l.'reopened'))->send();
                }),
            Action::make('edit')->label(__($l.'edit'))->icon(Heroicon::OutlinedPencilSquare)->color('gray')
                ->modalHeading(__($l.'edit'))->modalWidth('2xl')
                ->fillForm(fn () => $this->lead->only(LeadFields::FIELDS))
                ->schema(LeadForm::fields())
                ->action(function (array $data, Action $action) use ($l) {
                    $this->attempt($action, fn () => app(UpdateLead::class)->handle($this->lead, $data, (string) auth()->id()));
                    $this->refreshLead();
                    Notification::make()->success()->title(__($l.'saved'))->send();
                }),
            Action::make('assign')->label(__($l.'assign'))->icon(Heroicon::OutlinedUserCircle)->color('gray')
                ->modalWidth('md')
                ->fillForm(fn () => ['owner_id' => $this->lead->owner_id])
                ->schema([
                    Select::make('owner_id')->label(__('crm::leads.fields.owner_id'))->required()->native(false)
                        ->options(fn () => DB::table('users')->whereIn('id', app(IdentityApi::class)->usersWithAbility(Ability::ManageLeads))->orderBy('name')->pluck('name', 'id')),
                ])
                ->action(function (array $data, Action $action) use ($l) {
                    $this->attempt($action, fn () => app(AssignLead::class)->handle($this->lead, $data['owner_id'], (string) auth()->id()));
                    $this->refreshLead();
                    Notification::make()->success()->title(__($l.'assigned'))->send();
                }),
            Action::make('lost')->label(__($l.'lost'))->icon(Heroicon::OutlinedXCircle)->color('danger')
                ->visible($open)
                ->modalHeading(__($l.'lost_heading'))->modalWidth('md')
                ->schema([
                    Select::make('reason')->label(__($l.'reason'))->required()->native(false)
                        ->options(collect(Lead::LOST_REASONS)->mapWithKeys(fn (string $r) => [$r => __('crm::leads.lost_reasons.'.$r)])->all()),
                    Textarea::make('note')->label(__($l.'note'))->rows(2)->maxLength(2000),
                ])
                ->action(function (array $data, Action $action) use ($l) {
                    $this->attempt($action, fn () => app(MarkLeadLost::class)->handle($this->lead, $data['reason'], $data['note'] ?? null, (string) auth()->id()));
                    $this->refreshLead();
                    Notification::make()->success()->title(__($l.'marked_lost'))->send();
                }),
        ];
    }

    /**
     * The learner gets an account if they have none, then Find a teacher
     * opens filled in: who, what, their clock and their times; back here
     * after a trial is booked.
     */
    private function toFindTeacher(Action $action, string $mode): void
    {
        $lead = $this->lead;
        $learner = $this->attempt($action, fn () => app(PrepareLearner::class)->handle($lead, (string) auth()->id()));
        $perWeek = LeadTrial::query()->where('lead_id', $lead->id)->whereNotNull('recommended_per_week')->latest('assessed_at')->value('recommended_per_week');

        $this->redirect(route('filament.academy.pages.find-teacher', array_filter([
            'mode' => $mode,
            'student' => $learner,
            'subject' => $lead->subject_id,
            'tz' => $lead->timezone,
            'days' => $lead->preferred_days !== [] ? implode(',', $lead->preferred_days) : null,
            'from' => $lead->preferred_from,
            'to' => $lead->preferred_to,
            'per_week' => $mode === 'series' ? $perWeek : null,
            'return' => parse_url(self::getUrl(['lead' => $lead->id]), PHP_URL_PATH),
        ], fn ($v) => $v !== null && $v !== '')), navigate: true);
    }

    private function refreshLead(): void
    {
        unset($this->lead, $this->details, $this->trials, $this->timeline);
    }
}
