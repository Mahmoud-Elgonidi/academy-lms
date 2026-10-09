<?php

namespace Modules\Safeguarding\Filament\Concerns;

use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\Safeguarding\Actions\ReportConcern;
use Modules\Safeguarding\ConcernRules;
use Modules\Safeguarding\Enums\ConcernCategory;
use Modules\Safeguarding\Queries\MyConcerns;
use Modules\Scheduling\Contracts\DTOs\LessonCard;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * Raising a concern from one's own panel (docs/architecture/02, section 15):
 * what it is about in the reporter's own words (a child's, a student's, a
 * guardian's, a teacher's), the lesson and, for a teacher, the student it
 * is about, what happened, whether someone is in danger now; then the
 * concerns they raised and where each stands. It follows the person's own
 * live channel.
 */
trait RaisesConcerns
{
    public ?string $category = null;

    public string $text = '';

    public ?string $lesson = null;

    /** A teacher's: the student it is about. */
    public ?string $student = null;

    public bool $urgent = false;

    /** The number of the concern just sent (the thanks shows instead of the form). */
    public ?string $sent = null;

    /** family | teacher */
    abstract public function side(): string;

    /** classic | student | kids | teacher: whose words the page speaks in. */
    abstract public function look(): string;

    /** The learner a family's concern is about; null for a teacher. */
    abstract protected function learnerId(): ?string;

    /** @return array<string, string> value => label in the reporter's words */
    #[Computed]
    public function categories(): array
    {
        $look = $this->look();

        return collect(app(ConcernRules::class)->categoriesFor($this->side(), $look === 'teacher' ? 'classic' : $look))
            ->mapWithKeys(fn (ConcernCategory $c) => [$c->value => $c->label($look)])->all();
    }

    /** @return array<string, string> the lessons it may be about, newest first */
    #[Computed]
    public function lessons(): array
    {
        if ($this->look() === 'kids') {
            return [];
        }
        $time = app(LocalTimePresenter::class);
        $teacher = $this->side() === 'teacher';

        return collect(app(ConcernRules::class)->lessonChoices($this->side(), $this->learnerId(), (string) auth()->id()))
            ->mapWithKeys(fn (LessonCard $l) => [$l->id => $l->subject().' · '.$time->present($l->startsAt)['label'].' · '.($teacher ? $l->studentName : $l->teacherName)])
            ->all();
    }

    /** @return array<string, string> a teacher's students of late */
    #[Computed]
    public function students(): array
    {
        return $this->side() === 'teacher' ? app(ConcernRules::class)->studentsOfTeacher((string) auth()->id()) : [];
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function mine(): array
    {
        return app(MyConcerns::class)->of((string) auth()->id(), $this->look());
    }

    public function channel(): string
    {
        return PersonalChannel::name((string) auth()->id());
    }

    public function send(): void
    {
        $this->resetErrorBag();
        $teacher = $this->side() === 'teacher';
        try {
            $concern = app(ReportConcern::class)->handle(
                (string) auth()->id(),
                $this->side(),
                $teacher ? ($this->student ?: null) : $this->learnerId(),
                (string) $this->category,
                $this->text,
                $this->look() !== 'kids' && $this->urgent,
                $this->lesson ?: null,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field === 'session' ? 'lesson' : $field, $messages[0]);
            }

            return;
        }
        $this->reset('category', 'text', 'lesson', 'student', 'urgent');
        $this->sent = $concern->reference();
        unset($this->mine);
    }

    public function again(): void
    {
        $this->sent = null;
    }
}
