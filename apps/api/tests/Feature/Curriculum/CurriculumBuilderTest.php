<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Actions\MoveNode;
use Modules\Curriculum\Actions\PublishVersion;
use Modules\Curriculum\Actions\SaveNode;
use Modules\Curriculum\Actions\SaveProgram;
use Modules\Curriculum\Filament\Programs\EditCurriculum;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\Program;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

/*
 * The curriculum builder: a readiness bar that reads as PublishVersion does
 * (blockers close publishing with the reason, warnings show in its
 * confirmation), quick add with Enter, drag and drop through MoveNode::place
 * (within a parent, and a lesson between the units of its level), and the
 * student's preview.
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->admin = User::factory()->withRole(Role::Admin)->create(['name' => 'Huda Admin', 'email' => 'huda@noor.test']);
    $this->actingAs($this->admin);
    Filament::setCurrentPanel('academy');
    $this->program = app(SaveProgram::class)->handle(null, ['subject_id' => app(CatalogApi::class)->createSubject('عربي', 'Arabic'), 'name_ar' => 'الحروف', 'name_en' => 'Letters']);
    $this->version = $this->program->workingVersion();
});

function bNode(Program $program, string $kind, ?Node $parent, string $title, array $extra = []): Node
{
    return app(SaveNode::class)->handle($program->workingVersion(), null, ['kind' => $kind, 'parent_id' => $parent?->id, 'title_ar' => $title, 'title_en' => $title] + $extra);
}

/** @return list<string> the titles of a parent's children in order */
function bOrder(?Node $parent, string $versionId): array
{
    return Node::query()->where('version_id', $versionId)->where('parent_id', $parent?->id)->orderBy('ordinal')->pluck('title_en')->all();
}

it('shows what stops publishing, closes the button with the reason, and the Action refuses the same', function () {
    $level = bNode($this->program, 'level', null, 'Beginner');
    bNode($this->program, 'level', null, 'Empty level');
    $unit = bNode($this->program, 'unit', $level, 'Empty unit');

    Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->assertSee(__('curriculum::curriculum.readiness.blocked'))
        ->assertSee(__('curriculum::curriculum.readiness.items.no_lessons'))
        ->assertSee(__('curriculum::curriculum.readiness.items.empty_level', ['title' => 'Empty level']), false)
        ->assertSee(__('curriculum::curriculum.readiness.items.empty_unit', ['title' => 'Empty unit']), false)
        ->assertSeeHtml('href="#node-'.$unit->id.'"')
        ->assertActionDisabled('publish');

    expect(fn () => app(PublishVersion::class)->handle($this->version, null))
        ->toThrow(fn (ValidationException $e) => expect($e->errors()['version'])->toContain(__('curriculum::curriculum.readiness.items.no_lessons')));
});

it('publishes once nothing stops it, listing the warnings in the confirmation', function () {
    $level = bNode($this->program, 'level', null, 'Beginner');
    bNode($this->program, 'lesson', $level, 'Alif');

    Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->assertSee(__('curriculum::curriculum.readiness.ready_warnings'))
        ->assertActionEnabled('publish')
        ->mountAction('publish')
        ->assertMountedActionModalSee(__('curriculum::curriculum.readiness.items.no_objective', ['title' => 'Alif']))
        ->callMountedAction()
        ->assertNotified(__('curriculum::curriculum.builder.published'));

    expect($this->version->fresh()->status)->toBe('published');
});

it('adds with Enter and keeps the field for the next one; details opens the full form with what was typed', function () {
    $component = Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->set('quick.root', 'Beginner')
        ->call('quickAdd', 'root')
        ->assertSet('quick.root', '');
    $level = Node::query()->where('kind', 'level')->sole();

    $component->set("quick.{$level->id}", '  Alif  ')->call('quickAdd', $level->id)
        ->set("quick.{$level->id}", 'Ba')->call('quickAdd', $level->id)
        ->assertSet("quick.{$level->id}", '')
        ->call('quickAdd', $level->id)                     // nothing typed: nothing added
        ->assertSee('Alif')->assertSee('Ba');
    expect(bOrder($level, $this->version->id))->toBe(['Alif', 'Ba'])
        ->and(Node::query()->where('title_en', 'Alif')->sole()->title_ar)->toBe('Alif');

    // A level that has units takes units from its field; a unit takes lessons.
    $unit = bNode($this->program, 'unit', $level, 'Letters');
    $component->set("quick.{$level->id}", 'Vowels')->call('quickAdd', $level->id)
        ->set("quick.{$unit->id}", 'Ta')->call('quickAdd', $unit->id);
    expect(Node::query()->where('title_en', 'Vowels')->sole()->kind)->toBe('unit')
        ->and(bOrder($unit, $this->version->id))->toBe(['Ta']);

    $component->set("quick.{$unit->id}", 'Tha')
        ->mountAction(TestAction::make('addChild')->arguments(['node' => $unit->id, 'kind' => 'lesson', 'details' => true]))
        ->assertSchemaStateSet(['title_ar' => 'Tha', 'title_en' => 'Tha'])
        ->setActionData(['objectives' => 'Say it'])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertSet("quick.{$unit->id}", '');
    expect(Node::query()->where('title_en', 'Tha')->sole()->objectives)->toBe('Say it');
});

it('moves by drag and drop within a parent and between the units of a level, never out of the level', function () {
    $level = bNode($this->program, 'level', null, 'Beginner');
    $other = bNode($this->program, 'level', null, 'Advanced');
    $u1 = bNode($this->program, 'unit', $level, 'U1');
    $u2 = bNode($this->program, 'unit', $level, 'U2');
    $far = bNode($this->program, 'unit', $other, 'Far');
    [$a, $b, $c] = array_map(fn ($t) => bNode($this->program, 'lesson', $u1, $t), ['A', 'B', 'C']);
    $d = bNode($this->program, 'lesson', $u2, 'D');

    $page = Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->call('sortNode', $c->id, 0, $u1->id);
    expect(bOrder($u1, $this->version->id))->toBe(['C', 'A', 'B']);

    $page->call('sortNode', $a->id, 1, $u2->id);
    expect(bOrder($u1, $this->version->id))->toBe(['C', 'B'])
        ->and(bOrder($u2, $this->version->id))->toBe(['D', 'A']);

    $page->call('sortNode', $u2->id, 0, $level->id)->call('sortNode', $other->id, 0);
    expect(bOrder($level, $this->version->id))->toBe(['U2', 'U1'])
        ->and(bOrder(null, $this->version->id))->toBe(['Advanced', 'Beginner']);

    // Out of its level, or a unit to another level: refused, and said.
    $page->call('sortNode', $b->id, 0, $far->id)->assertNotified(__('curriculum::curriculum.errors.move'));
    expect(fn () => app(MoveNode::class)->place($u1, $other->id, 0))->toThrow(ValidationException::class)
        ->and(bOrder($far, $this->version->id))->toBe([])
        ->and(Node::query()->find($u1->id)->parent_id)->toBe($level->id);

    // The arrows still step within the parent.
    $page->call('move', $b->id, 'up');
    expect(bOrder($u1, $this->version->id))->toBe(['B', 'C']);
});

it('previews the levels as islands and the lessons as stations, with no learner', function () {
    $level = bNode($this->program, 'level', null, 'Beginner');
    bNode($this->program, 'lesson', $level, 'Alif');

    Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->mountAction('preview')
        ->assertMountedActionModalSee('Beginner')
        ->assertMountedActionModalSee('Alif')
        ->assertMountedActionModalSee(__('curriculum::curriculum.preview.island', ['n' => 1, 'of' => 1]));
});

it('greets an empty curriculum with a field for its first level', function () {
    Livewire::test(EditCurriculum::class, ['record' => $this->program->id])
        ->assertSee(__('curriculum::curriculum.builder.empty_title'))
        ->assertSeeHtml('wire:model="quick.root"')
        ->assertActionHidden('preview');
});
