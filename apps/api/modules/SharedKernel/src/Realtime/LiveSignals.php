<?php

namespace Modules\SharedKernel\Realtime;

use Closure;

/**
 * What a change in the database means for the screens. A table trigger
 * raises pg_notify('lms_live', '{topic}:{id}') on commit; the module that
 * owns the topic says here which broadcast event that becomes (its channel,
 * its name, no data). `live:relay` does the rest.
 *
 *     $signals->on('inbox', fn (string $userId) => new InboxChanged($userId));
 */
final class LiveSignals
{
    public const PG_CHANNEL = 'lms_live';

    /** @var array<string, Closure(string): object> */
    private array $topics = [];

    /** @param  Closure(string): object  $event */
    public function on(string $topic, Closure $event): void
    {
        $this->topics[$topic] = $event;
    }

    /** The event for a raised "{topic}:{id}", or null when no module owns the topic. */
    public function event(string $raised): ?object
    {
        [$topic, $id] = array_pad(explode(':', $raised, 2), 2, '');

        return isset($this->topics[$topic]) && $id !== '' ? ($this->topics[$topic])($id) : null;
    }
}
