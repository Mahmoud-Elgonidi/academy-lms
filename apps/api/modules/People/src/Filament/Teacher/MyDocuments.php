<?php

namespace Modules\People\Filament\Teacher;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\People\Actions\Checks\AddTeacherCheck;
use Modules\People\Checks\Clearances;
use Modules\People\Filament\Pages\TeacherChecks;
use Modules\People\Queries\TeacherChecksBoard;

/**
 * "My documents" in /teach: the documents the teacher's vetting rests on (a
 * criminal record check above all), where each stands, and why one was
 * turned down; they send a new one here. Whether they may teach students
 * under 18 shows when the academy asks for a check (a pause is never shown:
 * that is the academy's to say).
 */
final class MyDocuments extends Page
{
    protected string $view = 'people::filament.my-documents';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 88;

    protected static ?string $slug = 'my-documents';

    public static function canAccess(): bool
    {
        return auth()->check() && DB::table('teacher_profiles')->where('user_id', auth()->id())->exists();
    }

    public static function getNavigationLabel(): string
    {
        return __('people::checks.mine.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('people::checks.mine.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('people::checks.mine.lead');
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function mine(): array
    {
        $id = (string) auth()->id();
        // A pause is the academy's to explain: the teacher sees only where their own documents stand.
        $clearance = app(Clearances::class)->of($id, withPause: false);

        return [
            'channel' => PersonalChannel::name($id),
            'checks' => app(TeacherChecksBoard::class)->ofTeacher($id),
            'required' => $clearance->required,
            'state' => $clearance->state,
            'until' => $clearance->expiresOn,
        ];
    }

    public function day(?CarbonImmutable $day): ?string
    {
        return $day?->locale(app()->getLocale())->translatedFormat('j M Y');
    }

    protected function getHeaderActions(): array
    {
        $l = 'people::checks.mine.';

        return [
            Action::make('send')->label(__($l.'send'))->icon(Heroicon::OutlinedArrowUpTray)
                ->modalDescription(__($l.'send_hint'))->modalWidth(Width::Large)
                ->schema(TeacherChecks::documentFields())
                ->action(function (array $data) use ($l) {
                    $file = is_array($data['file'] ?? null) ? collect($data['file'])->first() : $data['file'];
                    $me = (string) auth()->id();
                    app(AddTeacherCheck::class)->handle($me, (string) $data['kind'], $data['reference'] ?? null,
                        filled($data['issued_on'] ?? null) ? CarbonImmutable::parse((string) $data['issued_on']) : null,
                        filled($data['expires_on'] ?? null) ? CarbonImmutable::parse((string) $data['expires_on']) : null, $file, $me);
                    unset($this->mine);
                    Notification::make()->success()->title(__($l.'sent'))->send();
                }),
        ];
    }
}
