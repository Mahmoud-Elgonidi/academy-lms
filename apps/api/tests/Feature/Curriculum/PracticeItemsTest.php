<?php

use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Curriculum\Actions\Practice\SaveNodeItems;
use Modules\Curriculum\Contracts\CurriculumApi;
use Modules\Curriculum\Filament\Programs\EditCurriculum;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeItem;
use Modules\SharedKernel\Games\GameCatalog;
use Modules\SharedKernel\Games\ItemKind;
use Tests\Support\EnrolledStudent;
use Tests\Support\QuranProgram;
use Tests\Support\StarterGames;

uses(EnrolledStudent::class, QuranProgram::class, StarterGames::class);

/*
 * A lesson's practice items (docs/architecture/02, section 18): the words
 * the games play, written once on the lesson in the curriculum builder, in
 * three shapes (a question with answers, parts to put in order, a pair to
 * match). An item keeps its id across saves, so what learners practised on
 * it stays theirs; the games are handed the shape, never the lesson.
 */

beforeEach(function () {
    $this->enrolStudentOnPlan();
    $this->program = $this->publishQuranProgram($this->quran);
    $this->first = Node::query()->findOrFail($this->lessons[0]);
});

function fatihaItems(): array
{
    return [
        ['kind' => 'choice', 'prompt' => '  أكمل: الحمد لله رب …  ', 'options' => ['العالمين', '', 'الدين'], 'answer' => 0],
        ['kind' => 'order', 'prompt' => 'رتّب الآيات', 'parts' => ['بسم الله الرحمن الرحيم', 'الحمد لله رب العالمين', 'الرحمن الرحيم']],
        ['kind' => 'pair', 'left' => 'مالك', 'right' => 'يوم الدين'],
        ['kind' => 'choice', 'prompt' => 'أكمل: مالك يوم …', 'options' => ['الدين', 'العالمين'], 'answer' => 0],
    ];
}

it('keeps a lesson\'s items in order, tidied, each in its shape, and hands the games only the shape', function () {
    $saved = app(SaveNodeItems::class)->handle($this->first, fatihaItems(), $this->admin->id);

    expect($saved)->toHaveCount(4)
        ->and($saved[0]->prompt)->toBe('أكمل: الحمد لله رب …')
        ->and($saved[0]->data)->toBe(['options' => ['العالمين', 'الدين'], 'answer' => 0])
        ->and($saved[2]->prompt)->toBeNull()
        ->and($saved[2]->data)->toBe(['left' => 'مالك', 'right' => 'يوم الدين']);

    $items = app(CurriculumApi::class)->practiceItems([$this->first->id, $this->lessons[1]]);
    expect(array_keys($items))->toBe([$this->first->id])
        ->and(array_map(fn ($i) => $i->kind, $items[$this->first->id]))->toBe([ItemKind::Choice, ItemKind::Order, ItemKind::Pair, ItemKind::Choice])
        ->and($items[$this->first->id][1]->forRound())->toBe(['id' => $saved[1]->id, 'kind' => 'order', 'prompt' => 'رتّب الآيات', 'parts' => ['بسم الله الرحمن الرحيم', 'الحمد لله رب العالمين', 'الرحمن الرحيم'], 'dir' => 'rtl'])
        ->and($items[$this->first->id][0]->forRound())->not->toHaveKey('node')
        ->and($items[$this->first->id][2]->summary())->toBe('مالك ↔ يوم الدين');
});

it('keeps an item\'s id when it is saved again, drops the ones left out, and refuses another lesson\'s', function () {
    $saved = app(SaveNodeItems::class)->handle($this->first, fatihaItems(), $this->admin->id);
    $kept = $saved[2]->id;

    $again = app(SaveNodeItems::class)->handle($this->first, [
        ['id' => $kept, 'kind' => 'pair', 'left' => 'مالك', 'right' => 'يوم الدين'],
        ['kind' => 'choice', 'prompt' => 'جديد', 'options' => ['أ', 'ب'], 'answer' => 1],
    ], $this->admin->id);
    expect($again[0]->id)->toBe($kept)
        ->and($again[0]->ordinal)->toBe(1)
        ->and(NodeItem::query()->where('node_id', $this->first->id)->count())->toBe(2);

    $other = Node::query()->findOrFail($this->lessons[1]);
    expect(fn () => app(SaveNodeItems::class)->handle($other, [['id' => $kept, 'kind' => 'pair', 'left' => 'x', 'right' => 'y']], $this->admin->id))
        ->toThrow(ValidationException::class);
});

it('refuses an item that does not fit its shape', function (array $item, string $field) {
    try {
        app(SaveNodeItems::class)->handle($this->first, [$item], $this->admin->id);
        $this->fail('saved');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toContain($field);
    }
})->with([
    'no prompt' => [['kind' => 'choice', 'options' => ['أ', 'ب'], 'answer' => 0], 'items.0.prompt'],
    'one answer' => [['kind' => 'choice', 'prompt' => 'س', 'options' => ['أ'], 'answer' => 0], 'items.0.options'],
    'the right answer is not there' => [['kind' => 'choice', 'prompt' => 'س', 'options' => ['أ', 'ب'], 'answer' => 4], 'items.0.answer'],
    'the same answer twice' => [['kind' => 'choice', 'prompt' => 'س', 'options' => ['أ', 'أ'], 'answer' => 0], 'items.0.options'],
    'one part' => [['kind' => 'order', 'prompt' => 'رتّب', 'parts' => ['أ']], 'items.0.parts'],
    'half a pair' => [['kind' => 'pair', 'left' => 'أ'], 'items.0.right'],
    'a part too long' => [['kind' => 'order', 'parts' => [str_repeat('أ', ItemKind::PART_MAX + 1), 'ب']], 'items.0.parts.0'],
    'an unknown shape' => [['kind' => 'essay', 'prompt' => 'اكتب'], 'items.0.kind'],
]);

it('lets two questions share an answer', function () {
    app(SaveNodeItems::class)->handle($this->first, [
        ['kind' => 'choice', 'prompt' => 'س1', 'options' => ['الدين', 'العالمين'], 'answer' => 0],
        ['kind' => 'choice', 'prompt' => 'س2', 'options' => ['العالمين', 'الدين'], 'answer' => 1],
    ], $this->admin->id);

    expect(NodeItem::query()->where('node_id', $this->first->id)->count())->toBe(2);
});

it('puts items on lessons only, at most forty', function () {
    $unit = Node::query()->where('version_id', $this->first->version_id)->where('kind', 'unit')->firstOrFail();
    expect(fn () => app(SaveNodeItems::class)->handle($unit, fatihaItems(), $this->admin->id))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveNodeItems::class)->handle($this->first, array_fill(0, SaveNodeItems::MAX + 1, ['kind' => 'pair', 'left' => 'أ', 'right' => 'ب']), $this->admin->id))
        ->toThrow(ValidationException::class);
});

it('writes a lesson\'s practice in the curriculum builder, also on a published program', function () {
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->admin);

    Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->callAction('practice', ['items' => [
            ['kind' => 'choice', 'prompt' => 'أكمل: الحمد لله رب …', 'options' => ['العالمين', 'الدين'], 'answer' => 0],
            ['kind' => 'pair', 'left' => 'مالك', 'right' => 'يوم الدين'],
        ]], ['node' => $this->first->id])
        ->assertHasNoActionErrors();

    expect(NodeItem::query()->where('node_id', $this->first->id)->orderBy('ordinal')->pluck('kind')->all())->toBe(['choice', 'pair']);
});

it('shows in the builder the games a lesson\'s practice is enough for, and how much of the program has practice', function () {
    $this->installStarterGames();
    Filament::setCurrentPanel('academy');
    $this->actingAs($this->admin);
    $title = fn (string $key) => app(GameCatalog::class)->game($key)->title();

    // One question and one pair: too few for any game yet.
    app(SaveNodeItems::class)->handle($this->first, [
        ['kind' => 'choice', 'prompt' => 'أكمل: الحمد لله رب …', 'options' => ['العالمين', 'الدين'], 'answer' => 0],
        ['kind' => 'pair', 'left' => 'مالك', 'right' => 'يوم الدين'],
    ], $this->admin->id);
    $builder = Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->assertSee(__('curriculum::curriculum.practice.plays_none'))
        ->assertSee(__('curriculum::curriculum.practice.program', ['with' => 1, 'total' => 7]));
    expect($builder->instance()->playsIn[$this->first->id])->toBe([]);

    // Two questions, an order and a pair: the balloons (two questions), the gates and the bridge (one order); the twins need three pairs.
    app(SaveNodeItems::class)->handle($this->first, fatihaItems(), $this->admin->id);
    $builder = Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->assertSee(__('curriculum::curriculum.practice.plays_in'))
        ->assertSee($title('bird-balloons'))->assertSee($title('bird-gates'))->assertSee($title('turtle-bridge'))
        ->assertDontSee($title('bird-twins'));
    expect(array_column($builder->instance()->playsIn[$this->first->id], 'title'))->toEqualCanonicalizing([$title('bird-gates'), $title('bird-balloons'), $title('turtle-bridge')])
        ->and($builder->instance()->practiceSummary())->toMatchArray(['with' => 1, 'total' => 7])
        ->and($builder->instance()->practiceSummary()['missing'])->toHaveCount(6);
});
