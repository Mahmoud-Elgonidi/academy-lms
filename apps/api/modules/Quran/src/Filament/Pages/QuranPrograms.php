<?php

namespace Modules\Quran\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Quran\Actions\Programs\SaveQuranProgram;
use Modules\Quran\Domain\Programs\Presets;
use Modules\Quran\Filament\Concerns\QuranMenu;
use Modules\Quran\Models\QuranProgram;
use Modules\Quran\Programs\JuzStrip;
use Modules\Quran\Support\Amounts;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Quran › Programs (docs/architecture/02, section 26): the academy's own
 * curriculum of the Quran, a card each (its track, its scope on the strip
 * of parts and which way it goes, its pace, how many learners follow it),
 * and the ready tracks as cards to start a new one from. A card opens the
 * builder; a program is archived, never deleted.
 */
final class QuranPrograms extends Page
{
    use QuranMenu;

    protected string $view = 'quran::filament.admin.programs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 43;

    protected static ?string $slug = 'quran/programs';

    #[Url]
    public bool $archived = false;

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageQuran->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('quran::screens.programs.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('quran::screens.programs.title');
    }

    public function getSubheading(): ?string
    {
        return __('quran::screens.programs.subtitle');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new')->label(__('quran::screens.programs.new'))->icon(Heroicon::Plus)->url(QuranProgramBuilder::getUrl()),
        ];
    }

    /** @return array{programs: list<array<string, mixed>>, archived: int, presets: list<array<string, mixed>>, text: bool} */
    #[Computed]
    public function catalog(): array
    {
        $strip = app(JuzStrip::class);
        $students = DB::table('quran_plans')->where('status', 'active')->groupBy('program_id')->selectRaw('program_id, count(*) as n')->pluck('n', 'program_id');
        $programs = QuranProgram::query()->when(! $this->archived, fn ($q) => $q->whereNull('archived_at'), fn ($q) => $q->whereNotNull('archived_at'))
            ->orderBy('name')->get();

        return [
            'text' => $strip->available(),
            'archived' => QuranProgram::query()->whereNotNull('archived_at')->count(),
            'programs' => $programs->map(fn (QuranProgram $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'track' => $p->track,
                'track_label' => __('quran::tracks.names.'.$p->track),
                'direction' => __('quran::programs.directions.'.$p->direction),
                'pace' => Amounts::pace($p->new_amount, $p->unit, $p->near_portions, $p->far_pages),
                'strip' => $strip->scope($p->scope_from_surah, $p->scope_from_verse, $p->scope_to_surah, $p->scope_to_verse, $p->direction),
                'lessons' => $p->track === 'noorani' ? __('quran::screens.programs.noorani_scope', ['from' => $p->scope_from_verse, 'to' => $p->scope_to_verse]) : null,
                'students' => (int) ($students[$p->id] ?? 0),
                'exams' => __('quran::programs.exam_stops.'.$p->exam_stops),
                'certificate' => $p->counts_for_certificate,
                'archived' => $p->archived_at !== null,
                'url' => QuranProgramBuilder::getUrl(['program' => $p->id]),
            ])->all(),
            'presets' => array_map(function (string $key) use ($strip) {
                $p = Presets::get($key);

                return [
                    'key' => $key,
                    'name' => __('quran::programs.presets.'.$key.'.name'),
                    'hint' => __('quran::programs.presets.'.$key.'.hint'),
                    'track' => $p['track'],
                    'track_label' => __('quran::tracks.names.'.$p['track']),
                    'strip' => $strip->scope($p['scope_from_surah'], $p['scope_from_verse'], $p['scope_to_surah'], $p['scope_to_verse'], $p['direction']),
                    'url' => QuranProgramBuilder::getUrl(['preset' => $key]),
                    // Every ready track but the Noorani Qaida reads the mushaf.
                    'needs_text' => $p['track'] !== 'noorani',
                ];
            }, Presets::keys()),
        ];
    }

    public function restoreAction(): Action
    {
        return Action::make('restore')->label(__('quran::screens.programs.restore'))->icon(Heroicon::OutlinedArrowUturnLeft)->link()->size('sm')
            ->authorize(fn () => self::canAccess())
            ->action(function (array $arguments) {
                $id = (string) ($arguments['program'] ?? '');
                $program = (Str::isUuid($id) ? QuranProgram::query()->find($id) : null) ?? abort(404);
                app(SaveQuranProgram::class)->archive($program, false);
                unset($this->catalog);
                Notification::make()->success()->title(__('quran::screens.programs.restored', ['name' => $program->name]))->send();
            });
    }
}
