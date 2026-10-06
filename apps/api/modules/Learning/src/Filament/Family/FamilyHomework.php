<?php

namespace Modules\Learning\Filament\Family;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Learning\Actions\SubmitHomework;
use Modules\Learning\Authorization\LearningAccess;
use Modules\Learning\Models\Homework;
use Modules\SharedKernel\Time\ViewerTimezone;

/**
 * The learner's homework: what is still to do, with the answer written,
 * a file or a recording made right here (Quran recitation, reading); then
 * what was handed in, with the teacher's mark.
 */
final class FamilyHomework extends Page
{
    protected string $view = 'learning::filament.family.homework';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'homework';

    /** @var array<string, string> homework id => the written answer */
    public array $answers = [];

    /** @var array<string, mixed> homework id => an uploaded file or recording */
    public array $files = [];

    public static function getNavigationLabel(): string
    {
        return __('learning::family.homework.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $learner = FamilyLearning::learner();
        $open = $learner ? Homework::query()->where('student_id', $learner->id)->where('status', 'assigned')->count() : 0;

        return $open > 0 ? (string) $open : null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('learning::family.homework.title');
    }

    /** @return array{open: Collection<int, Homework>, done: Collection<int, Homework>, teachers: Collection<string, string>, tz: string} */
    #[Computed]
    public function homework(): array
    {
        $learner = FamilyLearning::learner();
        $all = $learner
            ? Homework::query()->with('submissions')->where('student_id', $learner->id)->orderBy('due_at')->limit(60)->get()
            : new \Illuminate\Database\Eloquent\Collection;

        return [
            'open' => $all->filter(fn (Homework $h) => in_array($h->status, ['assigned', 'missed'], true))->values(),
            'done' => $all->filter(fn (Homework $h) => in_array($h->status, ['submitted', 'reviewed'], true))->sortByDesc('due_at')->values(),
            'teachers' => DB::table('users')->whereIn('id', $all->pluck('teacher_id')->unique())->pluck('name', 'id'),
            'tz' => app(ViewerTimezone::class)->effective(),
        ];
    }

    public function handIn(string $id): void
    {
        $this->resetErrorBag();
        $homework = Homework::query()->find($id);
        $learner = FamilyLearning::learner();
        abort_unless($homework !== null && $learner !== null && $homework->student_id === $learner->id
            && app(LearningAccess::class)->canHandIn(auth()->user(), $homework), 404);

        $file = $this->files[$id] ?? null;
        try {
            app(SubmitHomework::class)->handle($homework, (string) auth()->id(), trim($this->answers[$id] ?? '') ?: null, $file instanceof UploadedFile ? $file : null);
        } catch (ValidationException $e) {
            // The action names its fields "body" and "file"; on this page each homework has its own.
            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn (array $messages, string $key) => [($key === 'body' ? 'answers' : 'files').".{$id}" => $messages])->all());
        }

        unset($this->answers[$id], $this->files[$id], $this->homework);
        Notification::make()->success()->title(__('learning::family.homework.sent'))->send();
    }

    public function removeFile(string $id): void
    {
        unset($this->files[$id]);
    }
}
