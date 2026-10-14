<?php

use Illuminate\Container\Container;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Setup\SetupChecklist;
use Modules\SharedKernel\Setup\SetupGroup;
use Modules\SharedKernel\Setup\SetupStep;

/*
 * The registry behind "Start here" (SharedKernel\Setup): the modules' steps
 * in display order, required apart from optional, and a reading that asks
 * each step once and stops at the first required one not done.
 */

abstract class ChecklistTestStep implements SetupStep
{
    /** @var array<string, bool> */
    public static array $done = [];

    /** @var list<string> */
    public static array $asked = [];

    public function title(): string
    {
        return $this->key();
    }

    public function hint(): string
    {
        return '';
    }

    public function done(): bool
    {
        self::$asked[] = $this->key();

        return self::$done[$this->key()] ?? false;
    }

    public function url(): ?string
    {
        return null;
    }

    public function ability(): ?Ability
    {
        return null;
    }
}

final class ChecklistTestPrices extends ChecklistTestStep
{
    public function key(): string
    {
        return 'prices';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Money;
    }

    public function order(): int
    {
        return 10;
    }

    public function required(): bool
    {
        return true;
    }
}

final class ChecklistTestSubjects extends ChecklistTestStep
{
    public function key(): string
    {
        return 'subjects';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Teaching;
    }

    public function order(): int
    {
        return 10;
    }

    public function required(): bool
    {
        return true;
    }
}

final class ChecklistTestCurriculum extends ChecklistTestStep
{
    public function key(): string
    {
        return 'curriculum';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Teaching;
    }

    public function order(): int
    {
        return 40;
    }

    public function required(): bool
    {
        return false;
    }
}

final class ChecklistTestBranding extends ChecklistTestStep
{
    public function key(): string
    {
        return 'branding';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Academy;
    }

    public function order(): int
    {
        return 10;
    }

    public function required(): bool
    {
        return true;
    }
}

final class ChecklistTestTeam extends ChecklistTestStep
{
    public function key(): string
    {
        return 'team';
    }

    public function group(): SetupGroup
    {
        return SetupGroup::Academy;
    }

    public function order(): int
    {
        return 10;
    }   // the same place as branding: then by key

    public function required(): bool
    {
        return false;
    }
}

beforeEach(function () {
    ChecklistTestStep::$done = [];
    ChecklistTestStep::$asked = [];
    $this->checklist = new SetupChecklist(new Container);
    foreach ([ChecklistTestPrices::class, ChecklistTestCurriculum::class, ChecklistTestTeam::class, ChecklistTestSubjects::class, ChecklistTestBranding::class, ChecklistTestPrices::class] as $step) {
        $this->checklist->add($step);
    }
});

$keys = fn (array $steps) => array_map(fn (SetupStep $step) => $step->key(), $steps);

it('lists the steps by part, then their order, then their key, once each, whatever order the modules registered them in', function () use ($keys) {
    expect($keys($this->checklist->steps()))->toBe(['branding', 'team', 'subjects', 'curriculum', 'prices'])
        ->and(ChecklistTestStep::$asked)->toBe([]);   // listing asks nothing
});

it('keeps the required steps apart from the optional ones, and counts the progress on the required only', function () use ($keys) {
    ChecklistTestStep::$done = ['subjects' => true, 'curriculum' => true, 'team' => true];
    $progress = $this->checklist->progress();

    expect($keys($progress->required()))->toBe(['branding', 'subjects', 'prices'])
        ->and($keys($progress->optional()))->toBe(['team', 'curriculum'])
        ->and($progress->requiredDone())->toBe(1)
        ->and($progress->requiredLeft())->toBe(2)
        ->and($progress->percent())->toBe(33)
        ->and($progress->complete())->toBeFalse();

    ChecklistTestStep::$done += ['branding' => true, 'prices' => true];
    $progress = $this->checklist->progress();
    expect($progress->requiredLeft())->toBe(0)
        ->and($progress->percent())->toBe(100)
        ->and($progress->complete())->toBeTrue();
});

it('asks each step once per reading, stops at the first required step not done, and reads afresh the next time', function () {
    $progress = $this->checklist->progress();

    expect($progress->complete())->toBeFalse()
        ->and(ChecklistTestStep::$asked)->toBe(['branding']);

    $progress->requiredLeft();
    $progress->requiredLeft();
    foreach ($progress->steps() as $step) {
        $progress->isDone($step);
    }
    expect(ChecklistTestStep::$asked)->toBe(['branding', 'subjects', 'prices', 'team', 'curriculum']);

    ChecklistTestStep::$done['branding'] = true;
    expect($this->checklist->progress()->isDone($this->checklist->steps()[0]))->toBeTrue();
});

it('is complete with nothing required', function () {
    $empty = new SetupChecklist(new Container);
    $empty->add(ChecklistTestTeam::class);

    expect($empty->progress()->complete())->toBeTrue()
        ->and($empty->progress()->percent())->toBe(100)
        ->and($empty->progress()->requiredLeft())->toBe(0);
});
