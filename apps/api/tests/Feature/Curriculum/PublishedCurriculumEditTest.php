<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Curriculum\Actions\DeleteNode;
use Modules\Curriculum\Actions\MoveNode;
use Modules\Curriculum\Filament\Programs\EditCurriculum;
use Modules\Curriculum\Models\CurriculumVersion;
use Modules\Curriculum\Models\Node;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Learning\Actions\SubmitSessionReport;
use Modules\Learning\Models\SessionReport;
use Modules\Learning\Queries\ProgressQuery;
use Modules\Scheduling\Contracts\Events\SessionFinalized;
use Modules\Scheduling\Domain\Models\Session;
use Tests\Support\EnrolledStudent;
use Tests\Support\SampleProgram;

/*
 * A published curriculum keeps changing in the back office: a level, unit or
 * lesson nobody started is deleted (and the courses on it count again), one
 * with reports, progress, homework or practice is refused with why and what to
 * do instead; the arrows walk a lesson across the units of its level; drag and
 * drop sends each row's bare id.
 *
 * The sample program: level 1 with units Letters (lessons 1–3) and Vowels
 * (4–5), level 2 with Prolongation (6–7).
 */

uses(EnrolledStudent::class, SampleProgram::class);

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->program = $this->publishSampleProgram($this->quran);
    $this->version = $this->program->workingVersion();
    $this->enrollmentId = $this->booked->enrollmentId;
    app(EnrollmentApi::class)->attachCurriculum($this->enrollmentId, $this->program->id);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
});

function pNode(int $lesson): Node
{
    return Node::query()->findOrFail(test()->lessons[$lesson]);
}

function pUnit(string $title): Node
{
    return Node::query()->where('kind', 'unit')->where('title_en', $title)->sole();
}

/** @return list<string> a unit's lessons (English titles) in order */
function pLessons(string $unit): array
{
    return Node::query()->where('parent_id', pUnit($unit)->id)->orderBy('ordinal')->pluck('title_en')->all();
}

function pReportOn(int $lesson): void
{
    $session = Session::query()->where('enrollment_id', test()->enrollmentId)->orderBy('starts_at')->firstOrFail();
    $session->update(['status' => 'completed']);
    event(new SessionFinalized($session->academy_id, $session->id, 'completed', []));
    app(SubmitSessionReport::class)->handle(SessionReport::query()->where('session_id', $session->id)->firstOrFail(), test()->teacher->id, [
        'engagement' => 4, 'overall' => 4, 'items' => [['node_id' => test()->lessons[$lesson], 'outcome' => 'introduced', 'score' => 3]],
    ]);
}

it('deletes from a published version what no learner started, and the course counts again', function () {
    expect((int) app(ProgressQuery::class)->position($this->enrollmentId)->total_weight)->toBe(8);

    Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->assertSee(__('curriculum::curriculum.builder.published_note'))
        ->assertActionEnabled(TestAction::make('deleteNode')->arguments(['node' => $this->lessons[4]]))
        ->callAction(TestAction::make('deleteNode')->arguments(['node' => $this->lessons[4]]))
        ->assertNotified(__('curriculum::curriculum.builder.deleted'));
    expect(Node::query()->find($this->lessons[4]))->toBeNull()
        ->and(pLessons('Vowels'))->toBe(['Lesson 4']);

    // A whole unit with its lessons, and the course's total follows.
    app(DeleteNode::class)->handle(pUnit('Prolongation'));
    expect(Node::query()->whereIn('id', [$this->lessons[5], $this->lessons[6]])->count())->toBe(0)
        ->and((int) app(ProgressQuery::class)->position($this->enrollmentId)->total_weight)->toBe(5);
});

it('refuses to delete what learners left something on, saying why and what to do instead', function () {
    pReportOn(1);

    // The lesson, its unit and its level all hold the report.
    foreach ([pNode(1), pUnit('Letters'), Node::query()->findOrFail(pUnit('Letters')->parent_id)] as $node) {
        expect(fn () => app(DeleteNode::class)->handle($node))->toThrow(ValidationException::class, __('curriculum::curriculum.errors.in_use.reports', ['title' => $node->toData()->title()]));
    }
    expect(Node::query()->find($this->lessons[1]))->not->toBeNull();

    // The screen says it (ShowRefusals) and keeps the lesson.
    Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->callAction(TestAction::make('deleteNode')->arguments(['node' => $this->lessons[1]]))
        ->assertNotified(__('curriculum::curriculum.errors.in_use.reports', ['title' => pNode(1)->toData()->title()]));
    expect(Node::query()->find($this->lessons[1]))->not->toBeNull();

    // Practice played and homework that asks for a lesson's practice hold it too.
    DB::table('practice_stats')->insert(['academy_id' => $this->academy->id, 'enrollment_id' => $this->enrollmentId, 'node_id' => $this->lessons[3], 'student_id' => $this->student->id, 'rounds' => 1, 'answered' => 3, 'correct' => 2, 'last_at' => now()]);
    expect(fn () => app(DeleteNode::class)->handle(pNode(3)))->toThrow(ValidationException::class, __('curriculum::curriculum.errors.in_use.practice', ['title' => pNode(3)->toData()->title()]));

    DB::table('homework')->insert(['id' => (string) Str::uuid7(), 'academy_id' => $this->academy->id, 'teacher_id' => $this->teacher->id, 'student_id' => $this->student->id,
        'title' => 'Practice', 'due_at' => now()->addDay(), 'practice_nodes' => json_encode([$this->lessons[5]]), 'created_at' => now(), 'updated_at' => now()]);
    expect(fn () => app(DeleteNode::class)->handle(pNode(5)))->toThrow(ValidationException::class, __('curriculum::curriculum.errors.in_use.homework', ['title' => pNode(5)->toData()->title()]));

    DB::table('game_plays')->insert(['id' => (string) Str::uuid7(), 'academy_id' => $this->academy->id, 'student_id' => $this->student->id, 'played_by' => $this->student->id,
        'game_id' => (string) Str::uuid7(), 'game_key' => 'quiz', 'version_id' => (string) Str::uuid7(), 'focus' => 'free',
        'items' => json_encode([['id' => (string) Str::uuid7(), 'node' => $this->lessons[6], 'kind' => 'choice']]), 'started_at' => now(), 'total' => 1]);
    expect(fn () => app(DeleteNode::class)->handle(pNode(6)))->toThrow(ValidationException::class, __('curriculum::curriculum.errors.in_use.practice', ['title' => pNode(6)->toData()->title()]));

    // Untouched: still goes.
    app(DeleteNode::class)->handle(pNode(4));
    expect(Node::query()->find($this->lessons[4]))->toBeNull();
});

it('keeps a retired version untouchable', function () {
    CurriculumVersion::query()->whereKey($this->version->id)->update(['status' => 'retired']);

    expect(fn () => app(DeleteNode::class)->handle(pNode(4)))->toThrow(ValidationException::class, __('curriculum::curriculum.errors.retired'))
        ->and(fn () => app(MoveNode::class)->handle(pNode(4), 'up'))->toThrow(ValidationException::class, __('curriculum::curriculum.errors.retired'));
});

it('walks a lesson across the units of its level with the arrows, never out of the level', function () {
    $page = Livewire::test(EditCurriculum::class, ['record' => $this->program->id]);

    // ▲ on the first lesson of Vowels: to the end of Letters.
    $page->call('move', $this->lessons[3], 'up');
    expect(pLessons('Letters'))->toBe(['Lesson 1', 'Lesson 2', 'Lesson 3', 'Lesson 4'])->and(pLessons('Vowels'))->toBe(['Lesson 5']);

    // ▼ on the last lesson of Letters: back to the start of Vowels; again ▼ steps within it.
    $page->call('move', $this->lessons[3], 'down');
    expect(pLessons('Letters'))->toBe(['Lesson 1', 'Lesson 2', 'Lesson 3'])->and(pLessons('Vowels'))->toBe(['Lesson 4', 'Lesson 5']);
    $page->call('move', $this->lessons[3], 'down');
    expect(pLessons('Vowels'))->toBe(['Lesson 5', 'Lesson 4']);

    // The screen numbers follow: Lesson 4 is now the fifth.
    $page->assertSeeInOrder(['Lesson 3', 'Lesson 5', 'Lesson 4', 'Lesson 6']);

    // The edges of the level: the last lesson of level 1 and the first of level 2 stay.
    $page->call('move', $this->lessons[3], 'down')->call('move', $this->lessons[5], 'up')->call('move', $this->lessons[0], 'up');
    expect(pLessons('Vowels'))->toBe(['Lesson 5', 'Lesson 4'])->and(pLessons('Prolongation'))->toBe(['Lesson 6', 'Lesson 7'])
        ->and(pLessons('Letters'))->toBe(['Lesson 1', 'Lesson 2', 'Lesson 3']);

    // Units still step among the units of their level.
    $page->call('move', pUnit('Vowels')->id, 'up');
    expect(Node::query()->where('parent_id', pUnit('Letters')->parent_id)->orderBy('ordinal')->pluck('title_en')->all())->toBe(['Vowels', 'Letters']);
});

it('walks a lesson between a unit and a loose lesson of the level', function () {
    $level = Node::query()->findOrFail(pUnit('Letters')->parent_id);
    // Lesson 4 out to the level, between the two units.
    app(MoveNode::class)->place(pNode(3), $level->id, 1);
    $children = fn () => Node::query()->where('parent_id', $level->id)->orderBy('ordinal')->pluck('title_en')->all();
    expect($children())->toBe(['Letters', 'Lesson 4', 'Vowels']);

    // ▲ on the first lesson of Vowels: next to it in the level (a loose lesson sits before its unit).
    app(MoveNode::class)->handle(pNode(4), 'up');
    expect($children())->toBe(['Letters', 'Lesson 4', 'Lesson 5', 'Vowels'])->and(pLessons('Vowels'))->toBe([]);

    // A loose lesson steps into the unit beside it: the end of the one above, the start of the one below.
    app(MoveNode::class)->handle(pNode(3), 'up');
    expect(pLessons('Letters'))->toBe(['Lesson 1', 'Lesson 2', 'Lesson 3', 'Lesson 4']);
    app(MoveNode::class)->handle(pNode(4), 'down');
    expect(pLessons('Vowels'))->toBe(['Lesson 5'])->and($children())->toBe(['Letters', 'Vowels']);
});

it('drags a lesson into another unit and into an empty one by its bare id', function () {
    $page = Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        // Each row's key is its id as it is (Livewire 4 reads wire:sort:item as a plain value).
        ->assertSeeHtml('wire:sort:item="'.$this->lessons[0].'"')
        ->assertSeeHtml('wire:sort:item="'.pUnit('Vowels')->id.'"')
        ->assertDontSeeHtml("wire:sort:item=\"'");

    $page->call('sortNode', $this->lessons[0], 1, pUnit('Vowels')->id);
    expect(pLessons('Letters'))->toBe(['Lesson 2', 'Lesson 3'])->and(pLessons('Vowels'))->toBe(['Lesson 4', 'Lesson 1', 'Lesson 5']);

    // Empty a unit; it keeps a drop area with its hint, and a lesson dropped there stays.
    $page->call('sortNode', $this->lessons[1], 0, pUnit('Vowels')->id)->call('sortNode', $this->lessons[2], 0, pUnit('Vowels')->id)
        ->assertSeeHtml('data-empty="'.e(__('curriculum::curriculum.builder.unit_empty')).'"');
    expect(pLessons('Letters'))->toBe([]);
    $page->call('sortNode', $this->lessons[4], 0, pUnit('Letters')->id);
    expect(pLessons('Letters'))->toBe(['Lesson 5']);

    // Never into another level.
    $page->call('sortNode', $this->lessons[4], 0, pUnit('Prolongation')->id)->assertNotified(__('curriculum::curriculum.errors.move'));
    expect(pLessons('Letters'))->toBe(['Lesson 5']);
});
