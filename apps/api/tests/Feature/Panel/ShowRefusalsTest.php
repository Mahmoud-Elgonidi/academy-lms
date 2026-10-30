<?php

use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Livewire;
use Modules\Catalog\Contracts\CatalogApi;
use Modules\Curriculum\Actions\PublishVersion;
use Modules\Curriculum\Actions\SaveProgram;
use Modules\Curriculum\Filament\Programs\EditCurriculum;
use Modules\Curriculum\Models\Program;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;

/*
 * No silent refusal (SharedKernel\Filament\ShowRefusals): a ValidationException
 * keyed by something that is not a field on the screen shows as a danger
 * notification; field errors stay under their fields, and a message already
 * waiting as a notification is not sent twice.
 */

beforeEach(function () {
    $this->academy = $this->actAsTenant($this->createAcademy(['slug' => 'noor']));
    $this->actingAs(User::factory()->withRole(Role::Admin)->create());
    Filament::setCurrentPanel('academy');
});

/** @return list<string> the titles of the notifications the last request sent */
function sentTitles(): array
{
    return array_values(array_map(fn (array $n) => (string) $n['title'], session('filament.claimed_notifications') ?? session('filament.notifications') ?? []));
}

it('shows a refusal keyed by no field: publishing a version without lessons', function () {
    $program = app(SaveProgram::class)->handle(null, ['subject_id' => app(CatalogApi::class)->createSubject('عربي', 'Arabic'), 'name_ar' => 'الحروف', 'name_en' => 'Letters']);

    Livewire::test(new class extends Component
    {
        public string $programId = '';

        public function publish(): void
        {
            app(PublishVersion::class)->handle(Program::query()->findOrFail($this->programId)->workingVersion(), null);
        }

        public function render(): string
        {
            return '<div></div>';
        }
    })->set('programId', $program->id)->call('publish')
        ->assertNotified(__('curriculum::curriculum.readiness.items.no_lessons'));
});

it('leaves a field\'s refusal under its field', function () {
    Livewire::test(new class extends Component
    {
        public string $email = '';

        public function save(): void
        {
            throw ValidationException::withMessages(['email' => 'That address is taken.']);
        }

        public function render(): string
        {
            return '<div></div>';
        }
    })->call('save')
        ->assertHasErrors('email')
        ->assertNotNotified();

    // A Filament form's fields (mountedActions.*, data.*) too: the modal shows them.
    $program = app(SaveProgram::class)->handle(null, ['subject_id' => app(CatalogApi::class)->createSubject('عربي', 'Arabic'), 'name_ar' => 'الحروف', 'name_en' => 'Letters']);
    Livewire::test(EditCurriculum::class, ['record' => $program->id])
        ->callAction('addLevel', data: ['title_ar' => '', 'title_en' => ''])
        ->assertHasActionErrors(['title_ar' => 'required'])
        ->assertNotNotified();
});

it('never sends the same refusal twice', function () {
    Livewire::test(new class extends Component
    {
        public function save(): void
        {
            Notification::make()->danger()->title('The academy is closed.')->send();
            throw ValidationException::withMessages(['academy' => 'The academy is closed.', 'plan' => ['No room on the plan.', 'No room on the plan.']]);
        }

        public function render(): string
        {
            return '<div></div>';
        }
    })->call('save');

    expect(sentTitles())->toBe(['The academy is closed.', 'No room on the plan.']);
});
