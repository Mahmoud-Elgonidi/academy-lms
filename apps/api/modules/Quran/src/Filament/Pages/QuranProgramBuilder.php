<?php

namespace Modules\Quran\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Modules\Quran\Actions\Programs\SaveQuranProgram;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Domain\Grading\Grades;
use Modules\Quran\Domain\Programs\Presets;
use Modules\Quran\Domain\Programs\ProgramRules;
use Modules\Quran\Filament\Concerns\QuranMenu;
use Modules\Quran\Filament\Concerns\Refusals;
use Modules\Quran\Hifz\HifzPages;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Models\QuranProgram;
use Modules\Quran\Models\QuranSettings;
use Modules\Quran\Programs\JuzStrip;
use Modules\Quran\Programs\WeekPreview;
use Modules\Quran\Support\Amounts;
use Modules\SharedKernel\Authorization\Ability;

/**
 * The Quran program builder (docs/architecture/02, section 26): the track as
 * cards, the scope on the strip of the mushaf's parts (by parts in a tap, or
 * surah and verse) and which way it goes, the new amount a lesson and the
 * near and far reviews as sliders, the pass grade, exams and certificate;
 * beside it, what a typical week would hold, worked out by LessonPortion on
 * the server as the fields change. A program learners follow keeps its
 * shape (track, scope, direction, unit) and says why; a copy takes another.
 * Saved only through SaveQuranProgram; archived, never deleted.
 */
final class QuranProgramBuilder extends Page
{
    use QuranMenu;

    public const FIELDS = ['name', 'track', 'scope_from_surah', 'scope_from_verse', 'scope_to_surah', 'scope_to_verse', 'direction', 'unit',
        'new_amount', 'near_portions', 'far_pages', 'pass_grade', 'exam_stops', 'counts_for_certificate'];

    protected string $view = 'quran::filament.admin.builder';

    protected static ?string $slug = 'quran/programs/edit';

    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public ?string $program = null;

    #[Url]
    public ?string $preset = null;

    /** @var array<string, mixed> */
    public array $data = [];

    /** Lessons a week in the preview. */
    public int $perWeek = 3;

    /** What the learner in the preview already knows: none, or the last part (Juz Amma). */
    public string $known = 'none';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageQuran->value);
    }

    public function mount(): void
    {
        $found = $this->found();
        if ($this->program !== null && $found === null) {
            abort(404);
        }
        if ($found !== null) {
            $this->data = $found->only(self::FIELDS);
            $this->preset = null;
        } else {
            $this->data = $this->starting($this->preset);
        }
        $this->known = in_array($this->data['track'], ['review', 'tilawa'], true) ? 'juz30' : 'none';
    }

    public function getTitle(): string|Htmlable
    {
        $found = $this->found();

        return $found !== null ? $found->name : __('quran::screens.builder.new_title');
    }

    public function getBreadcrumbs(): array
    {
        return [QuranPrograms::getUrl() => __('quran::screens.programs.title'), __('quran::screens.builder.crumb')];
    }

    // ---- What the builder shows. ----

    /** Whether learners follow it (its shape is then kept), and how many. */
    #[Computed]
    public function followers(): int
    {
        return $this->program === null ? 0 : QuranPlan::query()->where('program_id', $this->program)->count();
    }

    public function locked(): bool
    {
        return $this->followers > 0;
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function preview(): array
    {
        $known = $this->known === 'juz30' ? $this->lastPartPages() : [];

        return app(WeekPreview::class)->of(array_intersect_key($this->data, array_flip(self::FIELDS)), $this->perWeek, $known);
    }

    /** @return ?array<string, mixed> */
    #[Computed]
    public function strip(): ?array
    {
        $d = $this->data;
        if (($d['track'] ?? null) === 'noorani' || ! is_numeric($d['scope_from_surah'] ?? null) || ! is_numeric($d['scope_to_surah'] ?? null)) {
            return null;
        }

        return app(JuzStrip::class)->scope((int) $d['scope_from_surah'], (int) $d['scope_from_verse'], (int) $d['scope_to_surah'], (int) $d['scope_to_verse'], (string) $d['direction']);
    }

    /** @return array<int, array{number: int, name: string, verses: int}> */
    #[Computed]
    public function surahs(): array
    {
        return collect(app(QuranApi::class)->surahs())->mapWithKeys(fn (array $s) => [$s['number'] => [
            'number' => $s['number'], 'name' => app()->getLocale() === 'ar' ? $s['name_ar'] : $s['name_en'], 'verses' => $s['verses'],
        ]])->all();
    }

    public function parts(): int
    {
        return app(JuzStrip::class)->count();
    }

    /** @return array{0: float, 1: float, 2: float} the least, the most and the step of the new amount in the unit */
    public function amountRange(): array
    {
        $unit = ($this->data['track'] ?? null) === 'noorani' ? 'lesson' : (string) ($this->data['unit'] ?? 'page');

        return ProgramRules::AMOUNTS[$unit] ?? ProgramRules::AMOUNTS['page'];
    }

    public function amountLabel(): string
    {
        $unit = ($this->data['track'] ?? null) === 'noorani' ? 'lesson' : (string) ($this->data['unit'] ?? 'page');

        return is_numeric($this->data['new_amount'] ?? null) ? Amounts::of((float) $this->data['new_amount'], $unit) : '—';
    }

    /** @return list<string> */
    public function tracks(): array
    {
        return ProgramRules::TRACKS;
    }

    /** @return list<string> */
    public function grades(): array
    {
        return Grades::PASSING;
    }

    public function textAvailable(): bool
    {
        return app(JuzStrip::class)->available();
    }

    // ---- Changing it. ----

    public function updatedData(mixed $value, string $key): void
    {
        if ($key === 'track') {
            $this->setTrack((string) $value);
        }
        // A surah chosen for an edge of the scope: its first verse at the start, its last at the end.
        if ($key === 'scope_from_surah') {
            $this->data['scope_from_verse'] = 1;
        }
        if ($key === 'scope_to_surah') {
            $this->data['scope_to_verse'] = $this->surahs[(int) $value]['verses'] ?? 1;
        }
        if ($key === 'unit') {
            [$min, $max] = $this->amountRange();
            $this->data['new_amount'] = min($max, max($min, (float) ($this->data['new_amount'] ?? $min)));
        }
        unset($this->preview, $this->strip);
    }

    public function updatedPerWeek(): void
    {
        $this->perWeek = max(1, min(WeekPreview::MAX_LESSONS, $this->perWeek));
        unset($this->preview);
    }

    public function updatedKnown(): void
    {
        unset($this->preview);
    }

    /** A track changes what the rest may be: the Noorani Qaida has lessons, review nothing new, neither exams. */
    public function setTrack(string $track): void
    {
        if ($this->locked() || ! in_array($track, ProgramRules::TRACKS, true)) {
            return;
        }
        $this->data['track'] = $track;
        if ($track === 'noorani') {
            $this->data = array_merge($this->data, ['unit' => 'lesson', 'direction' => 'forward', 'scope_from_surah' => null, 'scope_from_verse' => 1,
                'scope_to_surah' => null, 'scope_to_verse' => Presets::NOORANI_LESSONS, 'new_amount' => 1, 'near_portions' => 0, 'far_pages' => 0, 'exam_stops' => 'none']);
        } elseif (($this->data['unit'] ?? null) === 'lesson' || $this->data['scope_from_surah'] === null) {
            $last = collect($this->surahs)->last();
            $this->data = array_merge($this->data, ['unit' => 'page', 'scope_from_surah' => 1, 'scope_from_verse' => 1, 'scope_to_surah' => $last['number'] ?? 114,
                'scope_to_verse' => $last['verses'] ?? 6, 'new_amount' => 0.5]);
        }
        if ($track === 'review') {
            $this->data = array_merge($this->data, ['new_amount' => 0, 'near_portions' => 0, 'far_pages' => max(1, (int) $this->data['far_pages']), 'exam_stops' => 'none']);
            $this->known = 'juz30';
        } elseif ((float) ($this->data['new_amount'] ?? 0) <= 0) {
            $this->data['new_amount'] = $this->amountRange()[0];
        }
        unset($this->preview, $this->strip);
    }

    /** The scope as whole parts, from one to another. */
    public function scopeParts(int $from, int $to): void
    {
        if ($this->locked()) {
            return;
        }
        $bounds = app(JuzStrip::class)->bounds(min($from, $to), max($from, $to));
        if ($bounds === null) {
            return;
        }
        $this->data = array_merge($this->data, [
            'scope_from_surah' => $bounds['from']['surah'], 'scope_from_verse' => $bounds['from']['verse'],
            'scope_to_surah' => $bounds['to']['surah'], 'scope_to_verse' => $bounds['to']['verse'],
        ]);
        unset($this->preview, $this->strip);
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->resetErrorBag();
        $saved = Refusals::under(fn () => app(SaveQuranProgram::class)->handle($this->found(), $this->data + ($this->preset !== null ? ['preset' => $this->preset] : []), (string) auth()->id()),
            self::FIELDS, 'data');
        $new = $this->program === null;
        Notification::make()->success()->title(__($new ? 'quran::screens.builder.created' : 'quran::screens.builder.saved', ['name' => $saved->name]))->send();
        if ($new) {
            $this->redirect(self::getUrl(['program' => $saved->id]), navigate: true);
        }
    }

    protected function getHeaderActions(): array
    {
        $b = 'quran::screens.builder.';

        return [
            Action::make('copy')->label(__($b.'copy'))->icon(Heroicon::OutlinedDocumentDuplicate)->color('gray')
                ->visible(fn () => $this->program !== null)
                ->requiresConfirmation()->modalDescription(__($b.'copy_hint'))
                ->action(function () use ($b) {
                    $copy = app(SaveQuranProgram::class)->handle(null, ['name' => Str::limit(__($b.'copy_name', ['name' => $this->data['name']]), ProgramRules::NAME_MAX, '')]
                        + array_intersect_key($this->found()?->only(self::FIELDS) ?? $this->data, array_flip(self::FIELDS)), (string) auth()->id());
                    Notification::make()->success()->title(__($b.'copied'))->send();
                    $this->redirect(self::getUrl(['program' => $copy->id]), navigate: true);
                }),
            Action::make('archive')->label(__($b.'archive'))->icon(Heroicon::OutlinedArchiveBox)->color('gray')
                ->visible(fn () => $this->found()?->archived_at === null && $this->program !== null)
                ->requiresConfirmation()->modalDescription(__($b.'archive_hint'))
                ->action(function () use ($b) {
                    app(SaveQuranProgram::class)->archive($this->found() ?? abort(404));
                    Notification::make()->success()->title(__($b.'archived'))->send();
                    $this->redirect(QuranPrograms::getUrl(), navigate: true);
                }),
            Action::make('restore')->label(__('quran::screens.programs.restore'))->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')
                ->visible(fn () => $this->found()?->archived_at !== null)
                ->action(function () {
                    app(SaveQuranProgram::class)->archive($this->found() ?? abort(404), false);
                    Notification::make()->success()->title(__('quran::screens.programs.restored', ['name' => $this->found()?->name]))->send();
                }),
        ];
    }

    private function found(): ?QuranProgram
    {
        return $this->program !== null && Str::isUuid($this->program) ? QuranProgram::query()->find($this->program) : null;
    }

    /** @return array<string, mixed> a new program's fields: a ready track's, else the academy's defaults over the whole Quran from an-Nas */
    private function starting(?string $preset): array
    {
        if ($preset !== null && in_array($preset, Presets::keys(), true)) {
            return ['name' => __('quran::programs.presets.'.$preset.'.name')] + Presets::get($preset);
        }
        $this->preset = null;
        $s = QuranSettings::current();
        $unit = in_array($s->default_unit, ProgramRules::UNITS, true) ? $s->default_unit : 'page';
        $last = collect(app(QuranApi::class)->surahs())->last();

        return ['name' => '', 'counts_for_certificate' => true] + array_merge(Presets::get('full_backward'), [
            'unit' => $unit, 'new_amount' => $s->default_new_amount, 'near_portions' => $s->default_near_portions,
            'far_pages' => $s->default_far_pages, 'pass_grade' => $s->memorization_threshold,
            'scope_to_surah' => $last['number'] ?? 114, 'scope_to_verse' => $last['verses'] ?? 6,
        ]);
    }

    /** @return list<int> the mushaf's last part's pages */
    private function lastPartPages(): array
    {
        $pages = app(HifzPages::class);
        $last = app(JuzStrip::class)->count();

        return array_values(array_map(fn (array $p) => $p['page'], array_filter($pages->layout(), fn (array $p) => $p['juz'] === $last)));
    }
}
