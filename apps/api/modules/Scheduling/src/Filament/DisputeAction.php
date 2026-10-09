<?php

namespace Modules\Scheduling\Filament;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;
use Modules\Scheduling\Actions\Disputes\DisputeLesson;
use Modules\Scheduling\Domain\Disputes\DisputeRules;
use Modules\Scheduling\Domain\Models\Session;

/**
 * "Dispute this lesson" for the family's lessons (/my) and the teacher's week
 * (/teach): the reasons that fit what the lesson was recorded as, and what
 * happened in their words. DisputeLesson checks who and when again.
 */
final class DisputeAction
{
    /**
     * @param  'family'|'teacher'  $side
     * @param  Closure(array<string, mixed>): Session  $lesson  the viewer's own lesson named by the action's arguments
     * @param  Closure(): void  $done  refreshes the page's lessons
     */
    public static function make(string $side, Closure $lesson, Closure $done): Action
    {
        $l = 'scheduling::disputes.form.';

        return Action::make('dispute')
            ->label(__($l.'button'))->link()->color('warning')->size('sm')
            ->modalHeading(__($l.'heading'))
            ->modalDescription(fn (array $arguments) => __($l.'description.'.$side, [
                'outcome' => __('scheduling::disputes.outcomes.'.$lesson($arguments)->status->value), 'hours' => DisputeRules::HOURS,
            ]))
            ->schema(fn (array $arguments) => [
                Radio::make('reason')->label(__($l.'reason'))->required()
                    ->options(collect(DisputeRules::reasons($side, $lesson($arguments)->status->value))->mapWithKeys(fn (string $r) => [$r => __("scheduling::disputes.reasons.{$side}.{$r}")])->all()),
                Textarea::make('note')->label(__($l.'note'))->helperText(__($l.'note_hint.'.$side))->required()->rows(3)->maxLength(DisputeLesson::NOTE_MAX),
            ])
            ->modalSubmitActionLabel(__($l.'submit'))
            ->action(function (array $data, array $arguments) use ($side, $lesson, $done, $l) {
                try {
                    app(DisputeLesson::class)->handle($lesson($arguments), $side, (string) auth()->id(), (string) $data['reason'], (string) $data['note']);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(implode(' ', $e->validator->errors()->all()))->send();

                    throw new Halt;
                }
                $done();
                Notification::make()->success()->title(__($l.'sent'))->body(__($l.'sent_body'))->send();
            });
    }
}
