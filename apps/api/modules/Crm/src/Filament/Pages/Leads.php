<?php

namespace Modules\Crm\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Crm\Actions\CreateLead;
use Modules\Crm\Enums\LeadSource;
use Modules\Crm\Enums\LeadStage;
use Modules\Crm\Filament\Concerns\Attempts;
use Modules\Crm\Filament\LeadForm;
use Modules\Crm\Models\Lead;
use Modules\Crm\Queries\LeadFunnel;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;
use UnitEnum;

/**
 * The leads board (docs/architecture/01, section 14): one column per stage
 * on the way to a subscription, the last 30 days at a glance, and who
 * subscribed or was lost lately. Each card opens the lead.
 */
final class Leads extends Page
{
    use Attempts;

    public const PER_COLUMN = 30;

    protected string $view = 'crm::filament.leads';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'leads';

    #[Url]
    public string $owner = 'all';

    #[Url]
    public string $search = '';

    #[Url]
    public string $source = '';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageLeads->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('crm::leads.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('crm::leads.nav');
    }

    /** New leads nobody has called yet: mine, or nobody's. */
    public static function getNavigationBadge(): ?string
    {
        $count = Lead::query()->where('stage', LeadStage::New->value)
            ->where(fn ($q) => $q->where('owner_id', auth()->id())->orWhereNull('owner_id'))->count();

        return $count > 0 ? (string) $count : null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('crm::leads.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('crm::leads.subtitle');
    }

    /** @return array<string, array{label: string, cards: list<array<string, mixed>>, more: int}> */
    #[Computed]
    public function board(): array
    {
        $l = 'crm::leads.';
        $now = CarbonImmutable::now();
        $viewer = app(ViewerTimezone::class)->effective();
        $locale = app()->getLocale();
        $leads = $this->query()->whereIn('leads.stage', array_map(fn (LeadStage $s) => $s->value, LeadStage::OPEN))
            ->orderByDesc('leads.stage_changed_at')
            ->get(['leads.*', 'o.name as owner_name', 's.name_ar as subject_ar', 's.name_en as subject_en']);
        $trials = DB::table('lead_trials as t')->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 't.academy_id')->on('u.id', '=', 't.teacher_id'))
            ->whereIn('t.lead_id', $leads->pluck('id'))->whereIn('t.status', ['booked', 'attended'])->orderByDesc('t.starts_at')
            ->get(['t.lead_id', 't.starts_at', 't.status', 't.level', 'u.name as teacher'])->groupBy('lead_id');

        $columns = [];
        foreach (LeadStage::OPEN as $stage) {
            $mine = $leads->filter(fn (Lead $lead) => $lead->stage === $stage);
            $columns[$stage->value] = [
                'label' => __($l.'stages.'.$stage->value),
                'more' => max(0, $mine->count() - self::PER_COLUMN),
                'cards' => $mine->take(self::PER_COLUMN)->map(function (Lead $lead) use ($trials, $now, $viewer, $locale, $l) {
                    $trial = $trials[$lead->id][0] ?? null;

                    return [
                        'id' => $lead->id,
                        'name' => $lead->contact_name,
                        'for' => $lead->for_child && $lead->learner_name
                            ? __($l.'card.for', ['name' => $lead->learner_name]).($lead->learner_birth_year ? ' · '.__($l.'card.age', ['years' => $now->year - $lead->learner_birth_year]) : '')
                            : null,
                        'subject' => $locale === 'ar' ? $lead->getAttribute('subject_ar') : $lead->getAttribute('subject_en'),
                        'source' => __($l.'sources.'.$lead->source->value),
                        'owner' => $lead->getAttribute('owner_name'),
                        'note' => match (true) {
                            $lead->stage === LeadStage::TrialBooked && $trial !== null => __($l.'card.trial_at', ['when' => CarbonImmutable::parse($trial->starts_at)->setTimezone($viewer)->locale($locale)->translatedFormat('D j M · H:i')]),
                            $lead->stage === LeadStage::Attended => __($l.'card.waiting'),
                            $lead->stage === LeadStage::Assessed && $trial?->level => __($l.'card.level', ['level' => $trial->level]),
                            $lead->last_contact_at !== null => __($l.'card.contacted', ['ago' => $lead->last_contact_at->locale($locale)->diffForHumans()]),
                            default => __($l.'card.never'),
                        },
                        'fresh' => $lead->stage === LeadStage::New && $lead->created_at->greaterThan($now->subDay()),
                        'url' => LeadPage::getUrl(['lead' => $lead->id]),
                    ];
                })->values()->all(),
            ];
        }

        return $columns;
    }

    /** @return array{leads: int, booked: int, attended: int, won: int, rate: ?int} */
    #[Computed]
    public function funnel(): array
    {
        return app(LeadFunnel::class)->since(CarbonImmutable::now()->subDays(30), $this->owner === 'mine' ? (string) auth()->id() : null);
    }

    /** @return array{won: list<array<string, mixed>>, lost: list<array<string, mixed>>} */
    #[Computed]
    public function recent(): array
    {
        $since = CarbonImmutable::now()->subDays(30);
        $rows = $this->query()->whereIn('leads.stage', ['won', 'lost'])->where('leads.stage_changed_at', '>=', $since)
            ->orderByDesc('leads.stage_changed_at')->limit(40)->get(['leads.*', 'o.name as owner_name', 's.name_ar as subject_ar', 's.name_en as subject_en']);

        return collect(['won', 'lost'])->mapWithKeys(fn (string $stage) => [$stage => $rows->filter(fn (Lead $lead) => $lead->stage->value === $stage)->take(8)
            ->map(fn (Lead $lead) => [
                'name' => $lead->learnerName(),
                'detail' => $stage === 'lost' ? __('crm::leads.lost_reasons.'.($lead->lost_reason ?? 'other')) : $lead->stage_changed_at->locale(app()->getLocale())->diffForHumans(),
                'url' => LeadPage::getUrl(['lead' => $lead->id]),
            ])->values()->all()])->all();
    }

    /** @return array<string, string> */
    public function sources(): array
    {
        return LeadSource::options();
    }

    public function updated(string $property): void
    {
        unset($this->board, $this->funnel, $this->recent);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label(__('crm::leads.new'))->icon(Heroicon::OutlinedPlus)
                ->modalHeading(__('crm::leads.new'))->modalWidth('2xl')
                ->schema(LeadForm::fields())
                ->action(function (array $data, Action $action) {
                    $lead = $this->attempt($action, fn () => app(CreateLead::class)->handle($data, (string) auth()->id()));
                    Notification::make()->success()->title(__('crm::leads.created'))->send();
                    $this->redirect(LeadPage::getUrl(['lead' => $lead->id]), navigate: true);
                }),
        ];
    }

    private function query()
    {
        $term = trim($this->search);
        $digits = preg_replace('/\D/', '', $term);

        return Lead::query()
            ->leftJoin('users as o', fn ($j) => $j->on('o.academy_id', '=', 'leads.academy_id')->on('o.id', '=', 'leads.owner_id'))
            ->leftJoin('subjects as s', fn ($j) => $j->on('s.academy_id', '=', 'leads.academy_id')->on('s.id', '=', 'leads.subject_id'))
            ->when($this->owner === 'mine', fn ($q) => $q->where('leads.owner_id', auth()->id()))
            ->when($this->source !== '', fn ($q) => $q->where('leads.source', $this->source))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('leads.contact_name', 'ilike', '%'.$term.'%')
                ->orWhere('leads.learner_name', 'ilike', '%'.$term.'%')
                ->when(strlen($digits) >= 4, fn ($q) => $q->orWhere('leads.phone_e164', 'like', '%'.$digits.'%'))));
    }
}
