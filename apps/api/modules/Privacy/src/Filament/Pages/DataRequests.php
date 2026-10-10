<?php

namespace Modules\Privacy\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Privacy\Actions\AnswerCorrection;
use Modules\Privacy\Actions\ApproveErasure;
use Modules\Privacy\Actions\CancelDataRequest;
use Modules\Privacy\Actions\RefuseErasure;
use Modules\Privacy\Actions\RequestCorrection;
use Modules\Privacy\Actions\RequestDataExport;
use Modules\Privacy\Actions\RequestErasure;
use Modules\Privacy\Actions\SavePrivacySettings;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\Models\PrivacySettings;
use Modules\Privacy\Queries\DataRequests as Requests;
use Modules\Privacy\Queries\ErasurePreview;
use Modules\Privacy\Subjects;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * People's requests about their data, for the owner and admins: erasures
 * and corrections waiting for an answer (oldest first, late ones marked; an
 * erasure with what still holds it back), approved erasures in their last
 * day, and everything of
 * the last 90 days (a copy can be downloaded here). A request made by phone
 * or in person is written down here for the person; the academy's details
 * on its privacy notice and terms are set here too.
 */
final class DataRequests extends Page
{
    protected string $view = 'privacy::filament.data-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static ?int $navigationSort = 95;

    protected static ?string $slug = 'data-requests';

    /** The request a message pointed to, shown first. */
    #[Url]
    public ?string $request = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::HandleDataRequests->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('privacy::screens.office.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $open = DataRequest::query()->whereIn('kind', DataRequest::ANSWERED)->where('status', 'pending')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('privacy::screens.office.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('privacy::screens.office.lead', ['days' => DataRequest::ANSWER_DAYS]);
    }

    /** @return array{open: list<array<string, mixed>>, recent: list<array<string, mixed>>} */
    #[Computed]
    public function board(): array
    {
        $board = app(Requests::class)->forAcademy();
        if ($this->request !== null) {
            usort($board['open'], fn ($a, $b) => ($b['id'] === $this->request) <=> ($a['id'] === $this->request));
        }

        return $board;
    }

    /** @return array{goes: list<string>, stays: list<string>} */
    public function outcome(): array
    {
        return [
            'goes' => array_map(fn (string $k) => __("privacy::requests.goes.{$k}"), ErasurePreview::GOES),
            'stays' => array_map(fn (string $k) => __("privacy::requests.stays.{$k}"), ErasurePreview::STAYS),
        ];
    }

    public function when(mixed $at, bool $time = false): ?string
    {
        return $at === null ? null : CarbonImmutable::parse($at)->setTimezone(app(ViewerTimezone::class)->effective())
            ->locale(app()->getLocale())->translatedFormat($time ? 'l j M · H:i' : 'j M Y');
    }

    protected function getHeaderActions(): array
    {
        $l = 'privacy::screens.office.';

        return [
            Action::make('record')->label(__($l.'actions.record'))->icon(Heroicon::OutlinedPencilSquare)
                ->modalDescription(__($l.'actions.record_hint'))->modalWidth(Width::Large)
                ->schema([
                    Select::make('person')->label(__($l.'actions.person'))->required()->searchable()->native(false)
                        ->getSearchResultsUsing(fn (string $search) => DB::table('users')->where('status', '!=', 'erased')
                            ->where(fn ($q) => $q->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%')->orWhere('email', 'ilike', '%'.addcslashes($search, '%_\\').'%'))
                            ->orderBy('name')->limit(20)->pluck('name', 'id')->all())
                        ->getOptionLabelUsing(fn ($value) => DB::table('users')->where('id', $value)->value('name')),
                    Select::make('kind')->label(__($l.'actions.kind'))->required()->native(false)->live()
                        ->options(collect([DataRequest::EXPORT, DataRequest::ERASURE, DataRequest::CORRECTION])->mapWithKeys(fn (string $k) => [$k => __("privacy::requests.kinds.{$k}")])->all()),
                    Textarea::make('reason')->label(__('privacy::requests.reason'))->rows(2)->maxLength(RequestErasure::REASON_MAX)
                        ->visible(fn ($get) => $get('kind') === DataRequest::ERASURE),
                    Textarea::make('says')->label(__('privacy::requests.says'))->rows(3)->required()
                        ->minLength(RequestCorrection::SAYS_MIN)->maxLength(RequestCorrection::SAYS_MAX)
                        ->visible(fn ($get) => $get('kind') === DataRequest::CORRECTION),
                ])
                ->action(function (array $data) use ($l) {
                    $me = (string) auth()->id();
                    match ($data['kind']) {
                        DataRequest::EXPORT => app(RequestDataExport::class)->handle((string) $data['person'], $me, 'staff'),
                        DataRequest::CORRECTION => app(RequestCorrection::class)->handle((string) $data['person'], $me, 'staff', (string) $data['says']),
                        default => app(RequestErasure::class)->handle((string) $data['person'], $me, 'staff', $data['reason'] ?? null),
                    };
                    unset($this->board);
                    Notification::make()->success()->title(__($l.'done.recorded'))->send();
                }),
            Action::make('settings')->label(__($l.'actions.settings'))->icon(Heroicon::OutlinedDocumentText)->color('gray')
                ->modalDescription(__($l.'actions.settings_hint'))->modalWidth(Width::TwoExtraLarge)
                ->fillForm(fn () => PrivacySettings::current()->only(['legal_name', 'contact_email', 'address', 'privacy_extra', 'terms_extra']))
                ->schema([
                    TextInput::make('legal_name')->label(__($l.'settings.legal_name'))->helperText(__($l.'settings.legal_name_hint'))->maxLength(200),
                    TextInput::make('contact_email')->label(__($l.'settings.contact_email'))->email()->maxLength(200),
                    TextInput::make('address')->label(__($l.'settings.address'))->maxLength(300),
                    Textarea::make('privacy_extra')->label(__($l.'settings.privacy_extra'))->rows(4)->maxLength(5000),
                    Textarea::make('terms_extra')->label(__($l.'settings.terms_extra'))->rows(4)->maxLength(5000),
                ])
                ->action(function (array $data) use ($l) {
                    app(SavePrivacySettings::class)->handle($data, (string) auth()->id());
                    Notification::make()->success()->title(__($l.'done.saved'))->send();
                }),
            Action::make('pages')->label(__($l.'actions.pages'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn () => app(Requests::class)->legalLinks()['privacy'], shouldOpenInNewTab: true),
        ];
    }

    public function approveAction(): Action
    {
        $l = 'privacy::screens.office.';

        return Action::make('approve')->label(__($l.'actions.approve'))->icon(Heroicon::OutlinedCheck)->color('danger')->size('sm')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => __($l.'actions.approve_heading', ['name' => Subjects::name($this->find($arguments)->subject_id)]))
            ->modalDescription(__($l.'actions.approve_hint'))
            ->action(function (array $arguments) use ($l) {
                $done = app(ApproveErasure::class)->handle($this->find($arguments), (string) auth()->id());
                unset($this->board);
                Notification::make()->success()->title(__($l.'done.approved', ['when' => $this->when($done->erase_after, true)]))->send();
            });
    }

    public function refuseAction(): Action
    {
        $l = 'privacy::screens.office.';

        return Action::make('refuse')->label(__($l.'actions.refuse'))->icon(Heroicon::OutlinedXMark)->color('gray')->size('sm')
            ->modalDescription(__($l.'actions.refuse_hint'))->modalWidth(Width::Large)
            ->schema([Textarea::make('answer')->label(__($l.'actions.answer'))->required()->rows(3)->maxLength(RefuseErasure::ANSWER_MAX)])
            ->action(function (array $arguments, array $data) use ($l) {
                app(RefuseErasure::class)->handle($this->find($arguments), (string) auth()->id(), (string) $data['answer']);
                unset($this->board);
                Notification::make()->success()->title(__($l.'done.refused'))->send();
            });
    }

    /** A correction made where the data lives (the person's page, a credit note, a report), with a note for whoever asked. */
    public function correctedAction(): Action
    {
        $l = 'privacy::screens.office.';

        return Action::make('corrected')->label(__($l.'actions.corrected'))->icon(Heroicon::OutlinedCheck)->color('primary')->size('sm')
            ->modalDescription(__($l.'actions.corrected_hint'))->modalWidth(Width::Large)->modalSubmitActionLabel(__($l.'actions.corrected'))
            ->schema([Textarea::make('answer')->label(__($l.'actions.note'))->rows(3)->maxLength(AnswerCorrection::ANSWER_MAX)])
            ->action(function (array $arguments, array $data) use ($l) {
                app(AnswerCorrection::class)->done($this->find($arguments), (string) auth()->id(), $data['answer'] ?? null);
                unset($this->board);
                Notification::make()->success()->title(__($l.'done.corrected'))->send();
            });
    }

    public function refuseCorrectionAction(): Action
    {
        $l = 'privacy::screens.office.';

        return Action::make('refuseCorrection')->label(__($l.'actions.refuse'))->icon(Heroicon::OutlinedXMark)->color('gray')->size('sm')
            ->modalDescription(__($l.'actions.refuse_correction_hint'))->modalWidth(Width::Large)->modalSubmitActionLabel(__($l.'actions.refuse'))
            ->schema([Textarea::make('answer')->label(__($l.'actions.answer'))->required()->rows(3)->maxLength(AnswerCorrection::ANSWER_MAX)])
            ->action(function (array $arguments, array $data) use ($l) {
                app(AnswerCorrection::class)->refuse($this->find($arguments), (string) auth()->id(), (string) $data['answer']);
                unset($this->board);
                Notification::make()->success()->title(__($l.'done.refused'))->send();
            });
    }

    public function cancelAction(): Action
    {
        $l = 'privacy::screens.office.';

        return Action::make('cancel')->label(__($l.'actions.cancel'))->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')->size('sm')->link()
            ->requiresConfirmation()->modalDescription(__($l.'actions.cancel_hint'))
            ->action(function (array $arguments) use ($l) {
                app(CancelDataRequest::class)->handle($this->find($arguments), (string) auth()->id());
                unset($this->board);
                Notification::make()->success()->title(__($l.'done.cancelled'))->send();
            });
    }

    private function find(array $arguments): DataRequest
    {
        $request = app(Requests::class)->find((string) ($arguments['id'] ?? ''));
        abort_if($request === null, 404);

        return $request;
    }
}
