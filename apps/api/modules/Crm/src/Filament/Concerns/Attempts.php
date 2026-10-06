<?php

namespace Modules\Crm\Filament\Concerns;

use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/** Runs a change from a dialog; a refusal keeps the dialog open and says why. */
trait Attempts
{
    private function attempt(Action $action, Closure $change): mixed
    {
        try {
            return $change();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
            $action->halt();
        }

        return null;
    }
}
