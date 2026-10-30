<?php

namespace Modules\Scheduling\Filament;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Scheduling\Domain\Shifts\ShiftBlock;
use Modules\Scheduling\Domain\Shifts\WeeklyShifts;
use Modules\SharedKernel\Time\Clock;

/** The block editor admins use for counter-offers: day, from, to, on the teacher's clock. */
final class ShiftBlocksField
{
    public static function make(string $name = 'blocks'): Repeater
    {
        $times = [];
        for ($m = 5 * 60; $m <= ShiftBlock::DAY_MIN; $m += 30) {
            $times[ShiftBlock::clock($m)] = Clock::wall(ShiftBlock::clock($m));
        }
        $days = [];
        foreach ([6, 7, 1, 2, 3, 4, 5] as $day) {
            $days[$day] = __('scheduling::shifts.days.'.$day);
        }

        return Repeater::make($name)
            ->label(__('scheduling::review.blocks'))
            ->schema([
                Select::make('weekday')->label(__('scheduling::review.day'))->options($days)->required()->native(false),
                Select::make('start')->label(__('scheduling::review.from'))->options(array_slice($times, 0, -1, true))->required()->native(false),
                Select::make('end')->label(__('scheduling::review.to'))->options(array_slice($times, 1, null, true))->required()->native(false),
            ])
            ->columns(3)
            ->addActionLabel(__('scheduling::review.add_block'))
            ->reorderable(false)
            ->defaultItems(0);
    }

    /** @return list<array{weekday: int, start: string, end: string}> */
    public static function fill(WeeklyShifts $week): array
    {
        return array_map(fn (ShiftBlock $b) => ['weekday' => $b->weekday, 'start' => $b->start(), 'end' => $b->end()], $week->blocks);
    }

    /**
     * Runs a shift Action from an action's form and puts its refusals on the
     * form's own fields: the Actions say `shifts` (or `blocks`) for what is
     * wrong with the week, which no field is called, so the modal would stay
     * open without a word.
     *
     * @template T
     *
     * @param  callable(): T  $run
     * @return T
     */
    public static function refusalsOnBlocks(Action $action, callable $run, string $name = 'blocks'): mixed
    {
        $field = fn (string $f) => 'mountedActions.'.($action->getNestingIndex() ?? 0).'.data.'.$f;

        try {
            return $run();
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([$field($name) => __('scheduling::review.bad_block')]);
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $key => $messages) {
                $target = match ((string) $key) {
                    'shifts', 'blocks' => $field($name),
                    'effective_from' => $field('effective_from'),
                    default => (string) $key,
                };
                $errors[$target] = [...($errors[$target] ?? []), ...(array) $messages];
            }

            throw ValidationException::withMessages($errors);
        }
    }
}
