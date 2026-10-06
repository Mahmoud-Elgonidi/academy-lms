<?php

namespace Modules\Evaluation\Filament\Family;

use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Livewire;
use Modules\Evaluation\Actions\RateLesson;
use Modules\Evaluation\Models\LessonRating;

/**
 * Five stars under a lesson that took place in the family's recent lessons
 * (the "scheduling::family.lesson-card" hook): one tap rates it, then a
 * few quick words if they like. Each person rates for themselves.
 */
final class RateLessonChip extends Component
{
    public const HOOK = 'scheduling::family.lesson-card';

    public string $sessionId;

    public bool $open = false;

    public ?int $stars = null;

    /** @var list<string> */
    public array $tags = [];

    public bool $thanked = false;

    public static function register(): void
    {
        Livewire::component('evaluation.rate-chip', self::class);
        FilamentView::registerRenderHook(self::HOOK, fn (array $data) => Filament::getCurrentPanel()?->getId() === 'family' && ($data['lesson']['status'] ?? null) === 'completed'
            ? Blade::render('@livewire(\'evaluation.rate-chip\', [\'sessionId\' => $id], key(\'rate-\'.$id))', ['id' => $data['lesson']['id']])
            : '');
    }

    public function mount(string $sessionId): void
    {
        $this->sessionId = $sessionId;
        try {
            app(RateLesson::class)->check($sessionId, (string) auth()->id());
            $this->open = true;
        } catch (ValidationException) {
            return;
        }
        $rating = LessonRating::query()->where('session_id', $sessionId)->where('rater_id', auth()->id())->first();
        [$this->stars, $this->tags] = [$rating?->stars, $rating?->tags ?? []];
    }

    public function rate(int $stars): void
    {
        $this->save($stars, $this->tags);
        $this->thanked = true;
    }

    public function toggle(string $tag): void
    {
        if ($this->stars === null) {
            return;
        }
        $tags = in_array($tag, $this->tags, true) ? array_values(array_diff($this->tags, [$tag])) : [...$this->tags, $tag];
        $this->save($this->stars, $tags);
    }

    /** @return list<string> the quick words that fit the stars given */
    public function choices(): array
    {
        return $this->stars === null ? [] : LessonRating::TAGS[$this->stars >= 4 ? 'good' : 'bad'];
    }

    public function render(): View
    {
        return view('evaluation::filament.family.rate-chip');
    }

    /** @param  list<string>  $tags */
    private function save(int $stars, array $tags): void
    {
        $current = LessonRating::query()->where('session_id', $this->sessionId)->where('rater_id', auth()->id())->first();
        $fits = LessonRating::TAGS[$stars >= 4 ? 'good' : 'bad'];
        $rating = app(RateLesson::class)->handle($this->sessionId, (string) auth()->id(), $stars, array_values(array_intersect($tags, $fits)), $current?->comment);
        [$this->stars, $this->tags] = [$rating->stars, $rating->tags];
    }
}
