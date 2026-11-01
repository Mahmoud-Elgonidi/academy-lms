<?php

namespace Modules\Quran\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Quran\Actions\Certificates\RevokeQuranCertificate;
use Modules\Quran\Certificates\CertificateLinks;
use Modules\Quran\Certificates\Certificates;
use Modules\Quran\Domain\Certificates\CertificateNumber;
use Modules\Quran\Filament\Concerns\QuranMenu;
use Modules\Quran\Filament\Concerns\Refusals;
use Modules\Quran\Models\QuranCertificate;
use Modules\Quran\Models\QuranSettings;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * Quran › Certificates (docs/architecture/02, section 26): every
 * certificate the academy issued, newest first, with its number, learner,
 * portion, grade and examiner; its page opens signed for the viewer, to read
 * and print; the office revokes one once, with a reason (never shown on
 * the public verification). A certificate is issued by a passed exam.
 */
final class QuranCertificates extends Page
{
    use QuranMenu;

    protected string $view = 'quran::filament.admin.certificates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?int $navigationSort = 45;

    protected static ?string $slug = 'quran/certificates';

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewQuranBoard->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('quran::screens.certificates.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('quran::screens.certificates.title');
    }

    public function getSubheading(): ?string
    {
        return __('quran::screens.certificates.subtitle');
    }

    /** @return array{rows: list<array<string, mixed>>, enabled: bool, counts: array{all: int, revoked: int}} */
    #[Computed]
    public function certificates(): array
    {
        $names = app(Certificates::class);
        $tz = app(ViewerTimezone::class)->effective();
        $search = trim($this->search);
        $rows = QuranCertificate::query()
            ->when($this->status === 'valid', fn ($q) => $q->whereNull('revoked_at'))
            ->when($this->status === 'revoked', fn ($q) => $q->whereNotNull('revoked_at'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('number', 'ilike', '%'.addcslashes($search, '%_\\').'%')
                ->orWhereIn('student_id', fn ($u) => $u->select('id')->from('users')->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%'))))
            ->latest('issued_at')->limit(200)->get()
            ->map(fn (QuranCertificate $c) => [
                'id' => $c->id,
                'number' => $c->number,
                'title' => $names->title($c),
                'student' => $names->name($c->student_id),
                'portion' => $names->portion($c),
                'program' => (string) ($c->portion['program'] ?? ''),
                'grade' => $c->grade,
                'grade_label' => __('quran::programs.grades.'.$c->grade),
                'examiner' => $names->name($c->examiner_id),
                'day' => $c->issued_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y'),
                'revoked' => $c->isRevoked(),
                'revoked_day' => $c->revoked_at?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y'),
                'code' => CertificateNumber::display($c->code),
            ])->all();

        return [
            'rows' => $rows,
            'enabled' => QuranSettings::current()->certificates_enabled,
            'counts' => ['all' => QuranCertificate::query()->count(), 'revoked' => QuranCertificate::query()->whereNotNull('revoked_at')->count()],
        ];
    }

    public function openAction(): Action
    {
        return Action::make('open')->label(__('quran::screens.certificates.open'))->icon(Heroicon::OutlinedArrowTopRightOnSquare)->link()->size('sm')
            ->url(fn (array $arguments) => ($c = $this->certificate($arguments)) === null ? null : app(CertificateLinks::class)->page($c, (string) auth()->id()))
            ->openUrlInNewTab();
    }

    public function revokeAction(): Action
    {
        $w = 'quran::screens.certificates.';

        return Action::make('revoke')->label(__($w.'revoke'))->icon(Heroicon::OutlinedNoSymbol)->color('danger')->link()->size('sm')
            ->authorize(fn () => Gate::allows(Ability::ManageQuran->value))
            ->visible(fn (array $arguments) => ($c = $this->certificate($arguments)) !== null && ! $c->isRevoked())
            ->modalHeading(fn (array $arguments) => __($w.'revoke_heading', ['number' => $this->certificate($arguments)?->number]))
            ->modalDescription(__($w.'revoke_hint'))->modalIcon(Heroicon::OutlinedExclamationTriangle)->modalIconColor('danger')->modalWidth(Width::Medium)
            ->modalSubmitActionLabel(__($w.'revoke_submit'))
            ->schema([Textarea::make('reason')->label(__('quran::certificates.attributes.reason'))->required()->minLength(3)->maxLength(300)->rows(3)])
            ->action(function (array $data, array $arguments) use ($w) {
                $certificate = $this->certificate($arguments) ?? abort(404);
                Refusals::under(fn () => app(RevokeQuranCertificate::class)->handle($certificate, (string) auth()->id(), (string) $data['reason']), ['reason']);
                unset($this->certificates);
                Notification::make()->success()->title(__($w.'revoked', ['number' => $certificate->number]))->send();
            });
    }

    private function certificate(array $arguments): ?QuranCertificate
    {
        $id = (string) ($arguments['certificate'] ?? '');

        return Str::isUuid($id) ? QuranCertificate::query()->find($id) : null;
    }
}
