<?php

namespace Modules\SharedKernel\Filament;

use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\ComponentHook;

/**
 * No silent refusal on a screen: when an Action (or any method) on a Livewire
 * component throws a ValidationException keyed by something that is not a
 * field on the screen (`version`, `academy`, `node`...), nothing would show it:
 * Livewire keeps only the errors of the component's own properties and
 * Filament closes or keeps its modal without a word. This sends those
 * messages as a danger notification. Field errors (a Filament form's
 * `data.*` and `mountedActions.*`, or the component's own property, which a
 * screen shows with @error) stay under their fields; a message already
 * waiting as a notification is not sent again.
 *
 * Registered once with Livewire::componentHook in SharedKernel's provider.
 */
final class ShowRefusals extends ComponentHook
{
    private const FIELD_PREFIXES = ['data.', 'mountedActions.'];

    /** @param  callable  $stopPropagation */
    public function exception(\Throwable $e, $stopPropagation): void
    {
        if (! $e instanceof ValidationException) {
            return;
        }

        $messages = [];
        foreach ($e->errors() as $key => $errors) {
            if ($this->isField((string) $key)) {
                continue;
            }
            foreach ((array) $errors as $message) {
                $messages[] = (string) $message;
            }
        }

        $waiting = $this->waitingTitles();
        $messages = array_values(array_diff(array_unique(array_filter($messages, fn (string $m) => trim($m) !== '')), $waiting));
        if ($messages === []) {
            return;
        }

        Notification::make()->danger()
            ->title($messages[0])
            ->body(count($messages) > 1 ? implode(' · ', array_slice($messages, 1)) : null)
            ->send();
    }

    private function isField(string $key): bool
    {
        foreach (self::FIELD_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix) || $key === rtrim($prefix, '.')) {
                return true;
            }
        }

        // A screen's own public property (a search, a reason typed outside a form) shows its error under it (@error).
        $property = explode('.', $key, 2)[0];

        return $property !== '' && property_exists($this->component, $property)
            && (new \ReflectionProperty($this->component, $property))->isPublic();
    }

    /** @return list<string> the titles of the notifications already waiting to be shown in this request */
    private function waitingTitles(): array
    {
        $waiting = session()->get('filament.notifications', []);

        return array_values(array_filter(array_map(
            fn ($n) => is_array($n) ? (string) ($n['title'] ?? '') : '',
            is_array($waiting) ? $waiting : [],
        )));
    }
}
