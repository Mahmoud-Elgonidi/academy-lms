<?php

namespace Modules\People\Filament\Pages;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\People\Actions\Checks\AddTeacherCheck;
use Modules\People\Actions\Checks\DecideTeacherCheck;
use Modules\People\Actions\Checks\PauseTeacherForMinors;
use Modules\People\Actions\Checks\ResumeTeacherForMinors;
use Modules\People\Actions\Checks\SaveMinorCheckRule;
use Modules\People\Contracts\CheckKind;
use Modules\People\Live\TeacherChecksChanged;
use Modules\People\Models\TeacherCheck;
use Modules\People\Queries\TeacherChecksBoard;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Tenancy\TenantContext;
use UnitEnum;

/**
 * Teachers' vetting, for the academy's safeguarding leads (docs/architecture/02,
 * section 15): documents waiting to be checked against the original, checks
 * that run out within a month, and every teacher's standing with students
 * under 18 (cleared, waiting, run out, missing, paused). The academy's rule
 * (a verified criminal record check before teaching minors) is set here.
 */
final class TeacherChecks extends Page
{
    protected string $view = 'people::filament.teacher-checks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 41;

    protected static ?string $slug = 'teacher-checks';

    /** The teacher a link pointed to, shown first. */
    #[Url]
    public ?string $teacher = null;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::HandleSafeguarding->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('people::directory.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('people::checks.office.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = TeacherCheck::query()->where('status', 'pending')->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTitle(): string|Htmlable
    {
        return __('people::checks.office.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('people::checks.office.lead');
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function board(): array
    {
        return app(TeacherChecksBoard::class)->forAcademy() + ['channel' => TeacherChecksChanged::channel(app(TenantContext::class)->require()->id)];
    }

    public function day(?CarbonImmutable $day): ?string
    {
        return $day?->locale(app()->getLocale())->translatedFormat('j M Y');
    }

    protected function getHeaderActions(): array
    {
        $l = 'people::checks.office.';

        return [
            Action::make('add')->label(__($l.'actions.add'))->icon(Heroicon::OutlinedDocumentPlus)
                ->modalDescription(__($l.'actions.add_hint'))->modalWidth(Width::Large)
                ->schema([
                    Select::make('teacher')->label(__($l.'teacher'))->required()->native(false)->searchable()
                        ->options(fn () => collect($this->board['teachers'])->pluck('name', 'id')->all()),
                    ...self::documentFields(),
                    Toggle::make('verify')->label(__($l.'actions.verify_now'))->helperText(__($l.'actions.verify_now_hint')),
                ])
                ->action(function (array $data) use ($l) {
                    $file = is_array($data['file'] ?? null) ? collect($data['file'])->first() : $data['file'];
                    app(AddTeacherCheck::class)->handle((string) $data['teacher'], (string) $data['kind'], $data['reference'] ?? null,
                        self::date($data['issued_on'] ?? null), self::date($data['expires_on'] ?? null), $file, (string) auth()->id(), (bool) ($data['verify'] ?? false));
                    unset($this->board);
                    Notification::make()->success()->title(__($l.'done.added'))->send();
                }),
            Action::make('rule')->label(__($l.'actions.rule'))->icon(Heroicon::OutlinedAdjustmentsHorizontal)->color('gray')
                ->modalDescription(__($l.'actions.rule_hint'))->modalWidth(Width::Large)
                ->fillForm(fn () => ['required' => $this->board['required']])
                ->schema([Toggle::make('required')->label(__($l.'actions.rule_toggle'))])
                ->action(function (array $data) use ($l) {
                    app(SaveMinorCheckRule::class)->handle((bool) $data['required']);
                    unset($this->board);
                    Notification::make()->success()->title(__($l.'done.rule'))->send();
                }),
        ];
    }

    /** @return list<Field> the document's own fields (the teacher's page asks the same) */
    public static function documentFields(): array
    {
        $f = 'people::checks.fields.';

        return [
            Select::make('kind')->label(__($f.'kind'))->required()->native(false)->options(CheckKind::options())->default(CheckKind::CriminalRecord->value),
            TextInput::make('reference')->label(__($f.'reference'))->maxLength(120),
            DatePicker::make('issued_on')->label(__($f.'issued_on'))->native(false)->maxDate(now()->addDay()),
            DatePicker::make('expires_on')->label(__($f.'expires_on'))->native(false)->helperText(__('people::checks.office.expires_hint')),
            FileUpload::make('file')->label(__($f.'file'))->required()->storeFiles(false)->acceptedFileTypes(TeacherCheck::MIMES)->maxSize(TeacherCheck::MAX_KB),
        ];
    }

    public function verifyAction(): Action
    {
        $l = 'people::checks.office.';

        return Action::make('verify')->label(__($l.'actions.verify'))->icon(Heroicon::OutlinedCheck)->color('success')->size('sm')
            ->modalDescription(__($l.'actions.verify_hint'))->modalWidth(Width::Medium)
            ->fillForm(fn (array $arguments) => ['expires_on' => TeacherCheck::query()->find($arguments['id'] ?? '')?->expires_on?->toDateString()])
            ->schema([DatePicker::make('expires_on')->label(__('people::checks.fields.expires_on'))->native(false)->helperText(__($l.'expires_hint'))])
            ->action(function (array $arguments, array $data) use ($l) {
                app(DecideTeacherCheck::class)->handle((string) ($arguments['id'] ?? ''), true, self::date($data['expires_on'] ?? null), null, (string) auth()->id());
                unset($this->board);
                Notification::make()->success()->title(__($l.'done.verified'))->send();
            });
    }

    public function rejectAction(): Action
    {
        $l = 'people::checks.office.';

        return Action::make('reject')->label(__($l.'actions.reject'))->icon(Heroicon::OutlinedXMark)->color('gray')->size('sm')
            ->modalDescription(__($l.'actions.reject_hint'))->modalWidth(Width::Medium)
            ->schema([Textarea::make('note')->label(__('people::checks.fields.reason'))->required()->rows(3)->minLength(5)->maxLength(500)])
            ->action(function (array $arguments, array $data) use ($l) {
                app(DecideTeacherCheck::class)->handle((string) ($arguments['id'] ?? ''), false, null, (string) $data['note'], (string) auth()->id());
                unset($this->board);
                Notification::make()->success()->title(__($l.'done.rejected'))->send();
            });
    }

    public function pauseAction(): Action
    {
        $l = 'people::checks.office.';

        return Action::make('pause')->label(__($l.'actions.pause'))->icon(Heroicon::OutlinedPauseCircle)->color('danger')->size('sm')->link()
            ->modalHeading(fn (array $arguments) => __($l.'actions.pause_heading', ['name' => DB::table('users')->where('id', $arguments['id'] ?? '')->value('name')]))
            ->modalDescription(__($l.'actions.pause_hint'))->modalWidth(Width::Medium)
            ->schema([Textarea::make('reason')->label(__('people::checks.fields.reason'))->required()->rows(2)->minLength(5)->maxLength(500)])
            ->action(function (array $arguments, array $data) use ($l) {
                app(PauseTeacherForMinors::class)->handle((string) ($arguments['id'] ?? ''), (string) $data['reason'], (string) auth()->id());
                unset($this->board);
                Notification::make()->success()->title(__($l.'done.paused'))->send();
            });
    }

    public function resumeAction(): Action
    {
        $l = 'people::checks.office.';

        return Action::make('resume')->label(__($l.'actions.resume'))->icon(Heroicon::OutlinedPlayCircle)->color('gray')->size('sm')->link()
            ->modalDescription(__($l.'actions.resume_hint'))->modalWidth(Width::Medium)
            ->schema([Textarea::make('reason')->label(__('people::checks.fields.reason'))->required()->rows(2)->minLength(5)->maxLength(500)])
            ->action(function (array $arguments, array $data) use ($l) {
                app(ResumeTeacherForMinors::class)->handle((string) ($arguments['id'] ?? ''), (string) $data['reason'], (string) auth()->id());
                unset($this->board);
                Notification::make()->success()->title(__($l.'done.resumed'))->send();
            });
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        return filled($value) ? CarbonImmutable::parse((string) $value) : null;
    }
}
