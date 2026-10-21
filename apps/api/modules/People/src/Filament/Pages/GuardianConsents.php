<?php

namespace Modules\People\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Modules\People\Actions\Consent\RecordConsent;
use Modules\People\Actions\Consent\RequestConsent;
use Modules\People\Actions\Consent\SaveConsentAge;
use Modules\People\Actions\Consent\WithdrawConsent;
use Modules\People\Models\ConsentSubject;
use Modules\People\Models\GuardianConsent;
use Modules\People\Models\PeopleSettings;
use Modules\People\Queries\ConsentBoard;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Guardians' consent to children's accounts, for whoever follows the
 * families: who still waits (and their guardians, to ask again), whose consent
 * was taken back, and who has it; a consent the office holds on paper can be
 * recorded here, and one withdrawn at a guardian's request. The age it applies
 * under is the owner's and admins' to set.
 */
final class GuardianConsents extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'people::filament.guardian-consents';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = -2;

    protected static ?string $slug = 'guardian-consents';

    #[Url]
    public string $tab = 'waiting';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageConsents->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('people::consent.office.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(ConsentBoard::class)->query('waiting')->count() + app(ConsentBoard::class)->query('withdrawn')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('people::consent.office.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('people::consent.office.lead', ['age' => PeopleSettings::current()->consent_age]);
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return app(ConsentBoard::class)->counts();
    }

    public function updatedTab(): void
    {
        $this->resetTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('age')
                ->label(fn () => __('people::consent.office.settings').': '.__('people::consent.office.under', ['age' => PeopleSettings::current()->consent_age]))
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)->color('gray')
                ->visible(fn () => Gate::allows(Ability::ManagePolicies->value))
                ->modalHeading(__('people::consent.office.settings'))
                ->modalDescription(__('people::consent.office.settings_hint'))
                ->fillForm(fn () => ['consent_age' => PeopleSettings::current()->consent_age])
                ->schema([
                    Select::make('consent_age')->label(__('people::consent.office.settings'))->required()->native(false)
                        ->options(collect(PeopleSettings::CONSENT_AGES)->mapWithKeys(fn (int $a) => [$a => __('people::consent.office.under', ['age' => $a])])),
                ])
                ->action(function (array $data) {
                    app(SaveConsentAge::class)->handle((int) $data['consent_age']);
                    Notification::make()->success()->title(__('people::consent.office.settings_saved'))->send();
                    $this->resetTable();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $tab = in_array($this->tab, ConsentBoard::TABS, true) ? $this->tab : 'waiting';
        $tz = app(ViewerTimezone::class)->effective();
        $day = fn ($at) => $at ? CarbonImmutable::parse($at)->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y') : null;
        $l = 'people::consent.office.';

        return $table
            ->query(app(ConsentBoard::class)->query($tab))
            ->defaultSort(fn ($query) => $query->orderBy($tab === 'granted' ? 'granted_at' : 'users.name', $tab === 'granted' ? 'desc' : 'asc'))
            ->columns([
                TextColumn::make('name')->label(__($l.'columns.student'))->weight('semibold')->searchable('users.name'),
                TextColumn::make('birth_date')->label(__($l.'columns.age'))
                    ->state(fn (ConsentSubject $s) => $s->getAttribute('birth_date') ? __($l.'age', ['age' => (int) CarbonImmutable::parse($s->getAttribute('birth_date'))->diffInYears(CarbonImmutable::now($tz))]) : __($l.'age_unknown')),
                TextColumn::make('guardians')->label(__($l.'columns.guardians'))->wrap()
                    ->state(fn (ConsentSubject $s) => $s->getAttribute('guardians') ?: __($l.'no_guardian'))
                    ->color(fn (ConsentSubject $s) => $s->getAttribute('guardians') ? null : 'danger'),
                TextColumn::make('since')->label(__($l.'columns.since'))
                    ->state(fn (ConsentSubject $s) => match ($tab) {
                        'granted' => $day($s->getAttribute('granted_at')),
                        'withdrawn' => $day($s->getAttribute('withdrawn_at')),
                        default => null,
                    })
                    ->description(fn (ConsentSubject $s) => $tab === 'granted' && $s->getAttribute('via') ? __('people::consent.via.'.$s->getAttribute('via')) : null)
                    ->visible($tab !== 'waiting'),
            ])
            ->recordActions([
                Action::make('resend')->label(__($l.'resend'))->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
                    ->visible($tab !== 'granted')
                    ->disabled(fn (ConsentSubject $s) => (int) $s->getAttribute('guardian_count') === 0)
                    ->action(function (ConsentSubject $s) {
                        $count = app(RequestConsent::class)->handle($s->id, again: true);
                        Notification::make()->success()->title(trans_choice('people::consent.office.resent', $count, ['count' => $count]))->send();
                    }),
                Action::make('record')->label(__($l.'record'))->icon(Heroicon::OutlinedDocumentCheck)
                    ->visible($tab !== 'granted')
                    ->modalHeading(fn (ConsentSubject $s) => __($l.'record').' · '.$s->name)
                    ->modalDescription(__($l.'record_hint'))
                    ->schema(fn (ConsentSubject $s) => [
                        Select::make('guardian')->label(__('people::consent.attributes.guardian'))->native(false)
                            ->options(fn () => DB::table('guardian_student as g')->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'g.academy_id')->on('u.id', '=', 'g.guardian_id'))
                                ->where('g.student_id', $s->id)->orderBy('u.name')->pluck('u.name', 'u.id')->all()),
                        Textarea::make('note')->label(__('people::consent.attributes.note'))->required()->minLength(RecordConsent::NOTE_MIN)->maxLength(300)->rows(3),
                    ])
                    ->action(function (ConsentSubject $s, array $data) {
                        app(RecordConsent::class)->handle($s->id, $data['guardian'] ?: null, (string) $data['note'], (string) auth()->id());
                        Notification::make()->success()->title(__('people::consent.office.recorded'))->send();
                    }),
                Action::make('withdraw')->label(__($l.'withdraw'))->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible($tab === 'granted')
                    ->modalDescription(__($l.'withdraw_hint'))
                    ->schema([Textarea::make('note')->label(__('people::consent.attributes.note'))->required()->minLength(RecordConsent::NOTE_MIN)->maxLength(300)->rows(2)])
                    ->action(function (ConsentSubject $s, array $data) {
                        app(WithdrawConsent::class)->handle($s->id, (string) auth()->id(), false, (string) $data['note']);
                        Notification::make()->success()->title(__('people::consent.office.withdrawn'))->send();
                    }),
                Action::make('history')->label(__($l.'history'))->icon(Heroicon::OutlinedClock)->color('gray')->link()
                    ->modalHeading(fn (ConsentSubject $s) => __($l.'history').' · '.$s->name)
                    ->modalContent(fn (ConsentSubject $s) => view('people::filament.consent-history', ['rows' => $this->history($s->id, $day)]))
                    ->modalSubmitAction(false)->modalCancelActionLabel(__('shared::chrome.close')),
            ])
            ->emptyStateHeading(__('people::consent.family.none'))
            ->paginated([25, 50]);
    }

    /** @return list<array{state: string, who: string, when: ?string, note: ?string}> */
    private function history(string $studentId, callable $day): array
    {
        $names = fn (array $ids) => DB::table('users')->whereIn('id', array_filter($ids))->pluck('name', 'id');
        $consents = GuardianConsent::query()->where('student_id', $studentId)->orderByDesc('granted_at')->get();
        $who = $names($consents->flatMap(fn ($c) => [$c->guardian_id, $c->recorded_by, $c->withdrawn_by])->all());
        $rows = [];
        foreach ($consents as $c) {
            if ($c->withdrawn_at) {
                $rows[] = ['state' => __('people::consent.state.withdrawn'), 'who' => (string) ($who[$c->withdrawn_by] ?? ''), 'when' => $day($c->withdrawn_at), 'note' => $c->withdraw_note];
            }
            $rows[] = [
                'state' => __('people::consent.state.granted').' · '.__('people::consent.via.'.$c->via),
                'who' => (string) ($who[$c->guardian_id] ?? $who[$c->recorded_by] ?? ''),
                'when' => $day($c->granted_at),
                'note' => $c->note,
            ];
        }

        return $rows;
    }
}
