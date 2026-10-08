<?php

namespace Modules\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\Support\Enums\TicketKind;
use Modules\Support\Enums\TicketPriority;
use Modules\Support\Models\Ticket;
use Modules\Support\Models\TicketMessage;

/** Tickets in words, for every screen: the changes on a ticket's timeline, and how its targets stand. */
final class TicketView
{
    /** @param  array<string, string>  $names  user id => name */
    public static function event(TicketMessage $m, array $names): string
    {
        $l = 'support::tickets.events.';
        $by = $m->author_id ? ($names[$m->author_id] ?? '') : '';
        $data = $m->data;

        return match ($m->event) {
            'assigned' => isset($data['to']) ? __($l.'assigned', ['name' => $by, 'to' => $names[$data['to']] ?? '']) : __($l.'unassigned', ['name' => $by]),
            'kind' => __($l.'kind', ['name' => $by, 'from' => TicketKind::tryFrom((string) ($data['from'] ?? ''))?->label() ?? '', 'to' => TicketKind::tryFrom((string) ($data['to'] ?? ''))?->label() ?? '']),
            'priority' => __($l.'priority', ['name' => $by, 'from' => TicketPriority::tryFrom((string) ($data['from'] ?? ''))?->label() ?? '', 'to' => TicketPriority::tryFrom((string) ($data['to'] ?? ''))?->label() ?? '']),
            'complaint' => __($l.'complaint_'.($data['decision'] ?? 'validated'), ['name' => $by]),
            'closed' => $m->author_id === null ? __($l.'closed_quiet', ['days' => Ticket::CLOSE_AFTER_DAYS]) : __($l.'closed', ['name' => $by]),
            'reopened' => __($l.'reopened', ['name' => $by]),
            default => __($l.$m->event, ['name' => $by]),
        };
    }

    /**
     * A ticket's lines for a screen, in the viewer's time zone: whose side each is on (the viewer's own on one
     * side), the words, the attachment (a picture shown, a PDF linked) and when.
     *
     * @param  list<TicketMessage>  $messages
     * @param  array<string, string>  $names
     * @param  string  $mySide  requester | staff: whose lines stand on the viewer's side
     * @return list<array<string, mixed>>
     */
    public static function lines(array $messages, array $names, string $mySide, string $tz): array
    {
        $locale = app()->getLocale();

        return array_map(function (TicketMessage $m) use ($names, $mySide, $tz, $locale) {
            $at = $m->created_at->setTimezone($tz)->locale($locale);

            return [
                'id' => $m->id,
                'kind' => $m->kind,
                'side' => $m->side,
                'mine' => $m->side === $mySide,
                // Written down by someone at the academy for the requester: the requester's words, with who wrote them.
                'author' => $m->author_id && ! ($m->data['written_by_staff'] ?? false) ? ($names[$m->author_id] ?? '') : null,
                'writer' => ($m->data['written_by_staff'] ?? false) && $m->author_id ? ($names[$m->author_id] ?? '') : null,
                'body' => $m->body,
                'event' => $m->kind === 'event' ? self::event($m, $names) : null,
                'note' => $m->kind === 'event' && $m->event === 'complaint' ? ($m->data['note'] ?? null) : null,
                'file' => $m->file_path ? [
                    'name' => (string) $m->file_name,
                    'image' => str_starts_with((string) $m->mime, 'image/') && $m->mime !== 'image/heic',
                    'url' => route('support.file', ['message' => $m->id]),
                ] : null,
                'when' => $at->translatedFormat('j M · H:i'),
                'ago' => $at->diffForHumans(['options' => CarbonInterface::JUST_NOW]),
            ];
        }, $messages);
    }

    /**
     * How the targets stand, for the academy: the first answer (late, or due in so long) while none was given,
     * then the solution.
     *
     * @return array{state: string, text: string}|null state: late | soon | ok; null once resolved or closed
     */
    public static function target(Ticket $t, ?CarbonImmutable $now = null): ?array
    {
        if (! $t->status->isActive()) {
            return null;
        }
        $now ??= CarbonImmutable::now();
        $l = 'support::tickets.targets.';
        [$due, $what] = $t->first_response_at === null ? [$t->respond_by, 'answer'] : [$t->resolve_by, 'solve'];
        $span = fn (CarbonImmutable $a, CarbonImmutable $b) => $a->locale(app()->getLocale())->diffForHumans($b, ['syntax' => CarbonImmutable::DIFF_ABSOLUTE, 'parts' => 1]);
        if ($due->lessThan($now)) {
            return ['state' => 'late', 'text' => __($l.$what.'_late', ['time' => $span($due, $now)])];
        }

        return ['state' => $due->diffInMinutes($now, true) <= 60 ? 'soon' : 'ok', 'text' => __($l.$what.'_in', ['time' => $span($due, $now)])];
    }
}
